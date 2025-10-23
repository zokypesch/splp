using System.Collections.Concurrent;
using System.Text.Json;
using Microsoft.Extensions.Logging;
using SplpNet.Crypto;
using SplpNet.Kafka;
using SplpNet.Logging;
using SplpNet.Models;
using SplpNet.Utils;

namespace SplpNet.RequestReply;

/// <summary>
/// Delegate for handling requests
/// </summary>
/// <typeparam name="TRequest">Request payload type</typeparam>
/// <typeparam name="TResponse">Response payload type</typeparam>
/// <param name="requestId">Request ID for tracing</param>
/// <param name="payload">Request payload</param>
/// <returns>Response payload</returns>
public delegate Task<TResponse> RequestHandler<TRequest, TResponse>(string requestId, TRequest payload);

/// <summary>
/// Main messaging client for request-reply patterns over Kafka
/// </summary>
public class MessagingClient : IDisposable
{
    private readonly MessagingConfig _config;
    private readonly ILogger<MessagingClient>? _logger;
    private readonly KafkaWrapper _kafka;
    private readonly CassandraLogger _cassandraLogger;
    private readonly string _encryptionKey;
    
    private readonly ConcurrentDictionary<string, Func<string, object, Task<object>>> _handlers = new();
    private readonly ConcurrentDictionary<string, PendingRequest> _pendingRequests = new();
    
    private bool _disposed;
    private CancellationTokenSource? _cancellationTokenSource;

    private class PendingRequest
    {
        public TaskCompletionSource<object> TaskCompletionSource { get; set; } = new TaskCompletionSource<object>();
        public DateTime StartTime { get; set; }
    }

    // NOTE: Accept optional ILoggerFactory. Keep ILogger<MessagingClient> for convenience/back-compat.
    public MessagingClient(MessagingConfig config, ILoggerFactory? loggerFactory = null)
    {
        _config = config ?? throw new ArgumentNullException(nameof(config));
        _logger = loggerFactory?.CreateLogger<MessagingClient>();
        _kafka = new KafkaWrapper(config.Kafka, loggerFactory?.CreateLogger<KafkaWrapper>());
        _cassandraLogger = new CassandraLogger(config.Cassandra, loggerFactory?.CreateLogger<CassandraLogger>());
        _encryptionKey = config.Encryption.EncryptionKey;
    }

    /// <summary>
    /// Initializes the messaging client - single line setup for users
    /// </summary>
    public async Task InitializeAsync()
    {
        await _kafka.ConnectProducerAsync();
        await _kafka.ConnectConsumerAsync();
        await _cassandraLogger.InitializeAsync();
        
        _logger?.LogInformation("Messaging client initialized successfully");
    }

    /// <summary>
    /// Registers a handler for a specific topic
    /// Users only need to register their handler function
    /// </summary>
    /// <typeparam name="TRequest">Request payload type</typeparam>
    /// <typeparam name="TResponse">Response payload type</typeparam>
    /// <param name="topic">Topic to handle</param>
    /// <param name="handler">Handler function</param>
    public void RegisterHandler<TRequest, TResponse>(string topic, RequestHandler<TRequest, TResponse> handler)
    {
        _handlers[topic] = async (requestId, payload) =>
        {
            if (payload is TRequest typedPayload)
            {
                return await handler(requestId, typedPayload);
            }
            throw new InvalidOperationException($"Invalid payload type for topic {topic}");
        };

        _logger?.LogInformation("Handler registered for topic: {Topic}", topic);
    }

