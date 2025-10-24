# SPLP Java Library

A Java implementation of the Secure Perlin Library Protocol (SPLP) for secure, distributed messaging with encryption, logging, and fault tolerance.

## Overview

SPLP Java provides a robust messaging framework that includes:

- **Secure Messaging**: End-to-end encryption using AES-256-GCM
- **Kafka Integration**: Reliable message queuing and pub/sub patterns
- **Cassandra Logging**: Persistent logging of all message interactions
- **Request-Reply Pattern**: Asynchronous request-response messaging with timeouts
- **Fault Tolerance**: Circuit breakers, retry mechanisms, and connection recovery
- **Type Safety**: Strongly typed interfaces and comprehensive error handling

## Features

### Core Components

- **MessagingClient**: Main interface for sending and receiving encrypted messages
- **EncryptionService**: AES-256-GCM encryption/decryption with secure key management
- **CassandraLogger**: Structured logging to Cassandra with time-based queries
- **KafkaWrapper**: Kafka producer/consumer management with connection pooling
- **CircuitBreaker**: Fault tolerance for external service calls
- **RetryManager**: Configurable retry logic with exponential backoff

### Security Features

- AES-256-GCM encryption with random IVs
- Secure key generation and validation
- Message integrity verification
- Request ID tracking for audit trails

### Reliability Features

- Automatic connection recovery
- Configurable timeouts and retries
- Circuit breaker pattern for fault tolerance
- Comprehensive error handling and logging

## Prerequisites

- Java 17 or higher
- Maven 3.8 or higher
- Docker (for running Kafka and Cassandra)

## Installation

### Maven Dependency

Add the following dependency to your `pom.xml`:

```xml
<dependency>
    <groupId>com.perlinsos</groupId>
    <artifactId>splp-java</artifactId>
    <version>1.0.0</version>
</dependency>
```

### Build from Source

```bash
git clone https://github.com/perlinsos/splp-java.git
cd splp-java
mvn clean install
```

## Quick Start

### 1. Start Infrastructure

Start Kafka and Cassandra using Docker Compose:

```bash
# Create docker-compose.yml (see Infrastructure Setup section)
docker-compose up -d
```

```powershell
powershell -ExecutionPolicy Bypass -File .\run_service_fresh.ps1
```

### 2. Basic Usage

```java
import com.perlinsos.splp.messaging.MessagingClient;
import com.perlinsos.splp.crypto.EncryptionService;

// Generate encryption key
String encryptionKey = EncryptionService.generateEncryptionKey();

// Create messaging client
MessagingClient client = new MessagingClient(
    "localhost:9092",           // Kafka bootstrap servers
    "localhost",                // Cassandra host
    9042,                       // Cassandra port
    "datacenter1",              // Cassandra datacenter
    encryptionKey               // Encryption key
);

// Initialize the client
client.initialize();

// Register a message handler
client.registerHandler("user.service", (payload, requestId) -> {
    // Process the incoming message
    Map<String, Object> request = (Map<String, Object>) payload;
    String userId = (String) request.get("userId");
    
    // Return response
    return Map.of(
        "status", "success",
        "userId", userId,
        "timestamp", System.currentTimeMillis()
    );
});

// Start consuming messages
client.startConsuming();

// Send a request
Map<String, Object> request = Map.of("userId", "12345", "action", "getProfile");
CompletableFuture<Object> response = client.request("user.service", request, Duration.ofSeconds(30));

// Handle the response
response.thenAccept(result -> {
    System.out.println("Response: " + result);
}).exceptionally(error -> {
    System.err.println("Error: " + error.getMessage());
    return null;
});

// Close the client when done
client.close();
```

### 3. Service Example

See the complete service example in `src/main/java/com/perlinsos/splp/examples/service1/Service1Application.java`:

```java
public class Service1Application {
    public static void main(String[] args) throws Exception {
        // Initialize messaging client
        MessagingClient messagingClient = new MessagingClient(
            "localhost:9092",
            "localhost", 
            9042,
            "datacenter1",
            System.getenv("ENCRYPTION_KEY")
        );
        
        messagingClient.initialize();
        
        // Register handlers for different message types
        messagingClient.registerHandler("service-1-topic", Service1Application::handleCitizenVerification);
        
        // Start processing messages
        messagingClient.startConsuming();
        
        System.out.println("Service 1 (Dukcapil Service) is running...");
        
        // Keep the service running
        Runtime.getRuntime().addShutdownHook(new Thread(messagingClient::close));
        Thread.currentThread().join();
    }
}
```

