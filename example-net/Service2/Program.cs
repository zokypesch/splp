/**
 * Kemensos - Result Aggregation Service
 * Receives verification results from all government agencies
 * Aggregates and makes final decision on social assistance eligibility
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
builder.Services.AddSingleton<Service2Worker>();

var host = builder.Build();

// Run the service
var service = host.Services.GetRequiredService<Service2Worker>();
await service.RunAsync();

public class Service2Worker
{
    private readonly ILogger<Service2Worker> _logger;

    public Service2Worker(ILogger<Service2Worker> logger)
    {
        _logger = logger;
    }

    public async Task RunAsync()
    {
        _logger.LogInformation("═══════════════════════════════════════════════════════════");
        _logger.LogInformation("🏛️  KEMENSOS - Result Aggregation Service");
        _logger.LogInformation("    Final Social Assistance Decision System");
        _logger.LogInformation("═══════════════════════════════════════════════════════════");

        // Configuration
        var config = new MessagingConfig
        {
            Kafka = new KafkaConfig
            {
                Brokers = new[] { "localhost:9092" },
                ClientId = "kemensos-aggregation",
                GroupId = "service-2-group"
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
            _logger.LogInformation("✓ Kemensos Aggregation terhubung ke Kafka");
            _logger.LogInformation("✓ Listening on topic: service-2-topic (group: service-2-group)");
            _logger.LogInformation("✓ Siap memproses hasil verifikasi");

            // Create Kafka wrapper for manual message handling
            var kafkaWrapper = new KafkaWrapper(config.Kafka, _logger.CreateLogger<KafkaWrapper>());
            await kafkaWrapper.ConnectConsumerAsync();

            // Subscribe to service-2-topic (Command Center routes here)
            await kafkaWrapper.SubscribeAsync(new[] { "service-2-topic" }, async consumeResult =>
            {
                var startTime = DateTime.UtcNow;

                try
                {
                    if (consumeResult.Message?.Value == null) return;

                    var messageValue = consumeResult.Message.Value;
                    _logger.LogInformation("═".PadRight(60, '═'));
                    _logger.LogInformation("📬 [KEMENSOS] FINAL MESSAGE RECEIVED");

                    // Parse and decrypt
                    var encryptedMsg = JsonSerializer.Deserialize<EncryptedMessage>(messageValue);
                    if (encryptedMsg == null) return;

                    var (requestId, payload) = EncryptionService.DecryptPayload<DukcapilVerificationResult>(
                        encryptedMsg, config.Encryption.EncryptionKey);

                    _logger.LogInformation("📋 Order Details:");
                    _logger.LogInformation("  Registration ID: {RegistrationId}", payload.RegistrationId);
                    _logger.LogInformation("  NIK: {Nik}", payload.Nik);
                    _logger.LogInformation("  Nama: {FullName}", payload.FullName);
                    _logger.LogInformation("  Jenis Bantuan: {AssistanceType}", payload.AssistanceType);
                    _logger.LogInformation("  Nominal: Rp {RequestedAmount:N0}", payload.RequestedAmount);

                    _logger.LogInformation("✅ Processing History:");
                    _logger.LogInformation("  Processed by: {ProcessedBy}", payload.ProcessedBy);
                    _logger.LogInformation("  NIK Status: {NikStatus}", payload.NikStatus);
                    _logger.LogInformation("  Data Match: {DataMatch}", payload.DataMatch ? "YA" : "TIDAK");
                    _logger.LogInformation("  Family Members: {FamilyMembers}", payload.FamilyMembers);
                    _logger.LogInformation("  Address Verified: {AddressVerified}", payload.AddressVerified ? "YA" : "TIDAK");

                    // Make final decision based on verification results
                    var isApproved = payload.NikStatus == "valid" && 
                                   payload.DataMatch && 
                                   payload.AddressVerified;

                    if (isApproved)
                    {
                        _logger.LogInformation("✅ BANTUAN SOSIAL DISETUJUI - Saving to database");
                        _logger.LogInformation("✅ Sending confirmation email to citizen");
                        _logger.LogInformation("✅ Updating assistance registry");
                        _logger.LogInformation("✅ Scheduling disbursement");

                        // Simulate database operations
                        await Task.Delay(500);

                        _logger.LogInformation("💰 Disbursement Details:");
                        _logger.LogInformation("  Amount: Rp {RequestedAmount:N0}", payload.RequestedAmount);
                        _logger.LogInformation("  Method: Bank Transfer");
                        _logger.LogInformation("  Expected Date: {ExpectedDate}", DateTime.UtcNow.AddDays(7).ToString("yyyy-MM-dd"));
                    }
                    else
                    {
                        _logger.LogWarning("❌ BANTUAN SOSIAL DITOLAK");
                        _logger.LogWarning("  Reason: Verification failed");
                        if (payload.NikStatus != "valid")
                            _logger.LogWarning("  - NIK tidak valid");
                        if (!payload.DataMatch)
                            _logger.LogWarning("  - Data tidak cocok");
                        if (!payload.AddressVerified)
                            _logger.LogWarning("  - Alamat tidak terverifikasi");

                        _logger.LogInformation("📧 Sending rejection notification to citizen");
                    }

                    var duration = (DateTime.UtcNow - startTime).TotalMilliseconds;
                    _logger.LogInformation("🎉 PROCESSING CHAIN COMPLETED!");
                    _logger.LogInformation("   Publisher → Service 1 (Dukcapil) → Service 2 (Kemensos)");
                    _logger.LogInformation("   Total processing time: {Duration}ms", duration);
                    _logger.LogInformation("═".PadRight(60, '═'));
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, "❌ Error processing final message");
                }
            });

            _logger.LogInformation("Service 2 is running and waiting for messages...");
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
            _logger.LogError(ex, "Service 2 error");
        }
        finally
        {
            await client.CloseAsync();
            _logger.LogInformation("Service 2 disconnected");
        }
    }
}

// Reuse the same models from Service1
public class DukcapilVerificationResult
{
    public string RegistrationId { get; set; } = null!;
    public string Nik { get; set; } = null!;
    public string FullName { get; set; } = null!;
    public string DateOfBirth { get; set; } = null!;
    public string Address { get; set; } = null!;
    public string AssistanceType { get; set; } = null!;
    public decimal RequestedAmount { get; set; }
    public string ProcessedBy { get; set; } = null!;
    public string NikStatus { get; set; } = null!; // 'valid' | 'invalid' | 'blocked'
    public bool DataMatch { get; set; }
    public int FamilyMembers { get; set; }
    public bool AddressVerified { get; set; }
    public string VerifiedAt { get; set; } = null!;
    public string? Notes { get; set; }
}