    /// <summary>
    /// Starts consuming messages from specified topics
    /// Automatically handles decryption, handler execution, encryption, and reply sending
    /// </summary>
    /// <param name="topics">Topics to consume from</param>
    /// <param name="cancellationToken">Cancellation token</param>
    public async Task StartConsumingAsync(string[] topics, CancellationToken cancellationToken = default)
    {
        _cancellationTokenSource = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
        
        await _kafka.SubscribeAsync(topics, async consumeResult =>
        {
            var startTime = DateTime.UtcNow;
            string? requestId = null;

            try
            {
                if (consumeResult.Message?.Value == null)
                    return;

                // Parse encrypted message
                var encryptedMessage = JsonSerializer.Deserialize<EncryptedMessage>(consumeResult.Message.Value);
                if (encryptedMessage == null)
                {
                    _logger?.LogWarning("Failed to parse encrypted message");
                    return;
                }

                requestId = encryptedMessage.RequestId;

                // Check if this is a reply to a pending request
                if (_pendingRequests.TryRemove(requestId, out var pendingRequest))
                {
                    // This is a reply - decrypt and complete the pending request
                    var (_, responsePayload) = EncryptionService.DecryptPayload<object>(encryptedMessage, _encryptionKey);
                    pendingRequest.TaskCompletionSource.SetResult(responsePayload);

                    // Log the response
                    await _cassandraLogger.LogAsync(new LogEntry
                    {
                        RequestId = requestId,
                        Timestamp = DateTime.UtcNow,
                        Type = "response",
                        Topic = consumeResult.Topic,
                        Payload = responsePayload,
                        Success = true,
                        DurationMs = (long)(DateTime.UtcNow - pendingRequest.StartTime).TotalMilliseconds
                    });

                    return;
                }

                // This is a new request - find and execute handler
                if (!_handlers.TryGetValue(consumeResult.Topic, out var handler))
                {
                    _logger?.LogWarning("No handler registered for topic: {Topic}", consumeResult.Topic);
                    return;
                }

                // Decrypt the request
                var (decryptedRequestId, requestPayload) = EncryptionService.DecryptPayload<object>(encryptedMessage, _encryptionKey);

                // Log the request
                await _cassandraLogger.LogAsync(new LogEntry
                {
                    RequestId = requestId,
                    Timestamp = startTime,
                    Type = "request",
                    Topic = consumeResult.Topic,
                    Payload = requestPayload,
                    Success = true
                });

                // Execute handler
                var handlerResponse = await handler(requestId, requestPayload);

                // Create response message
                var responseMessage = new ResponseMessage<object>
                {
                    RequestId = requestId,
                    Payload = handlerResponse,
                    Timestamp = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds(),
                    Success = true
                };

                // Encrypt response
                var encryptedResponse = EncryptionService.EncryptPayload(responseMessage, _encryptionKey, requestId);

                // Send reply
                var replyTopic = $"{consumeResult.Topic}-reply";
                await _kafka.SendMessageAsync(replyTopic, JsonSerializer.Serialize(encryptedResponse), requestId, _cancellationTokenSource.Token);

                // Log the response
                await _cassandraLogger.LogAsync(new LogEntry
                {
                    RequestId = requestId,
                    Timestamp = DateTime.UtcNow,
                    Type = "response",
                    Topic = replyTopic,
                    Payload = handlerResponse,
                    Success = true,
                    DurationMs = (long)(DateTime.UtcNow - startTime).TotalMilliseconds
                });

                _logger?.LogDebug("Processed request {RequestId} successfully", requestId);
            }
            catch (Exception ex)
            {
                _logger?.LogError(ex, "Error processing message for request {RequestId}", requestId);

                if (requestId != null)
                {
                    // Log the error
                    await _cassandraLogger.LogAsync(new LogEntry
                    {
                        RequestId = requestId,
                        Timestamp = DateTime.UtcNow,
                        Type = "response",
                        Topic = consumeResult.Topic,
                        Payload = new { },
                        Success = false,
                        Error = ex.Message,
                        DurationMs = (long)(DateTime.UtcNow - startTime).TotalMilliseconds
                    });
                }
            }
        }, _cancellationTokenSource.Token);
    }

