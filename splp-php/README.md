# SPLP PHP Messaging Library

A complete PHP ecosystem for implementing Request-Reply patterns over Kafka with Command Center routing, encryption, and distributed tracing.

## Features

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

## Installation

### Via Composer

```bash
composer require splp/php-messaging
```

### Laravel Integration

1. **Register Service Provider** (Laravel 5.5+ auto-discovery):

```php
// config/app.php
'providers' => [
    // ...
    Splp\Messaging\Laravel\SplpServiceProvider::class,
],
```

2. **Publish Configuration**:

```bash
php artisan vendor:publish --provider="Splp\Messaging\Laravel\SplpServiceProvider"
```

3. **Configure Environment**:

```env
# .env
SPLP_KAFKA_BROKERS=localhost:9092
SPLP_KAFKA_CLIENT_ID=my-app
SPLP_CASSANDRA_CONTACT_POINTS=localhost
SPLP_CASSANDRA_KEYSPACE=my_keyspace
SPLP_ENCRYPTION_KEY=your-64-char-hex-key
```

### CodeIgniter Integration

1. **Load Library**:

```php
// application/config/autoload.php
$autoload['libraries'] = array('splp_messaging');
```

2. **Configure**:

```php
// application/config/splp.php
<?php
return [
    'kafka' => [
        'brokers' => ['localhost:9092'],
        'clientId' => 'my-app',
        'groupId' => 'my-app-group'
    ],
    'cassandra' => [
        'contactPoints' => ['localhost'],
        'localDataCenter' => 'datacenter1',
        'keyspace' => 'my_keyspace'
    ],
    'encryption' => [
        'encryptionKey' => 'your-64-char-hex-key'
    ]
];
```

## Quick Start

### Basic Usage

```php
<?php

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Contracts\RequestHandlerInterface;

// Configuration
$config = [
    'kafka' => [
        'brokers' => ['localhost:9092'],
        'clientId' => 'my-client',
        'groupId' => 'my-group'
    ],
    'cassandra' => [
        'contactPoints' => ['localhost'],
        'localDataCenter' => 'datacenter1',
        'keyspace' => 'my_keyspace'
    ],
    'encryption' => [
        'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
    ]
];

// Create messaging client
$client = new MessagingClient($config);

// Initialize (single line setup)
$client->initialize();

// Register handler
$client->registerHandler('calculate', new class implements RequestHandlerInterface {
    public function handle(string $requestId, mixed $payload): mixed
    {
        return ['result' => $payload['a'] + $payload['b']];
    }
});

// Start consuming
$client->startConsuming(['calculate']);

// Send request
$response = $client->request('calculate', ['a' => 10, 'b' => 5]);
echo "Result: " . $response['result']; // Result: 15
```

### Laravel Usage

```php
<?php

use Splp\Messaging\Facades\SplpMessaging;

// In your controller or service
class OrderController extends Controller
{
    public function processOrder(Request $request)
    {
        // Send request to order processing service
        $response = SplpMessaging::request('order-processing', [
            'orderId' => $request->order_id,
            'userId' => $request->user_id,
            'items' => $request->items
        ]);

        return response()->json($response);
    }
}

// Register handler in service provider
class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        SplpMessaging::registerHandler('order-processing', new OrderProcessor());
    }
}
```

### CodeIgniter Usage

```php
<?php

class Order_controller extends CI_Controller
{
    public function process_order()
    {
        $this->load->library('splp_messaging');
        
        // Send request
        $response = $this->splp_messaging->request('order-processing', [
            'orderId' => $this->input->post('order_id'),
            'userId' => $this->input->post('user_id'),
            'items' => $this->input->post('items')
        ]);
        
        $this->output->set_content_type('application/json');
        $this->output->set_output(json_encode($response));
    }
}
```

## Command Center Usage

### Setup Command Center