## Configuration

### Environment Variables

```bash
# Kafka Configuration
KAFKA_BOOTSTRAP_SERVERS=localhost:9092

# Cassandra Configuration
CASSANDRA_HOST=localhost
CASSANDRA_PORT=9042
CASSANDRA_DATACENTER=datacenter1

# Security Configuration
ENCRYPTION_KEY=your-base64-encoded-encryption-key

# Logging Configuration
LOG_LEVEL=INFO
```

### Application Properties

Create `application.properties` in your resources directory:

```properties
# Kafka Settings
kafka.bootstrap.servers=${KAFKA_BOOTSTRAP_SERVERS:localhost:9092}
kafka.consumer.group.id=splp-consumer-group
kafka.consumer.auto.offset.reset=earliest
kafka.producer.acks=all
kafka.producer.retries=3

# Cassandra Settings
cassandra.contact.points=${CASSANDRA_HOST:localhost}
cassandra.port=${CASSANDRA_PORT:9042}
cassandra.datacenter=${CASSANDRA_DATACENTER:datacenter1}
cassandra.keyspace=splp

# Circuit Breaker Settings
circuit.breaker.failure.threshold=5
circuit.breaker.timeout.duration=60000
circuit.breaker.retry.timeout=30000

# Retry Settings
retry.max.attempts=3
retry.base.delay=1000
retry.max.delay=10000
retry.jitter=true
```

## Infrastructure Setup

### Docker Compose

Create a `docker-compose.yml` file:

```yaml
version: '3.8'
services:
  zookeeper:
    image: confluentinc/cp-zookeeper:7.4.0
    environment:
      ZOOKEEPER_CLIENT_PORT: 2181
      ZOOKEEPER_TICK_TIME: 2000
    ports:
      - "2181:2181"

  kafka:
    image: confluentinc/cp-kafka:7.4.0
    depends_on:
      - zookeeper
    ports:
      - "9092:9092"
    environment:
      KAFKA_BROKER_ID: 1
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181
      KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://localhost:9092
      KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR: 1
      KAFKA_AUTO_CREATE_TOPICS_ENABLE: true

  cassandra:
    image: cassandra:4.1
    ports:
      - "9042:9042"
    environment:
      CASSANDRA_CLUSTER_NAME: splp-cluster
      CASSANDRA_DC: datacenter1
      CASSANDRA_RACK: rack1
    volumes:
      - cassandra_data:/var/lib/cassandra

volumes:
  cassandra_data:
```

Start the infrastructure:

```bash
docker-compose up -d
```

### Manual Setup

#### Kafka Setup

1. Download and extract Kafka
2. Start Zookeeper: `bin/zookeeper-server-start.sh config/zookeeper.properties`
3. Start Kafka: `bin/kafka-server-start.sh config/server.properties`

#### Cassandra Setup

1. Download and extract Cassandra
2. Start Cassandra: `bin/cassandra -f`
3. Create keyspace using `cqlsh`:

```sql
CREATE KEYSPACE splp WITH replication = {
    'class': 'SimpleStrategy',
    'replication_factor': 1
};
```

## API Reference

### MessagingClient

The main interface for messaging operations.

#### Constructor

```java
public MessagingClient(String kafkaBootstrapServers, String cassandraHost, 
                      int cassandraPort, String cassandraDatacenter, String encryptionKey)
```

#### Methods

- `initialize()`: Initialize Kafka and Cassandra connections
- `registerHandler(String topic, MessageHandler handler)`: Register message handler for a topic
- `startConsuming()`: Start consuming messages from registered topics
- `request(String topic, Object payload, Duration timeout)`: Send request and wait for response
- `close()`: Close all connections and cleanup resources

### EncryptionService

Handles message encryption and decryption.

#### Static Methods

- `generateEncryptionKey()`: Generate a new AES-256 encryption key
- `encryptPayload(Object payload, String key, String requestId)`: Encrypt a payload
- `decryptPayload(EncryptedMessage message, String key)`: Decrypt an encrypted message

### CassandraLogger

Provides structured logging to Cassandra.

#### Methods

- `initialize()`: Initialize Cassandra connection and create tables
- `log(LogEntry entry)`: Log a message entry
- `getLogsByRequestId(String requestId)`: Retrieve logs for a specific request
- `getLogsByTimeRange(Instant start, Instant end)`: Retrieve logs within time range
- `close()`: Close Cassandra connection

### CircuitBreaker

Implements the circuit breaker pattern for fault tolerance.

#### Methods

