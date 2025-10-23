# SPLP Laravel Integration

Integrasi SPLP-PHP dengan Laravel framework menggunakan production listener yang sama dengan `production-listener.php`.

## 🚀 Quick Start

### 1. Install Dependencies

```bash
# Install rdkafka extension (untuk real Kafka integration)
brew install librdkafka
pecl install rdkafka

# Tambahkan ke php.ini
echo 'extension=rdkafka.so' >> /usr/local/etc/php/8.2/php.ini
```

### 2. Register Service Provider

Tambahkan ke `config/app.php`:

```php
'providers' => [
    // ...
    Splp\Messaging\Laravel\SplpServiceProvider::class,
],
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --tag=splp-config
```

### 4. Update Environment Variables

Tambahkan ke `.env`:

```env
# Kafka Configuration
SPLP_KAFKA_BROKERS=10.70.1.23:9092
SPLP_KAFKA_CLIENT_ID=dukcapil-service
SPLP_KAFKA_GROUP_ID=service-1z-group
SPLP_KAFKA_CONSUMER_TOPIC=service-1-topic
SPLP_KAFKA_PRODUCER_TOPIC=command-center-inbox

# Cassandra Configuration
SPLP_CASSANDRA_CONTACT_POINTS=localhost
SPLP_CASSANDRA_LOCAL_DATA_CENTER=datacenter1
SPLP_CASSANDRA_KEYSPACE=service_1_keyspace

# Encryption
SPLP_ENCRYPTION_KEY=b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7

# Service Configuration
SPLP_SERVICE_NAME=Dukcapil Service
SPLP_SERVICE_VERSION=1.0.0
SPLP_SERVICE_WORKER_NAME=service-1-publisher
```

### 5. Run Listener

```bash
# Basic usage
php artisan splp:listen

# With custom options
php artisan splp:listen --topic=service-1-topic --group-id=my-group --worker-name=my-worker
```

## 📋 Available Commands

### `splp:listen`

Menjalankan SPLP Listener untuk memproses pesan dari Kafka.

**Options:**
- `--topic`: Topic yang akan di-listen (default: service-1-topic)
- `--group-id`: Consumer group ID (default: service-1z-group)
- `--worker-name`: Worker name untuk routing (default: service-1-publisher)

**Example:**
```bash
php artisan splp:listen --topic=service-1-topic --group-id=my-group --worker-name=my-worker
```

## 🔧 Services Available

### 1. KafkaWrapper (`splp.kafka`)

Production-ready Kafka wrapper dengan real Kafka integration.

```php
$kafkaWrapper = app('splp.kafka');
$healthStatus = $kafkaWrapper->getHealthStatus();
```

### 2. Service1MessageProcessor (`splp.service1.processor`)

Message processor untuk Service 1 (Dukcapil).

```php
$processor = app('splp.service1.processor');
```

### 3. SignalHandler (`splp.signal-handler`)

Handler untuk graceful shutdown.

```php
$signalHandler = app('splp.signal-handler');
```

## 📝 Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Splp\Messaging\Laravel\SplpMessaging;

class DukcapilController extends Controller
{
    public function sendPopulationData(Request $request)
    {
        try {
            $validated = $request->validate([
                'registrationId' => 'required|string',
                'nik' => 'required|string|size:16',
                'fullName' => 'required|string',
                'dateOfBirth' => 'required|date',
                'address' => 'required|string',
                'assistanceType' => 'nullable|string',
                'requestedAmount' => 'nullable|numeric'
            ]);

            $response = SplpMessaging::request('service-1-topic', $validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dikirim ke Dukcapil',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function healthCheck()
    {
        try {
            $kafkaWrapper = app('splp.kafka');
            $healthStatus = $kafkaWrapper->getHealthStatus();
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'services' => $healthStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

## 🔄 Message Flow

1. **Command Center** mengirim pesan ke `service-1-topic`
2. **Laravel Listener** (`php artisan splp:listen`) menerima pesan
3. **Service1MessageProcessor** memproses data kependudukan
4. **Hasil verifikasi** dikirim kembali ke `command-center-inbox`
5. **Command Center** meroute ke service berikutnya

## 🏗️ Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Command Center │───▶│  service-1-topic │───▶│ Laravel Listener│
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                          │
                                                          ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Command Center │◀───│command-center-   │◀───│Service1Message │
│                 │    │inbox             │    │Processor        │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## 🛠️ Development

### Manual Setup (untuk testing)

```php
use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

// Create configurations
$kafkaConfig = new KafkaConfig(
    brokers: ['10.70.1.23:9092'],
    clientId: 'dukcapil-service',
    groupId: 'service-1z-group',
    requestTimeoutMs: 30000,
    consumerTopic: 'service-1-topic',
    producerTopic: 'command-center-inbox'
);

$cassandraConfig = new CassandraConfig(
    contactPoints: ['localhost'],
    localDataCenter: 'datacenter1',
    keyspace: 'service_1_keyspace'
);

$encryptionConfig = new EncryptionConfig('your-encryption-key');

// Create services
$encryptionService = new EncryptionService($encryptionConfig->key);
$logger = new CassandraLogger($cassandraConfig);
$kafkaWrapper = new KafkaWrapper($kafkaConfig, $encryptionService, $logger);

// Initialize and start listening
$encryptionService->initialize();
$logger->initialize();
$kafkaWrapper->initialize();

$processor = new Service1MessageProcessor(
    $encryptionService,
    $logger,
    $kafkaConfig,
    $kafkaWrapper,
    'service-1-publisher'
);

$kafkaWrapper->setMessageHandler($processor);
$kafkaWrapper->startConsuming(['service-1-topic']);
```

## 🔍 Monitoring

### Health Check

```bash
# Check health status
curl http://your-app.com/api/dukcapil/health
```

### Logs

Listener akan menampilkan log real-time:

```
📥 Received message from topic: service-1-topic, partition: 0, offset: 123
🔄 Memverifikasi data kependudukan...
✅ Status NIK: VALID
✅ Data Cocok: YA
✅ Jumlah Anggota Keluarga: 3
✅ Alamat Terverifikasi: YA
📤 Mengirim hasil verifikasi ke Command Center...
✅ Hasil verifikasi berhasil dikirim ke Command Center
```

## 🚨 Troubleshooting

### 1. rdkafka Extension Not Found

```bash
# Install librdkafka
brew install librdkafka

# Install rdkafka extension
pecl install rdkafka

# Add to php.ini
echo 'extension=rdkafka.so' >> /usr/local/etc/php/8.2/php.ini
```

### 2. Kafka Connection Issues

```bash
# Test Kafka connectivity
telnet 10.70.1.23 9092
```

### 3. Decryption Errors

Pastikan encryption key sama antara sender dan receiver.

## 📚 Related Files

- `src/Laravel/ServiceProvider.php` - Laravel Service Provider
- `src/Laravel/Commands/SplpListenerCommand.php` - Artisan Command
- `src/Laravel/config/splp.php` - Configuration file
- `examples/laravel/index.php` - Integration example
- `examples/basic/production-listener.php` - Standalone listener