```php
<?php

use Splp\Messaging\CommandCenter\CommandCenter;

$config = [
    'kafka' => [
        'brokers' => ['localhost:9092'],
        'clientId' => 'command-center',
        'groupId' => 'command-center-group'
    ],
    'cassandra' => [
        'contactPoints' => ['localhost'],
        'localDataCenter' => 'datacenter1',
        'keyspace' => 'command_center'
    ],
    'encryption' => [
        'encryptionKey' => 'your-64-char-hex-key'
    ],
    'commandCenter' => [
        'inboxTopic' => 'command-center-inbox',
        'enableAutoRouting' => true,
        'defaultTimeout' => 30000
    ]
];

$commandCenter = new CommandCenter($config);
$commandCenter->initialize();

// Register routes
$commandCenter->registerRoute([
    'routeId' => 'route-001',
    'sourcePublisher' => 'my-publisher',
    'targetTopic' => 'my-worker-topic',
    'serviceInfo' => [
        'serviceName' => 'my-service',
        'version' => '1.0.0',
        'description' => 'My awesome service'
    ],
    'enabled' => true,
    'createdAt' => new DateTime(),
    'updatedAt' => new DateTime()
]);

// Start routing
$commandCenter->start();
```

### Send Messages via Command Center

```php
<?php

use Splp\Messaging\Core\Kafka\KafkaWrapper;
use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Core\Utils\RequestIdGenerator;

// Send to Command Center inbox
$kafka = new KafkaWrapper(['brokers' => ['localhost:9092'], 'clientId' => 'publisher']);
$encryption = new EncryptionService(['encryptionKey' => 'your-key']);

$kafka->connectProducer();

$message = [
    'request_id' => RequestIdGenerator::generate(),
    'worker_name' => 'my-publisher', // Routes based on this
    'data' => $encryption->encryptPayload(['test' => 'data'], 'request-id')['data'],
    'iv' => $encryption->encryptPayload(['test' => 'data'], 'request-id')['iv'],
    'tag' => $encryption->encryptPayload(['test' => 'data'], 'request-id')['tag']
];

$kafka->sendMessage('command-center-inbox', json_encode($message));
```

## Advanced Features

### Circuit Breaker

```php
<?php

use Splp\Messaging\Core\Utils\CircuitBreaker;

$circuitBreaker = new CircuitBreaker([
    'failureThreshold' => 5,
    'recoveryTimeout' => 30000,
    'monitoringPeriod' => 60000
]);

try {
    $result = $circuitBreaker->execute(function () {
        // Your risky operation
        return $this->callExternalService();
    });
} catch (MessagingException $e) {
    // Handle circuit breaker open
    echo "Service unavailable: " . $e->getMessage();
}
```

### Retry Mechanism

```php
<?php

use Splp\Messaging\Core\Utils\RetryManager;

$retryManager = new RetryManager([
    'maxAttempts' => 3,
    'baseDelay' => 1000,
    'maxDelay' => 10000,
    'backoffMultiplier' => 2,
    'jitter' => true
]);

$result = $retryManager->execute(function () {
    return $this->callExternalService();
}, function ($error) {
    // Only retry on specific errors
    return $error instanceof ConnectionException;
});
```

### Health Checks

```php
<?php

use Splp\Messaging\Core\Monitoring\HealthChecker;

$healthChecker = new HealthChecker([
    'checkInterval' => 30000,
    'timeout' => 5000,
    'criticalChecks' => ['kafka', 'cassandra']
]);

// Register health checks
$healthChecker->registerCheck('kafka', function () {
    return $this->kafka->healthCheck();
});

$healthChecker->registerCheck('cassandra', function () {
    return $this->logger->healthCheck();
});

// Get health status
$health = $healthChecker->getHealthStatus();
echo "Status: " . $health['status']; // healthy, unhealthy, degraded
```

## Configuration

### Complete Configuration Example