- `execute(Supplier<T> operation)`: Execute operation with circuit breaker protection
- `executeAsync(Supplier<CompletableFuture<T>> operation)`: Execute async operation
- `getState()`: Get current circuit breaker state (CLOSED, OPEN, HALF_OPEN)
- `reset()`: Manually reset the circuit breaker

## Testing

### Unit Tests

Run unit tests:

```bash
mvn test
```

### Integration Tests

Run integration tests (requires Docker):

```bash
mvn verify -P integration-tests
```

### Test Coverage

Generate test coverage report:

```bash
mvn jacoco:report
```

View coverage report at `target/site/jacoco/index.html`.

## Examples

### Simple Request-Response

```java
// Client 1 - Sender
CompletableFuture<Object> response = client.request("math.service", 
    Map.of("operation", "add", "a", 5, "b", 3), 
    Duration.ofSeconds(10));

response.thenAccept(result -> {
    Map<String, Object> res = (Map<String, Object>) result;
    System.out.println("Result: " + res.get("result")); // Result: 8
});

// Client 2 - Handler
client.registerHandler("math.service", (payload, requestId) -> {
    Map<String, Object> request = (Map<String, Object>) payload;
    String operation = (String) request.get("operation");
    int a = (Integer) request.get("a");
    int b = (Integer) request.get("b");
    
    int result = switch (operation) {
        case "add" -> a + b;
        case "subtract" -> a - b;
        case "multiply" -> a * b;
        case "divide" -> a / b;
        default -> throw new IllegalArgumentException("Unknown operation: " + operation);
    };
    
    return Map.of("result", result, "operation", operation);
});
```

### Error Handling

```java
CompletableFuture<Object> response = client.request("unreliable.service", 
    Map.of("data", "test"), 
    Duration.ofSeconds(5));

response.handle((result, error) -> {
    if (error != null) {
        if (error instanceof TimeoutException) {
            System.err.println("Request timed out");
        } else if (error instanceof ExecutionException) {
            System.err.println("Service error: " + error.getCause().getMessage());
        } else {
            System.err.println("Unexpected error: " + error.getMessage());
        }
        return null;
    } else {
        System.out.println("Success: " + result);
        return result;
    }
});
```

### Batch Processing

```java
List<CompletableFuture<Object>> futures = new ArrayList<>();

for (int i = 0; i < 100; i++) {
    Map<String, Object> request = Map.of("id", i, "data", "batch-" + i);
    CompletableFuture<Object> future = client.request("batch.service", request, Duration.ofSeconds(30));
    futures.add(future);
}

CompletableFuture<Void> allFutures = CompletableFuture.allOf(futures.toArray(new CompletableFuture[0]));

allFutures.thenRun(() -> {
    System.out.println("All batch requests completed");
    futures.forEach(future -> {
        try {
            Object result = future.get();
            System.out.println("Batch result: " + result);
        } catch (Exception e) {
            System.err.println("Batch error: " + e.getMessage());
        }
    });
});
```

## Performance Tuning

### Kafka Configuration

```properties
# Producer Performance
kafka.producer.batch.size=16384
kafka.producer.linger.ms=5
kafka.producer.compression.type=snappy
kafka.producer.buffer.memory=33554432

# Consumer Performance
kafka.consumer.fetch.min.bytes=1024
kafka.consumer.fetch.max.wait.ms=500
kafka.consumer.max.partition.fetch.bytes=1048576
```

### Cassandra Configuration

```properties
# Connection Pool
cassandra.connection.pool.local.core=2
cassandra.connection.pool.local.max=8
cassandra.connection.pool.remote.core=1
cassandra.connection.pool.remote.max=2

# Query Performance
cassandra.request.timeout=12000
cassandra.read.timeout=12000
cassandra.connect.timeout=5000
```

### JVM Tuning

```bash
# Memory Settings
-Xms2g -Xmx4g

# Garbage Collection
-XX:+UseG1GC
-XX:MaxGCPauseMillis=200
-XX:G1HeapRegionSize=16m

# Performance Monitoring
-XX:+PrintGC
-XX:+PrintGCDetails
-XX:+PrintGCTimeStamps
```

## Monitoring and Observability

### Logging Configuration

The library uses SLF4J for logging. Configure `logback.xml`:

