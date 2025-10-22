# SplpNet - Kafka Request-Reply Messaging Library for .NET

A comprehensive .NET library for implementing Request-Reply patterns over Kafka with built-in encryption, tracing, and Cassandra logging. Compatible with .NET 8, .NET 6, and .NET Standard 2.1.

## Features

- **Single-line Kafka connection** for both producer and consumer
- **Automatic request_id generation** using UUID v4 for distributed tracing
- **Transparent encryption/decryption** using AES-256-GCM (payload encrypted, request_id visible for tracing)
- **Simple handler registration** - users only write business logic
- **Automatic Cassandra logging** for all requests and responses
- **JSON payload support** with strong typing
- **Request-reply pattern** with timeout support
- **Concurrent request handling**
- **Cross-platform compatibility** (.NET 8, .NET 6, .NET Standard 2.1)

## Stack

- **Kafka** - Message broker for request-reply communication
- **Cassandra** - Distributed logging and tracing storage
- **.NET 8/6/Standard 2.1** - Runtime and type safety
- **Confluent.Kafka** - Kafka client
- **CassandraCSharpDriver** - Cassandra connectivity

## Installation

# Complete workflow create nupkg in one go:

```bash
cd E:\perlinsos\splp\splp-net
dotnet clean
dotnet restore --source https://api.nuget.org/v3/index.json
dotnet build --configuration Release --no-restore
dotnet pack --configuration Release --output .\nupkgs
```

# Verify creation:
```bash
ls .\nupkgs\*.nupkg
```

# Install from nupkg in client project:
```bash
dotnet add package SplpNet --source "E:\perlinsos\splp\splp-net\nupkgs"
```

Or via Package Manager:
```powershell
Install-Package SplpNet -Source "E:\perlinsos\splp\splp-net\nupkgs"
```

## Quick Start

### 1. Generate Encryption Key

```bash
# Option 1: Use the key generator
cd E:\perlinsos\splp\KeyGenerator
dotnet run

# Option 2: Use PowerShell
$bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
$key = [System.BitConverter]::ToString($bytes).Replace('-', '').ToLower()
$env:ENCRYPTION_KEY = $key
Write-Host "Generated key: $key"
```

Or use the factory method in code:
```csharp
using SplpNet.Crypto;

var key = EncryptionService.GenerateEncryptionKey();
Console.WriteLine($"Encryption Key: {key}");
// Save this key securely - all services must use the same key!
```

### 2. Create a Worker (Consumer)

```csharp
using SplpNet;
using Microsoft.Extensions.Logging;

var config = new MessagingConfig
{
    Kafka = new KafkaConfig
    {
        Brokers = new[] { "localhost:9092" },
        ClientId = "my-worker",
        GroupId = "worker-group"
    },
    Cassandra = new CassandraConfig
    {
        ContactPoints = new[] { "localhost" },
        LocalDataCenter = "datacenter1",
        Keyspace = "messaging"
    },
    Encryption = new EncryptionConfig
    {
        EncryptionKey = Environment.GetEnvironmentVariable("ENCRYPTION_KEY")!
    }
};

// Initialize client - single line setup!
using var client = new MessagingClient(config);
await client.InitializeAsync();

// Register handler - only write your business logic!
client.RegisterHandler<CalculateRequest, CalculateResponse>("calculate", async (requestId, payload) =>
{
    Console.WriteLine($"Processing request {requestId}");
    
    var result = payload.A + payload.B;
    
    return new CalculateResponse { Result = result };
});

// Start consuming - encryption/decryption is automatic!
await client.StartConsumingAsync(new[] { "calculate" });
```

### 3. Send Requests (Producer/Client)

```csharp
using var client = new MessagingClient(config);
await client.InitializeAsync();

// Send request - encryption happens automatically!
var response = await client.RequestAsync<CalculateRequest, CalculateResponse>(
    "calculate",
    new CalculateRequest { A = 10, B = 5 },
    30000 // timeout in ms
);

Console.WriteLine($"Result: {response.Result}"); // 15
```

## Configuration

### MessagingConfig

```csharp
var config = new MessagingConfig
{
    Kafka = new KafkaConfig
    {
        Brokers = new[] { "localhost:9092" },    // Kafka broker addresses
        ClientId = "my-service",                 // Unique client identifier
        GroupId = "my-group"                     // Consumer group ID (optional)
    },
    Cassandra = new CassandraConfig
    {
        ContactPoints = new[] { "localhost" },   // Cassandra nodes
        LocalDataCenter = "datacenter1",         // Data center name
        Keyspace = "messaging"                   // Keyspace for logs
    },
    Encryption = new EncryptionConfig
    {
        EncryptionKey = "64-char-hex-key"        // 32-byte (64 hex chars) AES-256 key
    }
};
```

## API Reference

### MessagingClient

#### `InitializeAsync(): Task`
Initialize Kafka connections and Cassandra logger. Must be called before any other operations.

