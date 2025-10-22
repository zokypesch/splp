namespace SplpNet.Models;

/// <summary>
/// Configuration for Kafka connection
/// </summary>
public class KafkaConfig
{
    /// <summary>
    /// List of Kafka broker addresses
    /// </summary>
    public string[] Brokers { get; set; } = null!;

    /// <summary>
    /// Unique client identifier
    /// </summary>
    public string ClientId { get; set; } = null!;

    /// <summary>
    /// Consumer group ID (optional)
    /// </summary>
    public string? GroupId { get; set; }
}

/// <summary>
/// Configuration for Cassandra connection
/// </summary>
public class CassandraConfig
{
    /// <summary>
    /// List of Cassandra contact points
    /// </summary>
    public string[] ContactPoints { get; set; } = null!;

    /// <summary>
    /// Local data center name
    /// </summary>
    public string LocalDataCenter { get; set; } = null!;

    /// <summary>
    /// Keyspace for logging
    /// </summary>
    public string Keyspace { get; set; } = null!;
}

/// <summary>
/// Configuration for encryption
/// </summary>
public class EncryptionConfig
{
    /// <summary>
    /// 32-byte hex string for AES-256 encryption
    /// </summary>
    public string EncryptionKey { get; set; } = null!;
}

/// <summary>
/// Complete messaging configuration
/// </summary>
public class MessagingConfig
{
    /// <summary>
    /// Kafka configuration
    /// </summary>
    public KafkaConfig Kafka { get; set; } = null!;

    /// <summary>
    /// Cassandra configuration
    /// </summary>
    public CassandraConfig Cassandra { get; set; } = null!;

    /// <summary>
    /// Encryption configuration
    /// </summary>
    public EncryptionConfig Encryption { get; set; } = null!;
}
