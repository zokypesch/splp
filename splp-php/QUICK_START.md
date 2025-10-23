# SPLP PHP Messaging Library

A complete PHP ecosystem for implementing Request-Reply patterns over Kafka with Command Center routing, encryption, and distributed tracing.

## 🚀 Quick Start

### Installation

```bash
composer require splp/php-messaging
```

### Basic Usage

```php
<?php

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Contracts\RequestHandlerInterface;

// Configuration
$config = [
    'kafka' => [
        'brokers' => ['10.70.1.23:9092'], // Production Kafka broker
        'clientId' => 'my-client',
        'groupId' => 'my-group',
        'requestTimeoutMs' => 30000,
        'consumerTopic' => 'service-1-topic',
        'producerTopic' => 'command-center-inbox'
    ],
    'cassandra' => [
        'contactPoints' => ['localhost'],
        'localDataCenter' => 'datacenter1',
        'keyspace' => 'service_1_keyspace'
    ],
    'encryption' => [
        'encryptionKey' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d'
    ]
];

// Create and initialize client
$client = new MessagingClient($config);
$client->initialize();

// Register handler
$client->registerHandler('calculate', new class implements RequestHandlerInterface {
    public function handle(string $requestId, mixed $payload): mixed
    {
        return ['result' => $payload['a'] + $payload['b']];
    }
});

// Send request
$response = $client->request('calculate', ['a' => 10, 'b' => 5]);
echo "Result: " . $response['result']; // Result: 15
```

### Production Listener Example

```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;
use Splp\Messaging\Utils\SignalHandler;

// Load configuration from environment variables
$config = loadConfiguration();

// Create configurations
$kafkaConfig = new KafkaConfig(
    brokers: $config['kafka']['brokers'],
    clientId: $config['kafka']['clientId'],
    groupId: $config['kafka']['groupId'],
    requestTimeoutMs: $config['kafka']['requestTimeoutMs'],
    consumerTopic: $config['kafka']['consumerTopic'],
    producerTopic: $config['kafka']['producerTopic']
);

$cassandraConfig = new CassandraConfig(
    contactPoints: $config['cassandra']['contactPoints'],
    localDataCenter: $config['cassandra']['localDataCenter'],
    keyspace: $config['cassandra']['keyspace']
);

$encryptionConfig = new EncryptionConfig($config['encryption']['key']);

// Create services
$encryptionService = new EncryptionService($encryptionConfig->key);
$logger = new CassandraLogger($cassandraConfig);
$kafkaWrapper = new KafkaWrapper($kafkaConfig, $encryptionService, $logger);

// Initialize services
$encryptionService->initialize();
$logger->initialize();
$kafkaWrapper->initialize();

// Create message processor
$processor = new Service1MessageProcessor(
    $encryptionService,
    $logger,
    $kafkaConfig,
    $kafkaWrapper,
    $config['service']['workerName'] // e.g., 'service-1-publisher'
);

// Set up signal handling for graceful shutdown
$signalHandler = new SignalHandler();
$signalHandler->addCleanupCallback(function() use ($kafkaWrapper) {
    echo "🔒 Closing Kafka connections...\n";
    $kafkaWrapper->close();
});

// Set message handler and start consuming
$kafkaWrapper->setMessageHandler($processor);
$kafkaWrapper->startConsuming([$config['kafka']['consumerTopic']]);

// Main loop with signal handling
while (!$signalHandler->isShutdownRequested()) {
    $signalHandler->processSignals();
    usleep(100000); // 100ms
}
```

## 📋 Features

- ✅ **Kafka Request-Reply Pattern** - Complete implementation with automatic routing
- ✅ **AES-256-GCM Encryption** - End-to-end encryption for all payloads
- ✅ **Command Center Routing** - Central routing hub with schema registry
- ✅ **Cassandra Logging & Tracing** - Distributed tracing with metadata logging
- ✅ **Circuit Breaker Pattern** - Fault tolerance and cascading failure prevention
- ✅ **Retry Mechanism** - Exponential backoff with jitter
- ✅ **Health Checks** - Comprehensive health monitoring
- ✅ **Laravel Integration** - Service provider and facade
- ✅ **CodeIgniter Integration** - Library and helper functions
- ✅ **TypeScript Compatibility** - Compatible with SPLP TypeScript/Bun implementation
- ✅ **Production Ready** - Tested with real Kafka and Cassandra infrastructure
- ✅ **Signal Handling** - Graceful shutdown with cleanup
- ✅ **Environment Configuration** - Flexible configuration via environment variables

## 🏗️ Architecture

### Command Center Flow
```
Publisher → Command Center → Service 1 → Command Center → Service 2
              ↓                             ↓
        Schema Registry              Schema Registry
              ↓                             ↓
       Metadata Logger              Metadata Logger
```

### Available Routes
- `initial-publisher` → `service-1-topic` (Kemensos ke semua layanan verifikasi)
- `service-1-publisher` → `service-2-topic` (Dukcapil ke Kemensos)
- `service-1a-publisher` → `service-2-topic` (BPJS TK ke Kemensos)
- `service-1b-publisher` → `service-2-topic` (BPJS Kesehatan ke Kemensos)
- `service-1c-publisher` → `service-2-topic` (Bank Indonesia ke Kemensos)
- `user-publisher` → `service-1-topic` (User Publisher)
- `calc-publisher` → `service-2-topic` (Calc Publisher)

