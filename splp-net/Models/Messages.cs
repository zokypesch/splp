using System.Text.Json.Serialization;

namespace SplpNet.Models;

/// <summary>
/// Request message structure
/// </summary>
/// <typeparam name="T">Type of the payload</typeparam>
public class RequestMessage<T>
{
    [JsonPropertyName("request_id")]
    public string RequestId { get; set; } = null!;

    [JsonPropertyName("payload")]
    public T Payload { get; set; } = default!;

    [JsonPropertyName("timestamp")]
    public long Timestamp { get; set; }
}

/// <summary>
/// Response message structure
/// </summary>
/// <typeparam name="T">Type of the payload</typeparam>
public class ResponseMessage<T>
{
    [JsonPropertyName("request_id")]
    public string RequestId { get; set; } = null!;

    [JsonPropertyName("payload")]
    public T Payload { get; set; } = default!;

    [JsonPropertyName("timestamp")]
    public long Timestamp { get; set; }

    [JsonPropertyName("success")]
    public bool Success { get; set; }

    [JsonPropertyName("error")]
    public string? Error { get; set; }
}

/// <summary>
/// Encrypted message structure
/// </summary>
public class EncryptedMessage
{
    /// <summary>
    /// Request ID (not encrypted for tracing)
    /// </summary>
    [JsonPropertyName("request_id")]
    public string RequestId { get; set; } = null!;

    /// <summary>
    /// Encrypted payload data
    /// </summary>
    [JsonPropertyName("data")]
    public string Data { get; set; } = null!;

    /// <summary>
    /// Initialization vector for AES-GCM
    /// </summary>
    [JsonPropertyName("iv")]
    public string Iv { get; set; } = null!;

    /// <summary>
    /// Authentication tag for AES-GCM
    /// </summary>
    [JsonPropertyName("tag")]
    public string Tag { get; set; } = null!;
}

/// <summary>
/// Log entry for Cassandra
/// </summary>
public class LogEntry
{
    public string RequestId { get; set; } = null!;
    public DateTime Timestamp { get; set; }
    public string Type { get; set; } = null!; // "request" or "response"
    public string Topic { get; set; } = null!;
    public object Payload { get; set; } = null!;
    public bool? Success { get; set; }
    public string? Error { get; set; }
    public long? DurationMs { get; set; }
}
