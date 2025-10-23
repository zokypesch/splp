# SPLP Laravel 12 Sample Project

Sample project Laravel 12 untuk testing integrasi SPLP-PHP dengan real Kafka integration.

## 📋 Struktur Project

```
splp-php/examples/laravel/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   └── SplpListenerCommand.php    # Artisan command untuk listener
│   │   └── Kernel.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── DukcapilController.php     # Controller untuk testing API
│   │   ├── Middleware/                     # Middleware Laravel
│   │   └── Kernel.php
│   ├── Providers/
│   │   ├── SplpServiceProvider.php        # Service Provider untuk SPLP
│   │   ├── AppServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── EventServiceProvider.php
│   ├── Services/
│   │   └── SplpMessagingService.php       # Service untuk SPLP messaging
│   └── Exceptions/
│       └── Handler.php
├── bootstrap/
│   └── app.php                             # Bootstrap Laravel
├── config/
│   └── splp.php                            # Konfigurasi SPLP
├── routes/
│   └── web.php                             # Routes untuk testing
├── composer.json                           # Dependencies
└── env.example                             # Environment variables example
```

## 🚀 Setup & Installation

### 1. Persiapan Environment

```bash
cd splp-php/examples/laravel

# Copy environment file
cp env.example .env

# Update .env sesuai kebutuhan
# Pastikan konfigurasi SPLP sudah benar:
# - SPLP_KAFKA_BROKERS=10.70.1.23:9092
# - SPLP_ENCRYPTION_KEY=b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7
```

### 2. Install Dependencies

Karena ini sample project, kita akan menggunakan autoloader dari parent directory:

```bash
cd ../../  # Kembali ke splp-php root
# Autoloader sudah ada di vendor/autoload.php
```

### 3. Link ke SPLP Core

Pastikan SPLP core classes dapat diakses:

```bash
# Struktur autoload sudah menggunakan:
# require_once __DIR__ . '/../../vendor/autoload.php'
```

## 🎯 Testing

### Test 1: Jalankan Listener

```bash
cd splp-php

# Simulasi Laravel Artisan command
php examples/laravel/app/Console/Commands/SplpListenerCommand.php
```

Atau buat test script:

```bash
# Buat file test-listener.php
cat > examples/laravel/test-listener.php << 'EOF'
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Simulate Laravel environment
$_ENV['SPLP_KAFKA_BROKERS'] = '10.70.1.23:9092';
$_ENV['SPLP_KAFKA_CLIENT_ID'] = 'dukcapil-service';
$_ENV['SPLP_KAFKA_GROUP_ID'] = 'service-1z-group';
$_ENV['SPLP_KAFKA_CONSUMER_TOPIC'] = 'service-1-topic';
$_ENV['SPLP_KAFKA_PRODUCER_TOPIC'] = 'command-center-inbox';
$_ENV['SPLP_ENCRYPTION_KEY'] = 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7';

// Load SPLP config
$config = require __DIR__ . '/config/splp.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

echo "🏛️  DUKCAPIL - Laravel Sample Project\n";
echo "═══════════════════════════════════════════════════════════\n\n";

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

// Initialize services
$encryptionService = new EncryptionService($encryptionConfig->key);
$logger = new CassandraLogger($cassandraConfig);
$kafkaWrapper = new KafkaWrapper($kafkaConfig, $encryptionService, $logger);

$encryptionService->initialize();
$logger->initialize();
$kafkaWrapper->initialize();

$processor = new Service1MessageProcessor(
    $encryptionService,
    $logger,
    $kafkaConfig,
    $kafkaWrapper,
    $config['service']['workerName']
);

$kafkaWrapper->setMessageHandler($processor);

echo "✅ Laravel SPLP Listener initialized successfully\n";
echo "✅ Ready to listen on topic: {$config['kafka']['consumerTopic']}\n";
echo "✅ Press Ctrl+C to stop\n\n";

// Start listening
$kafkaWrapper->startConsuming([$config['kafka']['consumerTopic']]);
EOF

php examples/laravel/test-listener.php
```

### Test 2: Test Service (Manual)

