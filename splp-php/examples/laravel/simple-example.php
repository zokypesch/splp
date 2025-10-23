<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

echo "=== SPLP Laravel Integration Example (Production Listener) ===\n";

echo "\n📋 Cara menjalankan SPLP Listener di Laravel:\n";
echo "1. Publish konfigurasi: php artisan vendor:publish --tag=splp-config\n";
echo "2. Update file config/splp.php sesuai kebutuhan\n";
echo "3. Jalankan listener: php artisan splp:listen\n";
echo "4. Atau dengan custom options:\n";
echo "   php artisan splp:listen --topic=service-1-topic --group-id=my-group --worker-name=my-worker\n\n";

echo "🔧 Manual Setup Example (untuk testing):\n";

try {
    // Load configuration (mirip dengan production-listener.php)
    $config = [
        'kafka' => [
            'brokers' => explode(',', $_ENV['SPLP_KAFKA_BROKERS'] ?? '10.70.1.23:9092'),
            'clientId' => $_ENV['SPLP_KAFKA_CLIENT_ID'] ?? 'dukcapil-service',
            'groupId' => $_ENV['SPLP_KAFKA_GROUP_ID'] ?? 'service-1z-group',
            'requestTimeoutMs' => (int)($_ENV['SPLP_KAFKA_REQUEST_TIMEOUT_MS'] ?? 30000),
            'consumerTopic' => $_ENV['SPLP_KAFKA_CONSUMER_TOPIC'] ?? 'service-1-topic',
            'producerTopic' => $_ENV['SPLP_KAFKA_PRODUCER_TOPIC'] ?? 'command-center-inbox'
        ],
        'cassandra' => [
            'contactPoints' => explode(',', $_ENV['SPLP_CASSANDRA_CONTACT_POINTS'] ?? 'localhost'),
            'localDataCenter' => $_ENV['SPLP_CASSANDRA_LOCAL_DATA_CENTER'] ?? 'datacenter1',
            'keyspace' => $_ENV['SPLP_CASSANDRA_KEYSPACE'] ?? 'service_1_keyspace'
        ],
        'encryption' => [
            'key' => $_ENV['SPLP_ENCRYPTION_KEY'] ?? 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7'
        ],
        'service' => [
            'name' => $_ENV['SPLP_SERVICE_NAME'] ?? 'Dukcapil Service',
            'version' => $_ENV['SPLP_SERVICE_VERSION'] ?? '1.0.0',
            'workerName' => $_ENV['SPLP_SERVICE_WORKER_NAME'] ?? 'service-1-publisher'
        ]
    ];
    
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
        $config['service']['workerName']
    );

    // Set message handler
    $kafkaWrapper->setMessageHandler($processor);

    echo "✓ Services initialized successfully\n";
    echo "✓ Ready to start listening on topic: {$config['kafka']['consumerTopic']}\n";
    echo "✓ Producer topic: {$config['kafka']['producerTopic']}\n";
    echo "✓ Worker name: {$config['service']['workerName']}\n";
    
    // Note: Untuk production, gunakan Artisan command
    echo "\n⚠️  Untuk production, gunakan: php artisan splp:listen\n";
    
} catch (\Exception $e) {
    echo "❌ Error in manual setup: " . $e->getMessage() . "\n";
}

echo "\n📝 Laravel Controller Example:\n";
echo "class DukcapilController extends Controller\n";
echo "{\n";
echo "    public function sendPopulationData(Request \$request)\n";
echo "    {\n";
echo "        \$validated = \$request->validate([\n";
echo "            'registrationId' => 'required|string',\n";
echo "            'nik' => 'required|string|size:16',\n";
echo "            'fullName' => 'required|string',\n";
echo "            'dateOfBirth' => 'required|date',\n";
echo "            'address' => 'required|string'\n";
echo "        ]);\n";
echo "        \n";
echo "        \$response = SplpMessaging::request('service-1-topic', \$validated);\n";
echo "        return response()->json(['success' => true, 'data' => \$response]);\n";
echo "    }\n";
echo "}\n";

echo "\n✅ Laravel integration example completed!\n";