#### `RegisterHandler<TRequest, TResponse>(string topic, RequestHandler<TRequest, TResponse> handler): void`
Register a handler function for a specific topic. The handler receives:
- `requestId: string` - UUID for tracing
- `payload: TRequest` - Decrypted request payload

Returns: `Task<TResponse>` - Response to send back

#### `StartConsumingAsync(string[] topics, CancellationToken cancellationToken = default): Task`
Start consuming messages from specified topics. Automatically handles:
- Message decryption
- Handler execution
- Response encryption
- Reply sending
- Cassandra logging

#### `RequestAsync<TRequest, TResponse>(string topic, TRequest payload, int timeoutMs = 30000, CancellationToken cancellationToken = default): Task<TResponse>`
Send a request and wait for reply. Automatically handles:
- Request ID generation
- Payload encryption
- Message sending
- Reply waiting
- Response decryption
- Cassandra logging

#### `GetLogger(): CassandraLogger`
Get logger instance for manual queries.

#### `CloseAsync(): Task`
Disconnect all connections gracefully.

### CassandraLogger

#### `GetLogsByRequestIdAsync(string requestId): Task<List<LogEntry>>`
Query all logs for a specific request ID.

#### `GetLogsByTimeRangeAsync(DateTime startTime, DateTime endTime): Task<List<LogEntry>>`
Query logs within a time range.

## Example Models

```csharp
public class CalculateRequest
{
    public int A { get; set; }
    public int B { get; set; }
    public string Operation { get; set; } = "add";
}

public class CalculateResponse
{
    public int Result { get; set; }
    public string Operation { get; set; } = "add";
}
```

## Running with Infrastructure

### Prerequisites

1. **Kafka**: Running on `localhost:9092`
   ```bash
   docker run -d -p 9092:9092 apache/kafka
   ```

2. **Cassandra**: Running on `localhost:9042`
   ```bash
   docker run -d -p 9042:9042 cassandra:latest
   ```

3. **Generate shared encryption key**:
   ```csharp
   var key = SplpNetFactory.GenerateEncryptionKey();
   Environment.SetEnvironmentVariable("ENCRYPTION_KEY", key);
   ```

## Security Features

### Encryption
- **Algorithm**: AES-256-GCM (Galois/Counter Mode)
- **Key Size**: 256 bits (32 bytes)
- **Authentication**: Built-in authentication tag
- **IV**: Random 16-byte initialization vector per message

### What's Encrypted
- Request and response payloads
- All business data

### What's NOT Encrypted (for tracing)
- `request_id` - Needed for distributed tracing and correlation

## Logging and Tracing

All requests and responses are automatically logged to Cassandra with:
- `request_id` - UUID for correlation
- `timestamp` - Message timestamp
- `type` - "request" or "response"
- `topic` - Kafka topic
- `payload` - Original payload (stored encrypted)
- `success` - Whether request succeeded
- `error` - Error message if failed
- `duration_ms` - Processing duration

### Query Examples

```csharp
var logger = client.GetLogger();

// Get all logs for a request
var logs = await logger.GetLogsByRequestIdAsync("uuid-here");

// Get logs from last hour
var endTime = DateTime.UtcNow;
var startTime = endTime.AddHours(-1);
var recentLogs = await logger.GetLogsByTimeRangeAsync(startTime, endTime);
```

## Dependency Injection

```csharp
// Program.cs or Startup.cs
services.AddSingleton<MessagingConfig>(config);
services.AddSingleton<MessagingClient>();
services.AddHostedService<MyWorkerService>();
```

## Error Handling

### Timeout
Requests automatically timeout if no reply is received:

```csharp
try 
{
    var response = await client.RequestAsync<MyRequest, MyResponse>("topic", payload, 5000); // 5s timeout
} 
catch (TimeoutException ex) 
{
    Console.WriteLine($"Request timeout: {ex.Message}");
}
```

### Handler Errors
Errors in handlers are automatically caught and logged:

```csharp
client.RegisterHandler<MyRequest, MyResponse>("topic", async (id, payload) =>
{
    if (!payload.IsValid)
    {
        throw new ArgumentException("Invalid payload"); // Automatically logged to Cassandra
    }
    return result;
});
```

## Compatibility

- **.NET 8.0** - Full feature support
- **.NET 6.0** - Full feature support  
- **.NET Standard 2.1** - Full feature support (compatible with .NET Core 3.0+, .NET 5+)

## Best Practices

1. **Use environment variables** for encryption keys
2. **Share the same encryption key** across all services
3. **Use descriptive topic names** for better tracing
4. **Implement proper error handling** in handlers
5. **Set appropriate timeouts** based on expected processing time
6. **Monitor Cassandra logs** for system health
7. **Use strong typing** for request/response models
8. **Use dependency injection** in ASP.NET Core applications

## License

MIT
