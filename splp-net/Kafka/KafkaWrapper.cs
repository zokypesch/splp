using Confluent.Kafka;
using Microsoft.Extensions.Logging;
using SplpNet.Models;

namespace SplpNet.Kafka;

/// <summary>
/// Wrapper for Kafka producer and consumer operations
/// </summary>
public class KafkaWrapper : IDisposable
{
    private readonly KafkaConfig _config;
    private readonly ILogger<KafkaWrapper>? _logger;
    private IProducer<string, string>? _producer;
    private IConsumer<string, string>? _consumer;
    private bool _disposed;

    public KafkaWrapper(KafkaConfig config, ILogger<KafkaWrapper>? logger = null)
    {
        _config = config ?? throw new ArgumentNullException(nameof(config));
        _logger = logger;
    }

    /// <summary>
    /// Connects the Kafka producer
    /// </summary>
    public async Task ConnectProducerAsync()
    {
        if (_producer != null)
            return;

        var producerConfig = new ProducerConfig
        {
            BootstrapServers = string.Join(",", _config.Brokers),
            ClientId = _config.ClientId,
            Acks = Acks.All,
            EnableIdempotence = true,
            MessageTimeoutMs = 30000,
            RequestTimeoutMs = 30000
        };

        _producer = new ProducerBuilder<string, string>(producerConfig)
            .SetErrorHandler((_, e) => _logger?.LogError("Kafka producer error: {Error}", e.Reason))
            .Build();

        _logger?.LogInformation("Kafka producer connected successfully");
        await Task.CompletedTask;
    }

    /// <summary>
    /// Connects the Kafka consumer
    /// </summary>
    public async Task ConnectConsumerAsync()
    {
        if (_consumer != null)
            return;

        var consumerConfig = new ConsumerConfig
        {
            BootstrapServers = string.Join(",", _config.Brokers),
            ClientId = _config.ClientId,
            GroupId = _config.GroupId ?? _config.ClientId,
            AutoOffsetReset = AutoOffsetReset.Latest,
            EnableAutoCommit = true,
            SessionTimeoutMs = 30000,
            HeartbeatIntervalMs = 10000
        };

        _consumer = new ConsumerBuilder<string, string>(consumerConfig)
            .SetErrorHandler((_, e) => _logger?.LogError("Kafka consumer error: {Error}", e.Reason))
            .Build();

        _logger?.LogInformation("Kafka consumer connected successfully");
        await Task.CompletedTask;
    }

    /// <summary>
    /// Sends a message to a Kafka topic
    /// </summary>
    /// <param name="topic">The topic to send to</param>
    /// <param name="message">The message content</param>
    /// <param name="key">Optional message key</param>
    /// <param name="cancellationToken">Cancellation token</param>
    public async Task SendMessageAsync(string topic, string message, string? key = null, CancellationToken cancellationToken = default)
    {
        if (_producer == null)
            throw new InvalidOperationException("Producer not connected. Call ConnectProducerAsync first.");

        try
        {
            var kafkaMessage = new Message<string, string>
            {
                Key = key,
                Value = message,
                Timestamp = new Timestamp(DateTimeOffset.UtcNow)
            };

            var result = await _producer.ProduceAsync(topic, kafkaMessage, cancellationToken);
            
            _logger?.LogDebug("Message sent to topic {Topic}, partition {Partition}, offset {Offset}", 
                result.Topic, result.Partition.Value, result.Offset.Value);
        }
        catch (ProduceException<string, string> ex)
        {
            _logger?.LogError(ex, "Failed to send message to topic {Topic}", topic);
            throw;
        }
    }

    /// <summary>
    /// Subscribes to topics and processes messages
    /// </summary>
    /// <param name="topics">Topics to subscribe to</param>
    /// <param name="messageHandler">Handler for received messages</param>
    /// <param name="cancellationToken">Cancellation token</param>
    public async Task SubscribeAsync(string[] topics, Func<ConsumeResult<string, string>, Task> messageHandler, CancellationToken cancellationToken = default)
    {
        if (_consumer == null)
            throw new InvalidOperationException("Consumer not connected. Call ConnectConsumerAsync first.");

        _consumer.Subscribe(topics);
        _logger?.LogInformation("Subscribed to topics: {Topics}", string.Join(", ", topics));

        try
        {
            while (!cancellationToken.IsCancellationRequested)
            {
                try
                {
                    var consumeResult = _consumer.Consume(cancellationToken);
                    
                    if (consumeResult?.Message != null)
                    {
                        _logger?.LogDebug("Received message from topic {Topic}, partition {Partition}, offset {Offset}",
                            consumeResult.Topic, consumeResult.Partition.Value, consumeResult.Offset.Value);

                        await messageHandler(consumeResult);
                    }
                }
                catch (ConsumeException ex)
                {
                    _logger?.LogError(ex, "Error consuming message");
                }
                catch (OperationCanceledException)
                {
                    break;
                }
                catch (Exception ex)
                {
                    _logger?.LogError(ex, "Unexpected error in message handler");
                }
            }
        }
        finally
        {
            _consumer.Close();
            _logger?.LogInformation("Consumer closed");
        }
    }

    /// <summary>
    /// Disconnects from Kafka
    /// </summary>
    public async Task DisconnectAsync()
    {
        if (_producer != null)
        {
            _producer.Flush(TimeSpan.FromSeconds(10));
            _producer.Dispose();
            _producer = null;
            _logger?.LogInformation("Kafka producer disconnected");
        }

        if (_consumer != null)
        {
            _consumer.Close();
            _consumer.Dispose();
            _consumer = null;
            _logger?.LogInformation("Kafka consumer disconnected");
        }

        await Task.CompletedTask;
    }

    public void Dispose()
    {
        if (!_disposed)
        {
            DisconnectAsync().GetAwaiter().GetResult();
            _disposed = true;
        }
        GC.SuppressFinalize(this);
    }
}