```xml
<configuration>
    <appender name="CONSOLE" class="ch.qos.logback.core.ConsoleAppender">
        <encoder>
            <pattern>%d{HH:mm:ss.SSS} [%thread] %-5level %logger{36} - %msg%n</pattern>
        </encoder>
    </appender>
    
    <appender name="FILE" class="ch.qos.logback.core.rolling.RollingFileAppender">
        <file>logs/splp.log</file>
        <rollingPolicy class="ch.qos.logback.core.rolling.TimeBasedRollingPolicy">
            <fileNamePattern>logs/splp.%d{yyyy-MM-dd}.log</fileNamePattern>
            <maxHistory>30</maxHistory>
        </rollingPolicy>
        <encoder>
            <pattern>%d{yyyy-MM-dd HH:mm:ss.SSS} [%thread] %-5level %logger{36} - %msg%n</pattern>
        </encoder>
    </appender>
    
    <logger name="com.perlinsos.splp" level="INFO"/>
    <logger name="org.apache.kafka" level="WARN"/>
    <logger name="com.datastax.oss.driver" level="WARN"/>
    
    <root level="INFO">
        <appender-ref ref="CONSOLE"/>
        <appender-ref ref="FILE"/>
    </root>
</configuration>
```

### Metrics Integration

The library provides hooks for metrics collection:

```java
// Custom metrics collector
public class MetricsCollector {
    private final MeterRegistry meterRegistry;
    
    public void recordMessageSent(String topic, long duration) {
        Timer.Sample sample = Timer.start(meterRegistry);
        sample.stop(Timer.builder("splp.message.sent")
            .tag("topic", topic)
            .register(meterRegistry));
    }
    
    public void recordMessageReceived(String topic, boolean success) {
        Counter.builder("splp.message.received")
            .tag("topic", topic)
            .tag("success", String.valueOf(success))
            .register(meterRegistry)
            .increment();
    }
}
```

## Troubleshooting

### Common Issues

#### Connection Errors

**Problem**: `Unable to connect to Kafka/Cassandra`

**Solution**:
1. Verify services are running: `docker-compose ps`
2. Check network connectivity: `telnet localhost 9092`
3. Verify configuration: Check bootstrap servers and contact points
4. Check firewall settings

#### Encryption Errors

**Problem**: `Decryption failed` or `Invalid key format`

**Solution**:
1. Verify encryption key format (Base64 encoded)
2. Ensure same key is used by all clients
3. Check for key rotation issues
4. Verify message integrity

#### Performance Issues

**Problem**: High latency or low throughput

**Solution**:
1. Tune Kafka producer/consumer settings
2. Optimize Cassandra connection pool
3. Adjust JVM heap size
4. Enable compression
5. Use batch processing for high volume

#### Memory Issues

**Problem**: `OutOfMemoryError` or high memory usage

**Solution**:
1. Increase JVM heap size
2. Tune garbage collection settings
3. Implement message size limits
4. Use streaming for large payloads
5. Monitor connection pools

### Debug Mode

Enable debug logging:

```properties
logging.level.com.perlinsos.splp=DEBUG
logging.level.org.apache.kafka=DEBUG
logging.level.com.datastax.oss.driver=DEBUG
```

### Health Checks

Implement health checks for monitoring:

```java
public class HealthCheck {
    private final MessagingClient messagingClient;
    
    public boolean isHealthy() {
        try {
            // Check Kafka connectivity
            boolean kafkaHealthy = messagingClient.getKafkaWrapper().isProducerConnected();
            
            // Check Cassandra connectivity
            boolean cassandraHealthy = messagingClient.getLogger() != null;
            
            return kafkaHealthy && cassandraHealthy;
        } catch (Exception e) {
            return false;
        }
    }
}
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes and add tests
4. Run tests: `mvn test`
5. Commit your changes: `git commit -am 'Add new feature'`
6. Push to the branch: `git push origin feature/new-feature`
7. Create a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/perlinsos/splp-java.git
cd splp-java

# Install dependencies
mvn clean install

# Run tests
mvn test

# Start development infrastructure
docker-compose -f docker-compose.dev.yml up -d

# Run integration tests
mvn verify -P integration-tests
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Documentation**: [https://docs.perlinsos.com/splp-java](https://docs.perlinsos.com/splp-java)
- **Issues**: [https://github.com/perlinsos/splp-java/issues](https://github.com/perlinsos/splp-java/issues)
- **Discussions**: [https://github.com/perlinsos/splp-java/discussions](https://github.com/perlinsos/splp-java/discussions)
- **Email**: support@perlinsos.com

## Changelog

### Version 1.0.0
- Initial release
- Core messaging functionality
- Kafka and Cassandra integration
- AES-256-GCM encryption
- Circuit breaker and retry mechanisms
- Comprehensive test suite
- Documentation and examples