/**
 * Kemensos (Ministry of Social Affairs)
 * Submits social assistance verification request to Command Center
 * Command Center routes to all verification services
 */

using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;
using SplpNet.Models;
using SplpNet.Configuration;
using SplpNet.Utils;
using SplpNet.Crypto;
using SplpNet.Kafka;
using SplpNet.RequestReply;

var builder = Host.CreateApplicationBuilder(args);

// Configure logging
builder.Logging.ClearProviders();
builder.Logging.AddConsole();

// Configure services
builder.Services.AddSingleton<PublisherService>();

var host = builder.Build();

// Run the publisher
var publisherService = host.Services.GetRequiredService<PublisherService>();
await publisherService.RunAsync();

public class PublisherService
{
    private readonly ILogger<PublisherService> _logger;

    public PublisherService(ILogger<PublisherService> logger)
    {
        _logger = logger;
    }

    public async Task RunAsync()
    {
        _logger.LogInformation("═══════════════════════════════════════════════════════════");
        _logger.LogInformation("🏛️  KEMENSOS - Kementerian Sosial RI");
        _logger.LogInformation("    Social Assistance Verification System");
        _logger.LogInformation("═══════════════════════════════════════════════════════════");

        // Configuration - Load from appsettings.json or environment variables
        var config = ConfigurationHelper.LoadFromEnvironmentOrFile();
        config.Kafka.ClientId = "publisher-kemensos";
        config.Kafka.GroupId = "publisher-group";

        using var client = new MessagingClient(config);
        
        try
        {
            await client.InitializeAsync();
            _logger.LogInformation("✓ Kemensos connected to Kafka");

            // Create social assistance verification request
            var requestId = RequestIdGenerator.GenerateRequestId();
            var payload = new BansosCitizenRequest
            {
                RegistrationId = $"BANSOS-{DateTimeOffset.UtcNow.ToUnixTimeMilliseconds()}",
                Nik = "3174012501850001",
                FullName = "Budi Santoso",
                DateOfBirth = "1985-01-25",
                Address = "Jl. Merdeka No. 123, Jakarta Pusat",
                AssistanceType = "PKH", // Program Keluarga Harapan
                RequestedAmount = 3000000 // Rp 3.000.000
            };

            _logger.LogInformation("📋 Pengajuan Bantuan Sosial:");
            _logger.LogInformation("  Request ID: {RequestId}", requestId);
            _logger.LogInformation("  Registration ID: {RegistrationId}", payload.RegistrationId);
            _logger.LogInformation("  NIK: {Nik}", payload.Nik);
            _logger.LogInformation("  Nama Lengkap: {FullName}", payload.FullName);
            _logger.LogInformation("  Tanggal Lahir: {DateOfBirth}", payload.DateOfBirth);
            _logger.LogInformation("  Alamat: {Address}", payload.Address);
            _logger.LogInformation("  Jenis Bantuan: {AssistanceType}", payload.AssistanceType);
            _logger.LogInformation("  Nominal: Rp {RequestedAmount:N0}", payload.RequestedAmount);

            // Encrypt payload and create message for Command Center
            var encrypted = EncryptionService.EncryptPayload(payload, config.Encryption.EncryptionKey, requestId);

            var message = new
            {
                request_id = requestId,
                worker_name = "initial-publisher", // Routes to all verification services
                data = encrypted.Data,
                iv = encrypted.Iv,
                tag = encrypted.Tag
            };

            // Send to Command Center
            _logger.LogInformation("📤 Mengirim ke Command Center untuk verifikasi...");
            
            var messageJson = System.Text.Json.JsonSerializer.Serialize(message);
            var kafkaWrapper = new KafkaWrapper(config.Kafka);
            await kafkaWrapper.ConnectProducerAsync();
            await kafkaWrapper.SendMessageAsync("command-center-inbox", messageJson, requestId);

            _logger.LogInformation("✓ Data terkirim ke Command Center");
            _logger.LogInformation("  → Akan diverifikasi oleh:");
            _logger.LogInformation("     • Dukcapil (Data Kependudukan)");
            _logger.LogInformation("     • BPJS Ketenagakerjaan (Status Pekerjaan)");
            _logger.LogInformation("     • BPJS Kesehatan (Status Kesehatan)");
            _logger.LogInformation("     • Bank Indonesia (Data Finansial)");

            // Wait a bit for processing
            _logger.LogInformation("Waiting for services to process...");
            await Task.Delay(5000);

            await kafkaWrapper.DisconnectAsync();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "❌ Error occurred");
        }
        finally
        {
            await client.CloseAsync();
            _logger.LogInformation("Publisher disconnected");
        }
    }
}

public class BansosCitizenRequest
{
    public string RegistrationId { get; set; } = null!;
    public string Nik { get; set; } = null!;              // Nomor Induk Kependudukan
    public string FullName { get; set; } = null!;
    public string DateOfBirth { get; set; } = null!;
    public string Address { get; set; } = null!;
    public string AssistanceType { get; set; } = null!;   // Type of social assistance requested
    public decimal RequestedAmount { get; set; } // Amount of assistance
}