    /// <summary>
    /// Sends a request and waits for reply
    /// Automatically handles request ID generation, payload encryption, message sending, reply waiting, and response decryption
    /// </summary>
    /// <typeparam name="TRequest">Request payload type</typeparam>
    /// <typeparam name="TResponse">Response payload type</typeparam>
    /// <param name="topic">Topic to send to</param>
    /// <param name="payload">Request payload</param>
    /// <param name="timeoutMs">Timeout in milliseconds (default: 30000)</param>
    /// <param name="cancellationToken">Cancellation token</param>
    /// <returns>Response payload</returns>
    public async Task<TResponse> RequestAsync<TRequest, TResponse>(
        string topic, 
        TRequest payload, 
        int timeoutMs = 30000, 
        CancellationToken cancellationToken = default)
    {
        var requestId = RequestIdGenerator.GenerateRequestId();
        var startTime = DateTime.UtcNow;

        try
        {
            // Create request message
            var requestMessage = new RequestMessage<TRequest>
            {
                RequestId = requestId,
                Payload = payload,
                Timestamp = DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()
            };

            // Encrypt request
            var encryptedRequest = EncryptionService.EncryptPayload(requestMessage, _encryptionKey, requestId);

            // Set up pending request for reply
            var tcs = new TaskCompletionSource<object>();
            var pendingRequest = new PendingRequest
            {
                TaskCompletionSource = tcs,
                StartTime = startTime
            };

            _pendingRequests[requestId] = pendingRequest;

            // Subscribe to reply topic if not already subscribed
            var replyTopic = $"{topic}-reply";
            
            // Send request
            await _kafka.SendMessageAsync(topic, JsonSerializer.Serialize(encryptedRequest), requestId, cancellationToken);

            // Log the request
            await _cassandraLogger.LogAsync(new LogEntry
            {
                RequestId = requestId,
                Timestamp = startTime,
                Type = "request",
                Topic = topic,
                Payload = payload!,
                Success = true
            });

            // Wait for reply with timeout
            using var timeoutCts = new CancellationTokenSource(timeoutMs);
            using var combinedCts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken, timeoutCts.Token);

            try
            {
#if NET6_0_OR_GREATER
                var response = await tcs.Task.WaitAsync(combinedCts.Token);
#else
                // Fallback for .NET Core 3.1
                var response = await Task.Run(async () => await tcs.Task, combinedCts.Token);
#endif
                
                if (response is ResponseMessage<TResponse> typedResponse)
                {
                    if (!typedResponse.Success)
                    {
                        throw new InvalidOperationException($"Request failed: {typedResponse.Error}");
                    }
                    return typedResponse.Payload;
                }
                
                // Try to deserialize as TResponse directly
                if (response is TResponse directResponse)
                {
                    return directResponse;
                }

                // Try to deserialize from JSON
                var responseJson = JsonSerializer.Serialize(response);
                var deserializedResponse = JsonSerializer.Deserialize<TResponse>(responseJson);
                return deserializedResponse ?? throw new InvalidOperationException("Failed to deserialize response");
            }
            catch (OperationCanceledException) when (timeoutCts.Token.IsCancellationRequested)
            {
                throw new TimeoutException($"Request {requestId} timed out after {timeoutMs}ms");
            }
        }
        finally
        {
            _pendingRequests.TryRemove(requestId, out _);
        }
    }

    /// <summary>
    /// Gets the Cassandra logger instance for manual queries
    /// </summary>
    /// <returns>CassandraLogger instance</returns>
    public CassandraLogger GetLogger()
    {
        return _cassandraLogger;
    }

    /// <summary>
    /// Closes all connections gracefully
    /// </summary>
    public async Task CloseAsync()
    {
        _cancellationTokenSource?.Cancel();
        
        await _kafka.DisconnectAsync();
        _cassandraLogger.Dispose();
        
        _logger?.LogInformation("Messaging client closed");
    }

    public void Dispose()
    {
        if (!_disposed)
        {
            CloseAsync().GetAwaiter().GetResult();
            _kafka.Dispose();
            _cassandraLogger.Dispose();
            _cancellationTokenSource?.Dispose();
            _disposed = true;
        }
        GC.SuppressFinalize(this);
    }
}
