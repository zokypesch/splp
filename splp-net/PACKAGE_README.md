# SplpNet - Kafka Request-Reply Messaging Library

[![NuGet](https://img.shields.io/nuget/v/SplpNet.svg)](https://www.nuget.org/packages/SplpNet/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A comprehensive .NET library for implementing Request-Reply patterns over Kafka with built-in encryption, tracing, and Cassandra logging.

## 🚀 Quick Start

### Installation

```bash
dotnet add package SplpNet
```

### Basic Usage

```csharp
using SplpNet;

// Configuration
var config = new MessagingConfig
{
    Kafka = new KafkaConfig
    {
        Brokers = new[] { "localhost:9092" },
        ClientId = "my-service"
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

// Initialize client
using var client = new MessagingClient(config);
await client.InitializeAsync();

// Register handler
client.RegisterHandler<MyRequest, MyResponse>("my-topic", async (requestId, payload) =>
{
    return new MyResponse { Result = payload.Value * 2 };
});

// Start consuming
await client.StartConsumingAsync(new[] { "my-topic" });

// Send request
var response = await client.RequestAsync<MyRequest, MyResponse>("my-topic", new MyRequest { Value = 42 });
```

## ✨ Features

- **🔐 AES-256-GCM Encryption** - Transparent payload encryption/decryption
- **📊 Distributed Tracing** - UUID-based request tracking
- **📝 Cassandra Logging** - Automatic request/response logging with TTL
- **⚡ Simple API** - Single-line Kafka connection setup
- **🔄 Request-Reply Pattern** - Timeout support and concurrent handling
- **🛡️ Type Safety** - Full generic type support
- **🌐 Cross-Platform** - Works on Windows, Linux, macOS

## 🎯 Target Frameworks

- **.NET 8.0** - Latest features and performance
- **.NET 6.0** - LTS support
- **.NET Standard 2.1** - Broad compatibility

## 📦 Dependencies

- **Confluent.Kafka** - Kafka client
- **CassandraCSharpDriver** - Cassandra connectivity
- **System.Text.Json** - JSON serialization
- **Microsoft.Extensions.*** - Logging and DI support

## 🔧 Configuration

### Environment Variables

```bash
# Required: 64-character hex encryption key
ENCRYPTION_KEY=abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789
```

### Generate Encryption Key

```csharp
var key = SplpNetFactory.GenerateEncryptionKey();
Console.WriteLine($"ENCRYPTION_KEY={key}");
```

## 📚 Documentation

For complete documentation, examples, and advanced usage, visit:
- [GitHub Repository](https://github.com/your-org/splp-net)
- [API Documentation](https://github.com/your-org/splp-net/wiki)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- Create an issue on [GitHub](https://github.com/your-org/splp-net/issues)
- Check the [documentation](https://github.com/your-org/splp-net/wiki)
- Review [examples](https://github.com/your-org/splp-net/tree/main/examples)
