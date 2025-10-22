/**
 * Command Center - Message Routing Service
 * Routes messages between services based on worker_name
 * Provides centralized routing and logging
 */

using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using SplpNet;
using System.Text.Json;

var builder = Host.CreateApplicationBuilder(args);

// Configure logging
builder.Logging.ClearProviders();
builder.Logging.AddConsole();

// Configure services
builder.Services.AddSingleton<CommandCenterService>();

var host = builder.Build();

// Run the command center
var commandCenter = host.Services.GetRequiredService<CommandCenterService>();
await commandCenter.RunAsync();

public class CommandCenterService
{
    private readonly ILogger<CommandCenterService> _logger;
    private readonly Dictionary<string, string> _routingTable;

    public CommandCenterService(ILogger<CommandCenterService> logger)
    {
        _logger = logger;
        
        // Define routing table
        _routingTable = new Dictionary<string, string>
        {
            { "initial-publisher", "service-1-topic" },      // Publisher → Service 1 (Dukcapil)
            { "service-1-publisher", "service-2-topic" }     // Service 1 → Service 2 (Kemensos)
        };
    }

    public async Task RunAsync()
    {
        _logger.LogInformation("═══════════════════════════════════════════════════════════");
        _logger.LogInformation("🎯 COMMAND CENTER - Message Routing Service");
        _logger.LogInformation("    Centralized Message Router for SPLP");
        _logger.LogInformation("═══════════════════════════════════════════════════════════");

        // Display routing configuration
        _logger.LogInformation("📋 Routing Configuration:");
        foreach (var route in _routingTable)
        {
            _logger.LogInformation("  ✓ Route: {WorkerName} → {TargetTopic}", route.Key, route.Value);
        }

        // Configuration
        var config = new MessagingConfig
        {
            Kafka = new KafkaConfig
            {
                Brokers = new[] { "localhost:9092" },
                ClientId = "command-center",
                GroupId = "command-center-group"
            },
            Cassandra = new CassandraConfig
            {
                ContactPoints = new[] { "localhost" },
                LocalDataCenter = "datacenter1",
                Keyspace = "messaging"
            },
            Encryption = new EncryptionConfig
            {
                EncryptionKey = Environment.GetEnvironmentVariable("ENCRYPTION_KEY") ?? SplpNetFactory.GenerateEncryptionKey()
            }
        };

        using var client = new MessagingClient(config, _logger.CreateLogger<MessagingClient>());
        
        try
        {
            await client.InitializeAsync();
            _logger.LogInformation("✓ Command Center connected to Kafka");
            _logger.LogInformation("✓ Listening on topic: command-center-inbox");
            _logger.LogInformation("✓ Ready to route messages");

            // Create Kafka wrapper for manual message handling
            var kafkaWrapper = new KafkaWrapper(config.Kafka, _logger.CreateLogger<KafkaWrapper>());
            await kafkaWrapper.ConnectProducerAsync();
            await kafkaWrapper.ConnectConsumerAsync();

            // Subscribe to command-center-inbox
            await kafkaWrapper.SubscribeAsync(new[] { "command-center-inbox" }, async consumeResult =>
            {
                var startTime = DateTime.UtcNow;

                try
                {
                    if (consumeResult.Message?.Value == null) return;

                    var messageValue = consumeResult.Message.Value;
                    var routingMessage = JsonSerializer.Deserialize<RoutingMessage>(messageValue);
                    
                    if (routingMessage == null)
                    {
                        _logger.LogWarning("Failed to parse routing message");
                        return;
                    }

                    _logger.LogInformation("─".PadRight(60, '─'));
                    _logger.LogInformation("📥 [COMMAND CENTER] Message received");
                    _logger.LogInformation("  Request ID: {RequestId}", routingMessage.RequestId);
                    _logger.LogInformation("  Worker Name: {WorkerName}", routingMessage.WorkerName);

                    // Look up routing destination
                    if (!_routingTable.TryGetValue(routingMessage.WorkerName, out var targetTopic))
                    {
                        _logger.LogWarning("❌ No route found for worker: {WorkerName}", routingMessage.WorkerName);
                        return;
                    }

                    // Create encrypted message for target service
                    var targetMessage = new EncryptedMessage
                    {
                        RequestId = routingMessage.RequestId,
                        Data = routingMessage.Data,
                        Iv = routingMessage.Iv,
                        Tag = routingMessage.Tag
                    };

                    // Route to target topic
                    _logger.LogInformation("📤 Routing to: {TargetTopic}", targetTopic);
                    await kafkaWrapper.SendMessageAsync(
                        targetTopic,
                        JsonSerializer.Serialize(targetMessage),
                        routingMessage.RequestId);

                    var duration = (DateTime.UtcNow - startTime).TotalMilliseconds;
                    _logger.LogInformation("✓ Message routed successfully");
                    _logger.LogInformation("  Processing time: {Duration}ms", duration);
                    _logger.LogInformation("─".PadRight(60, '─'));
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, "❌ Error routing message");
                }
            });

            _logger.LogInformation("Command Center is running and routing messages...");
            _logger.LogInformation("Press Ctrl+C to exit");

            // Keep running until cancelled
            var tcs = new TaskCompletionSource();
            Console.CancelKeyPress += (_, e) =>
            {
                e.Cancel = true;
                tcs.SetResult();
            };

            await tcs.Task;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Command Center error");
        }
        finally
        {
            await client.CloseAsync();
            _logger.LogInformation("Command Center disconnected");
        }
    }
}

public class RoutingMessage
{
    [JsonPropertyName("request_id")]
    public string RequestId { get; set; } = null!;

    [JsonPropertyName("worker_name")]
    public string WorkerName { get; set; } = null!;

    [JsonPropertyName("data")]
    public string Data { get; set; } = null!;

    [JsonPropertyName("iv")]
    public string Iv { get; set; } = null!;

    [JsonPropertyName("tag")]
    public string Tag { get; set; } = null!;
}