```php
<?php

$config = [
    'kafka' => [
        'brokers' => ['localhost:9092'],
        'clientId' => 'my-app',
        'groupId' => 'my-app-group'
    ],
    'cassandra' => [
        'contactPoints' => ['localhost'],
        'localDataCenter' => 'datacenter1',
        'keyspace' => 'my_keyspace'
    ],
    'encryption' => [
        'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
    ],
    'commandCenter' => [
        'inboxTopic' => 'command-center-inbox',
        'enableAutoRouting' => true,
        'defaultTimeout' => 30000
    ]
];
```

### Environment Variables

```env
# Kafka Configuration
SPLP_KAFKA_BROKERS=localhost:9092
SPLP_KAFKA_CLIENT_ID=my-app
SPLP_KAFKA_GROUP_ID=my-app-group

# Cassandra Configuration
SPLP_CASSANDRA_CONTACT_POINTS=localhost
SPLP_CASSANDRA_LOCAL_DATA_CENTER=datacenter1
SPLP_CASSANDRA_KEYSPACE=my_keyspace

# Encryption
SPLP_ENCRYPTION_KEY=your-64-char-hex-key

# Command Center
SPLP_COMMAND_CENTER_INBOX_TOPIC=command-center-inbox
SPLP_COMMAND_CENTER_ENABLE_AUTO_ROUTING=true
SPLP_COMMAND_CENTER_DEFAULT_TIMEOUT=30000
```

## Testing

### Unit Tests

```bash
composer test
```

### Integration Tests

```bash
composer test-integration
```

### Code Coverage

```bash
composer test-coverage
```

## Performance

- **Encryption**: ~1ms per message
- **Command Center**: 1-5ms routing latency
- **Throughput**: 10k+ messages/sec (single instance)
- **Cassandra Write**: Async, non-blocking
- **TTL Cleanup**: Automatic, no performance impact

## Security

- **Encryption**: AES-256-GCM for all payloads
- **Metadata Only**: Cassandra logs contain NO sensitive data
- **Request ID Visible**: Required for distributed tracing
- **Shared Key**: All services use same ENCRYPTION_KEY

## Data Privacy

### What's Logged (Cassandra):
- ✅ request_id
- ✅ worker_name
- ✅ timestamp
- ✅ routing info (topics, route_id)
- ✅ success/failure status
- ✅ processing time

### What's NOT Logged:
- ❌ Payload data
- ❌ Business logic details
- ❌ User information
- ❌ Encrypted message content

## Troubleshooting

### Common Issues

1. **Connection Failed**
   ```bash
   # Check Kafka is running
   docker ps | grep kafka
   
   # Check Cassandra is running
   docker ps | grep cassandra
   ```

2. **Decryption Errors**
   - Ensure all services use same ENCRYPTION_KEY
   - Check key is 64 hex characters (32 bytes)

3. **Route Not Found**
   - Verify route registered in Schema Registry
   - Check worker_name matches route config

4. **Cassandra Connection Failed**
   - Wait for Cassandra to be healthy
   - Check keyspace exists

### Debug Mode

```php
<?php

// Enable debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set log level
$config['logging']['level'] = 'debug';
```

## Examples

See the `examples/` directory for complete working examples:

- `examples/basic/` - Basic request-reply pattern
- `examples/command-center/` - Command Center routing
- `examples/laravel/` - Laravel integration
- `examples/codeigniter/` - CodeIgniter integration

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

MIT License - see LICENSE file for details.

## Support

- **Documentation**: [GitHub Wiki](https://github.com/splp/php-messaging/wiki)
- **Issues**: [GitHub Issues](https://github.com/splp/php-messaging/issues)
- **Discussions**: [GitHub Discussions](https://github.com/splp/php-messaging/discussions)

## Changelog

### v1.0.0
- Initial release
- Complete Kafka Request-Reply implementation
- Command Center routing
- AES-256-GCM encryption
- Cassandra logging & tracing
- Laravel & CodeIgniter integration
- Circuit breaker & retry mechanisms
- Health checks & monitoring
