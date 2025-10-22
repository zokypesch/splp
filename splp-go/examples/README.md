# SPLP Go Examples

This directory contains comprehensive examples demonstrating how to use the SPLP (Secure Private Logging Protocol) Go library for building secure, encrypted messaging systems with Kafka and Cassandra.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Examples Overview](#examples-overview)
- [Running the Examples](#running-the-examples)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)

## 🔧 Prerequisites

Before running the examples, ensure you have the following installed and running:

### Required Infrastructure

1. **Kafka** (localhost:9092)
2. **Cassandra** (localhost:9042)
3. **Go** (1.19 or later)

### Quick Infrastructure Setup

Use the provided Docker Compose from the project root:

```bash
# From the project root directory
cd ../../
docker-compose up -d

# Verify services are running
docker-compose ps
```

### Environment Variables

Set the encryption key (recommended for consistency across examples):

```bash
# Windows PowerShell
$env:ENCRYPTION_KEY = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"

# Linux/macOS
export ENCRYPTION_KEY="0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
```

## 🚀 Quick Start

1. **Start Infrastructure**:
   ```bash
   docker-compose up -d
   ```

2. **Run Basic Consumer** (in one terminal):
   ```bash
   cd examples/basic_consumer
   go run main.go
   ```

3. **Run Basic Publisher** (in another terminal):
   ```bash
   cd examples/basic_publisher
   go run main.go
   ```

You should see encrypted messages being sent and received!

## 📚 Examples Overview

### 1. 📤 Basic Publisher (`basic_publisher/`)

**Purpose**: Demonstrates how to send messages using the SPLP Go messaging client.

**Features**:
- Simple message publishing
- Calculation requests
- Multiple message sending
- Automatic encryption
- Response handling

**Use Case**: Perfect for applications that need to send requests and receive responses.

### 2. 🎯 Basic Consumer (`basic_consumer/`)

**Purpose**: Shows how to receive and process messages with automatic decryption.

**Features**:
- Multiple topic handlers
- Calculation processing
- General message processing
- Greeting service
- Graceful shutdown

**Use Case**: Ideal for building worker services that process incoming requests.

### 3. 🔐 Encrypted Messaging (`encrypted_messaging/`)

**Purpose**: Advanced example showcasing encryption capabilities and secure financial transactions.

**Features**:
- Direct encryption/decryption demo
- Secure financial transaction processing
- Data integrity verification
- Sensitive data handling
- Security best practices

**Use Case**: Essential for applications handling sensitive data like financial transactions, PII, or confidential business data.

### 4. 📊 Logging Integration (`logging_integration/`)

**Purpose**: Demonstrates comprehensive logging with Cassandra integration.

**Features**:
- Direct Cassandra logging
- Integrated messaging with automatic logging
- Audit trail creation
- Time-range queries
- Compliance logging (SOX compliant)

**Use Case**: Critical for applications requiring audit trails, compliance logging, and operational monitoring.

## 🏃‍♂️ Running the Examples

### Method 1: Individual Examples

Each example can be run independently:

```bash
# Basic Publisher
cd examples/basic_publisher
go run main.go

# Basic Consumer  
cd examples/basic_consumer
go run main.go

# Encrypted Messaging
cd examples/encrypted_messaging
go run main.go

# Logging Integration
cd examples/logging_integration
go run main.go
```

### Method 2: Complete Workflow

For a complete demonstration:

1. **Terminal 1 - Start Consumer**:
   ```bash
   cd examples/basic_consumer
   go run main.go
   ```

2. **Terminal 2 - Send Messages**:
   ```bash
   cd examples/basic_publisher
   go run main.go
   ```

3. **Terminal 3 - Test Encryption**:
   ```bash
   cd examples/encrypted_messaging
   go run main.go
   ```

4. **Terminal 4 - Check Logging**:
   ```bash
   cd examples/logging_integration
   go run main.go
   ```

## ⚙️ Configuration

### Kafka Configuration

```go
Kafka: types.KafkaConfig{
    Brokers:  []string{"localhost:9092"},
    ClientID: "your-client-id",
    GroupID:  "your-group-id",
}
```

### Cassandra Configuration

```go
Cassandra: &types.CassandraConfig{
    ContactPoints:   []string{"localhost"},
    LocalDataCenter: "datacenter1",
    Keyspace:        "messaging",
}
```

### Encryption Configuration

```go
Encryption: types.EncryptionConfig{
    Key: "64-character-hex-string", // 32 bytes for AES-256
}
```

## 🔍 Example Outputs

### Basic Publisher Output
```
🚀 SPLP Go Basic Publisher Example
==================================
🔧 Initializing messaging client...
✅ Client initialized successfully

📤 Sending simple message...
✅ Received response: {Success:true Payload:map[...]}

🧮 Sending calculation request...
✅ Calculation result: {Success:true Payload:map[result:15 ...]}
```

### Basic Consumer Output
```
🎯 SPLP Go Basic Consumer Example
=================================
🔧 Initializing messaging client...
✅ Client initialized successfully
📝 Registering message handlers...
🎧 Starting to consume messages...
✅ Consumer is running and ready to process messages!

🧮 Processing calculation request: req-12345
✅ Calculation completed: 10.00 add 5.00 = 15.00
```

### Encrypted Messaging Output
```
🔐 SPLP Go Encrypted Messaging Example
=====================================

🔒 Part 1: Direct Encryption/Decryption Demo
-------------------------------------------
📄 Original sensitive data: map[credit_card:4532-1234-5678-9012 ...]
🔐 Encrypted data:
   Request ID: req-67890
   Encrypted Data: a1b2c3d4e5f6...
   IV: 0123456789abcdef01234567
   Auth Tag: fedcba9876543210...
🔓 Decrypted data: map[credit_card:4532-1234-5678-9012 ...]
✅ Request ID integrity verified
```

## 🛠️ Troubleshooting

### Common Issues

1. **Connection Refused (Kafka)**:
   ```
   Error: failed to initialize kafka: connection refused
   ```
   **Solution**: Ensure Kafka is running on localhost:9092
   ```bash
   docker-compose up -d kafka
   ```

2. **Connection Refused (Cassandra)**:
   ```
   Error: failed to initialize cassandra logger: connection refused
   ```
   **Solution**: Ensure Cassandra is running on localhost:9042
   ```bash
   docker-compose up -d cassandra
   ```

3. **Encryption Key Issues**:
   ```
   Error: invalid encryption key length
   ```
   **Solution**: Ensure your encryption key is exactly 64 hex characters (32 bytes)
   ```bash
   # Generate a valid key
   openssl rand -hex 32
   ```

4. **Topic Not Found**:
   ```
   Error: topic does not exist
   ```
   **Solution**: Kafka auto-creates topics, but you can create them manually:
   ```bash
   # Create topics manually if needed
   docker exec -it kafka kafka-topics --create --topic calculate --bootstrap-server localhost:9092
   ```

### Debugging Tips

1. **Enable Verbose Logging**: Set environment variable for detailed logs
   ```bash
   export KAFKA_LOG_LEVEL=debug
   ```

2. **Check Kafka Topics**:
   ```bash
   docker exec -it kafka kafka-topics --list --bootstrap-server localhost:9092
   ```

3. **Monitor Kafka Messages**:
   ```bash
   docker exec -it kafka kafka-console-consumer --topic calculate --bootstrap-server localhost:9092
   ```

4. **Query Cassandra Logs**:
   ```bash
   docker exec -it cassandra cqlsh -e "SELECT * FROM messaging.logs LIMIT 10;"
   ```

## 🔗 Related Documentation

- [Main Project README](../../README.md)
- [SPLP Bun Examples](../../example-bun/)
- [Command Center Guide](../../command-center/COMMAND_CENTER_GUIDE.md)
- [Test Guide](../../TEST_GUIDE.md)

## 🤝 Contributing

When adding new examples:

1. Create a new directory under `examples/`
2. Include a `main.go` file with comprehensive comments
3. Add example to this README
4. Test with the standard infrastructure setup
5. Follow the existing code style and patterns

## 📄 License

This project is part of the SPLP (Secure Private Logging Protocol) suite. See the main project for license information.