```bash
# Buat file test-service.php
cat > examples/laravel/test-service.php << 'EOF'
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Simulate Laravel environment
$_ENV['SPLP_KAFKA_BROKERS'] = '10.70.1.23:9092';
$_ENV['SPLP_ENCRYPTION_KEY'] = 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7';

require_once __DIR__ . '/app/Services/SplpMessagingService.php';

use App\Services\SplpMessagingService;

echo "🧪 Testing SPLP Messaging Service\n";
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    $service = new SplpMessagingService();
    
    echo "✅ Service initialized\n\n";
    
    // Test health check
    echo "📊 Health Status:\n";
    $health = $service->getHealthStatus();
    print_r($health);
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
EOF

php examples/laravel/test-service.php
```

## 📚 API Endpoints (dalam Laravel)

### 1. Health Check
```bash
GET /api/splp/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2025-10-22T10:00:00.000000Z",
  "services": {
    "kafka": {...},
    "encryption": {...},
    "cassandra": {...}
  }
}
```

### 2. Send Population Data
```bash
POST /api/splp/population-data
Content-Type: application/json

{
  "registrationId": "REG_123456",
  "nik": "3171234567890123",
  "fullName": "John Doe",
  "dateOfBirth": "1990-01-01",
  "address": "Jakarta Selatan",
  "assistanceType": "Bansos",
  "requestedAmount": 500000
}
```

### 3. Test Send
```bash
POST /api/splp/test-send
```

### 4. Get Config
```bash
GET /api/splp/config
```

## 🔧 Component Details

### SplpServiceProvider
- Registers SPLP services ke Laravel container
- Provides: `splp.kafka`, `splp.service1.processor`, `splp.signal-handler`
- Auto-registers Artisan commands

### SplpMessagingService
- Wrapper service untuk SPLP integration
- Methods:
  - `sendPopulationData(array $data)`
  - `sendMessage(string $topic, array $data)`
  - `sendCommandCenterMessage(string $requestId, array $data)`
  - `getHealthStatus()`
  - `getConfiguration()`

### DukcapilController
- REST API endpoints untuk testing
- Methods:
  - `sendPopulationData(Request $request)`
  - `healthCheck()`
  - `getConfig()`
  - `testSend()`

### SplpListenerCommand
- Artisan command: `php artisan splp:listen`
- Options:
  - `--topic`: Consumer topic
  - `--group-id`: Consumer group ID
  - `--worker-name`: Worker name untuk routing

## 🎓 Usage Examples

### Example 1: Menggunakan Service di Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\SplpMessagingService;
use Illuminate\Http\Request;

class MyController extends Controller
{
    public function __construct(
        private SplpMessagingService $splpService
    ) {}

    public function sendData(Request $request)
    {
        $data = $request->validate([
            'nik' => 'required|string|size:16',
            'fullName' => 'required|string'
        ]);

        $response = $this->splpService->sendPopulationData($data);
        
        return response()->json($response);
    }
}
```

### Example 2: Menggunakan Facade (Coming Soon)

```php
use SPLP;

$response = SPLP::sendMessage('service-1-topic', $data);
```

## 📝 Notes

1. **Ini adalah sample project** - Tidak semua fitur Laravel diimplementasikan
2. **Focus pada SPLP integration** - Controller, Service, dan Command untuk testing SPLP
3. **Real Kafka Integration** - Menggunakan rdkafka extension
4. **Production Ready** - Menggunakan komponen yang sama dengan production-listener.php

## 🐛 Troubleshooting

### Issue: Class not found

**Solution:** Pastikan autoloader sudah include dengan benar:
```php
require_once __DIR__ . '/../../vendor/autoload.php';
```

### Issue: Kafka connection failed

**Solution:** Check Kafka broker:
```bash
telnet 10.70.1.23 9092
```

### Issue: rdkafka extension not found

**Solution:** Install rdkafka:
```bash
brew install librdkafka
pecl install rdkafka
echo 'extension=rdkafka.so' >> /opt/homebrew/etc/php/8.2/php.ini
```

## ✅ Testing Checklist

- [ ] Environment variables configured
- [ ] SPLP service can initialize
- [ ] Kafka connection works
- [ ] Listener can consume messages
- [ ] Publisher can send messages
- [ ] Health check returns OK
- [ ] API endpoints respond correctly

## 🎯 Next Steps

1. Run test-listener.php
2. Send test message from another terminal
3. Verify message is received and processed
4. Check Command Center receives reply

**Sample project Laravel 12 untuk SPLP-PHP siap untuk testing!** 🚀
