using System.Text.Json;
using SplpNet.Models;

namespace SplpNet.Configuration;

/// <summary>
/// Helper class for loading configuration from appsettings.json or environment variables
/// </summary>
public static class ConfigurationHelper
{
    /// <summary>
    /// Load MessagingConfig from appsettings.json file
    /// </summary>
    /// <param name="configFilePath">Path to appsettings.json file (optional)</param>
    /// <returns>MessagingConfig instance</returns>
    public static MessagingConfig LoadFromFile(string configFilePath = "appsettings.json")
    {
        if (!File.Exists(configFilePath))
        {
            throw new FileNotFoundException($"Configuration file not found: {configFilePath}");
        }

        var json = File.ReadAllText(configFilePath);
        var configRoot = JsonSerializer.Deserialize<ConfigRoot>(json, new JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true
        });

        if (configRoot?.MessagingConfig == null)
        {
            throw new InvalidOperationException("MessagingConfig section not found in configuration file");
        }

        return configRoot.MessagingConfig;
    }

    /// <summary>
    /// Load MessagingConfig from environment variables (fallback to appsettings.json)
    /// </summary>
    /// <returns>MessagingConfig instance</returns>
    public static MessagingConfig LoadFromEnvironmentOrFile()
    {
        var encryptionKey = Environment.GetEnvironmentVariable("ENCRYPTION_KEY");
        
        if (!string.IsNullOrEmpty(encryptionKey))
        {
            // Use environment variables
            return new MessagingConfig
            {
                Kafka = new KafkaConfig
                {
                    Brokers = Environment.GetEnvironmentVariable("KAFKA_BROKERS")?.Split(',') ?? new[] { "localhost:9092" },
                    ClientId = Environment.GetEnvironmentVariable("KAFKA_CLIENT_ID") ?? "splp-client"
                },
                Cassandra = new CassandraConfig
                {
                    ContactPoints = Environment.GetEnvironmentVariable("CASSANDRA_HOSTS")?.Split(',') ?? new[] { "localhost" },
                    LocalDataCenter = Environment.GetEnvironmentVariable("CASSANDRA_DATACENTER") ?? "datacenter1",
                    Keyspace = Environment.GetEnvironmentVariable("CASSANDRA_KEYSPACE") ?? "messaging"
                },
                Encryption = new EncryptionConfig
                {
                    EncryptionKey = encryptionKey
                }
            };
        }
        
        // Fallback to appsettings.json
        return LoadFromFile();
    }

    private class ConfigRoot
    {
        public MessagingConfig MessagingConfig { get; set; } = null!;
    }
}
