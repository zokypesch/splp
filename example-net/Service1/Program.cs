/**
 * Dukcapil - Direktorat Jenderal Kependudukan dan Pencatatan Sipil
 * Verifies citizen identity and population data
 * Checks NIK validity, family data, and address verification
 */

using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using SplpNet;
using SplpNet.Models;
using SplpNet.RequestReply;
using SplpNet.Kafka;
using SplpNet.Crypto;
using System.Text.Json;

var builder = Host.CreateDefaultBuilder(args)
    .ConfigureServices((hostContext, services) =>
    {
        services.AddSingleton<Service1Worker>();
    })
    .ConfigureLogging(logging =>
    {
        logging.ClearProviders();
        logging.AddConsole();
    })
    .Build();

// Run the service
var service = builder.Services.GetRequiredService<Service1Worker>();
await service.RunAsync();

public class Service1Worker
{
    private readonly ILogger<Service1Worker> _logger;
    private readonly ILoggerFactory _loggerFactory;

    public Service1Worker(ILogger<Service1Worker> logger, ILoggerFactory loggerFactory)
    {
        _logger = logger;
        _loggerFactory = loggerFactory;
    }

    public async Task RunAsync()
    {
        _logger.LogInformation("═══════════════════════════════════════════════════════════");
        _logger.LogInformation("🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil");
        _logger.LogInformation("    Population Data Verification Service");
        _logger.LogInformation("═══════════════════════════════════════════════════════════");

        // Configuration
        var config = new MessagingConfig
        {
            Kafka = new KafkaConfig
            {
                Brokers = new[] { "10.70.1.23:9092" },
                ClientId = "dukcapil-service",
                GroupId = "service-1f-group"
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

        using var client = new MessagingClient(config, _loggerFactory);
        
        try
        {
            await client.InitializeAsync();
            _logger.LogInformation("✓ Dukcapil terhubung ke Kafka");
            _logger.LogInformation("✓ Listening on topic: service-1-topic (group: service-1f-group)");
            _logger.LogInformation("✓ Siap memverifikasi data kependudukan");

            // Create Kafka wrapper for manual message handling
            var kafkaWrapper = new KafkaWrapper(config.Kafka, _loggerFactory.CreateLogger<KafkaWrapper>());
            await kafkaWrapper.ConnectProducerAsync();
            await kafkaWrapper.ConnectConsumerAsync();

            // Subscribe to service-1-topic (Command Center routes here)
            await kafkaWrapper.SubscribeAsync(new[] { "service-1-topic" }, async consumeResult =>
            {
                var startTime = DateTime.UtcNow;

                try
                {
                    if (consumeResult.Message?.Value == null) return;

                    var messageValue = consumeResult.Message.Value;
                    _logger.LogInformation("─".PadRight(60, '─'));
                    _logger.LogInformation("📥 [DUKCAPIL] Menerima data dari Command Center");

                    // Parse and decrypt
                    var encryptedMsg = JsonSerializer.Deserialize<EncryptedMessage>(messageValue);
                    if (encryptedMsg == null) return;

                    var (requestId, payload) = EncryptionService.DecryptPayload<BansosCitizenRequest>(
                        encryptedMsg, config.Encryption.EncryptionKey);

                    _logger.LogInformation("  Request ID: {RequestId}", requestId);
                    _logger.LogInformation("  Registration ID: {RegistrationId}", payload.RegistrationId);
                    _logger.LogInformation("  NIK: {Nik}", payload.Nik);
                    _logger.LogInformation("  Nama: {FullName}", payload.FullName);
                    _logger.LogInformation("  Tanggal Lahir: {DateOfBirth}", payload.DateOfBirth);
                    _logger.LogInformation("  Alamat: {Address}", payload.Address);

                    // Verify population data
                    _logger.LogInformation("🔄 Memverifikasi data kependudukan...");
                    await Task.Delay(1000); // Simulate verification

                    // Simulate NIK verification
                    var nikValid = payload.Nik.Length == 16 && payload.Nik.StartsWith("317"); // Jakarta NIK
                    var dataMatch = !string.IsNullOrEmpty(payload.FullName);
                    var random = new Random();
                    var familyMembers = random.Next(1, 6); // 1-5 family members

                    var processedData = new DukcapilVerificationResult
                    {
                        RegistrationId = payload.RegistrationId,
                        Nik = payload.Nik,
                        FullName = payload.FullName,
                        DateOfBirth = payload.DateOfBirth,
                        Address = payload.Address,
                        AssistanceType = payload.AssistanceType,
                        RequestedAmount = payload.RequestedAmount,
                        ProcessedBy = "dukcapil",
                        NikStatus = nikValid ? "valid" : "invalid",
                        DataMatch = dataMatch,
                        FamilyMembers = familyMembers,
                        AddressVerified = true,
                        VerifiedAt = DateTime.UtcNow.ToString("O"),
                        Notes = nikValid ? "Data kependudukan terverifikasi" : "NIK tidak valid"
                    };

                    _logger.LogInformation("  ✅ Status NIK: {NikStatus}", processedData.NikStatus.ToUpper());
                    _logger.LogInformation("  ✅ Data Cocok: {DataMatch}", dataMatch ? "YA" : "TIDAK");
                    _logger.LogInformation("  ✅ Jumlah Anggota Keluarga: {FamilyMembers}", familyMembers);
                    _logger.LogInformation("  ✅ Alamat Terverifikasi: {AddressVerified}", processedData.AddressVerified ? "YA" : "TIDAK");
                    _logger.LogInformation("  📋 Catatan: {Notes}", processedData.Notes);
                    _logger.LogInformation("  🏢 Diproses oleh: DUKCAPIL");

                    // Encrypt processed data
                    var encrypted = EncryptionService.EncryptPayload(processedData, config.Encryption.EncryptionKey, requestId);

                    // Send back to Command Center for routing to service_2
                    var outgoingMessage = new
                    {
                        request_id = requestId,
                        worker_name = "service-1-publisher", // This identifies routing: service_1 -> service_2
                        data = encrypted.Data,
                        iv = encrypted.Iv,
                        tag = encrypted.Tag
                    };

                    _logger.LogInformation("📤 Mengirim hasil verifikasi ke Command Center...");
                    await kafkaWrapper.SendMessageAsync(
                        "command-center-inbox",
                        JsonSerializer.Serialize(outgoingMessage),
                        requestId);

                    var duration = (DateTime.UtcNow - startTime).TotalMilliseconds;
                    _logger.LogInformation("✓ Hasil verifikasi terkirim ke Command Center");
                    _logger.LogInformation("  Waktu proses: {Duration}ms", duration);
                    _logger.LogInformation("─".PadRight(60, '─'));
                }
                catch (Exception ex)
                {
                    _logger.LogError(ex, "❌ Error processing message");
                }
            });

            _logger.LogInformation("Service 1 is running and waiting for messages...");
            _logger.LogInformation("Press Ctrl+C to exit");

            // Keep running until cancelled
            var tcs = new TaskCompletionSource<bool>();
            Console.CancelKeyPress += (_, e) =>
            {
                e.Cancel = true;
                tcs.SetResult(true);
            };

            await tcs.Task;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Service 1 error");
        }
        finally
        {
            await client.CloseAsync();
            _logger.LogInformation("Service 1 disconnected");
        }
    }
}

public class BansosCitizenRequest
{
    public string RegistrationId { get; set; } = null!;
    public string Nik { get; set; } = null!;
    public string FullName { get; set; } = null!;
    public string DateOfBirth { get; set; } = null!;
    public string Address { get; set; } = null!;
    public string AssistanceType { get; set; } = null!;
    public decimal RequestedAmount { get; set; }
}

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