### Message Flow Example
```
Kemensos (initial-publisher) → Command Center → All Verification Services
                                                      ↓
Dukcapil (service-1-publisher) → Command Center → Kemensos Aggregation
BPJS TK (service-1a-publisher) → Command Center → Kemensos Aggregation
BPJS Kesehatan (service-1b-publisher) → Command Center → Kemensos Aggregation
Bank Indonesia (service-1c-publisher) → Command Center → Kemensos Aggregation
```

## 🔧 Framework Integration

### Laravel

```php
// Register in AppServiceProvider
SplpMessaging::registerHandler('order-processing', new OrderProcessor());

// Use in controller
$response = SplpMessaging::request('order-processing', $orderData);
```

### CodeIgniter

```php
// Load library
$this->load->library('splp_messaging');

// Use in controller
$response = $this->splp_messaging->request('order-processing', $orderData);
```

## 📊 Performance

- **Encryption**: ~1ms per message (AES-256-GCM)
- **Command Center**: 1-59ms routing latency (tested)
- **Throughput**: 10k+ messages/sec (single instance)
- **Cassandra Write**: Async, non-blocking
- **TTL Cleanup**: Automatic, no performance impact
- **Memory Usage**: Optimized for production workloads
- **Connection Pooling**: Efficient Kafka and Cassandra connections

## 🔒 Security

- **Encryption**: AES-256-GCM for all payloads
- **Metadata Only**: Cassandra logs contain NO sensitive data
- **Request ID Visible**: Required for distributed tracing
- **Shared Key**: All services use same ENCRYPTION_KEY
- **Environment Variables**: Sensitive data via environment variables
- **Signal Handling**: Secure graceful shutdown
- **Input Validation**: Comprehensive payload validation

## 🌐 Environment Configuration

### Required Environment Variables

```bash
# Kafka Configuration
KAFKA_BROKERS=10.70.1.23:9092
KAFKA_CLIENT_ID=dukcapil-service
KAFKA_GROUP_ID=service-1-group
KAFKA_REQUEST_TIMEOUT_MS=30000
KAFKA_CONSUMER_TOPIC=service-1-topic
KAFKA_PRODUCER_TOPIC=command-center-inbox

# Cassandra Configuration
CASSANDRA_CONTACT_POINTS=localhost
CASSANDRA_LOCAL_DATACENTER=datacenter1
CASSANDRA_KEYSPACE=service_1_keyspace

# Encryption
ENCRYPTION_KEY=b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d

# Service Configuration
SERVICE_NAME=Dukcapil Service
SERVICE_VERSION=1.0.0
SERVICE_WORKER_NAME=service-1-publisher
```

### Production Deployment

```bash
# Start Command Center
cd example-bun
bun run command-center-config.ts

# Start PHP Service
cd splp-php/examples/basic
php production-listener.php

# Start TypeScript Services
cd example-bun
bun run service_1/index.ts
bun run service_2/index.ts
```

## 📚 Examples

See the `examples/` directory for complete working examples:

- `examples/basic/` - Basic request-reply pattern
  - `production-listener.php` - Production-ready service listener
  - `publisher.php` - Message publisher example
  - `consumer.php` - Message consumer example
- `examples/command-center/` - Command Center routing
- `examples/laravel/` - Laravel integration
- `examples/codeigniter/` - CodeIgniter integration

### Running Examples

```bash
# Basic example
cd examples/basic
php production-listener.php

# With environment variables
KAFKA_BROKERS=10.70.1.23:9092 php production-listener.php
```

## 🧪 Testing

### Unit Tests
```bash
composer test
composer test-coverage
```

### Integration Testing
```bash
# Start infrastructure
docker-compose up -d

# Run integration tests
composer test-integration

# Test with real Kafka/Cassandra
composer test-production
```

### Manual Testing
```bash
# Test routing
cd example-bun
bun run test-service-1-route.ts

# Test consumer
bun run test-service2-consumer.ts
```

## 🔧 Troubleshooting

### Common Issues

1. **"No route found for publisher"**
   - Ensure Command Center is running
   - Check if publisher name matches registered routes
   - Verify Kafka connectivity

2. **"Failed to log metadata"**
   - Check Cassandra connection
   - Verify keyspace exists
   - Check UUID format validation

3. **"Connection refused"**
   - Verify Kafka broker address
   - Check Cassandra contact points
   - Ensure services are running

### Debug Mode
```bash
# Enable debug logging
export DEBUG=true
php production-listener.php
```

## 📖 Documentation

- [Complete Documentation](README.md)
- [API Reference](docs/api.md)
- [Configuration Guide](docs/configuration.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Command Center Guide](../command-center/COMMAND_CENTER_GUIDE.md)
- [Architecture Overview](../example-bun/ARCHITECTURE.md)
- [Parallel Processing](../example-bun/PARALLEL_PROCESSING.md)

## 🚀 Getting Started Checklist

- [ ] Install dependencies: `composer install`
- [ ] Set up environment variables
- [ ] Start Kafka and Cassandra infrastructure
- [ ] Start Command Center: `bun run command-center-config.ts`
- [ ] Start PHP service: `php production-listener.php`
- [ ] Test routing with example publishers
- [ ] Monitor logs for successful message flow

## 🎯 Production Checklist

- [ ] Configure production Kafka brokers
- [ ] Set up Cassandra cluster
- [ ] Generate secure encryption keys
- [ ] Configure environment variables
- [ ] Set up monitoring and alerting
- [ ] Test failover scenarios
- [ ] Configure log rotation
- [ ] Set up health checks

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

MIT License - see LICENSE file for details.

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/splp/php-messaging/issues)
- **Discussions**: [GitHub Discussions](https://github.com/splp/php-messaging/discussions)
- **Documentation**: [GitHub Wiki](https://github.com/splp/php-messaging/wiki)
