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

echo "═══════════════════════════════════════════════════════════\n";
echo "🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil\n";
echo "    Population Data Verification Service (PHP)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Load configuration from environment variables or defaults
$config = loadConfiguration();

// Print configuration (excluding sensitive data)
printConfiguration($config);
echo "\n";

try {
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

    // Set up signal handling for graceful shutdown
    $signalHandler = new SignalHandler();
    $signalHandler->addCleanupCallback(function() use ($kafkaWrapper) {
        echo "🔒 Closing Kafka connections...\n";
        $kafkaWrapper->close();
    });

    // Set message handler
    $kafkaWrapper->setMessageHandler($processor);

    echo "✓ Dukcapil terhubung ke Kafka\n";
    echo "✓ Listening on topic: {$config['kafka']['consumerTopic']} (group: {$config['kafka']['groupId']})\n";
    echo "✓ Producer topic: {$config['kafka']['producerTopic']}\n";
    echo "✓ Siap memverifikasi data kependudukan dan mengirim hasil ke Command Center\n";
    echo "✓ Menunggu pesan dari Command Center...\n\n";

    // Start consuming
    $kafkaWrapper->startConsuming([$config['kafka']['consumerTopic']]);

    echo "Service 1 ({$config['service']['name']}) is running and waiting for messages...\n";
    echo "Press Ctrl+C to exit\n\n";

    // Main loop with signal handling
    while (!$signalHandler->isShutdownRequested()) {
        $signalHandler->processSignals();
        usleep(100000); // 100ms
    }

    echo "\n\nShutting down {$config['service']['name']}...\n";

} catch (\Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

function loadConfiguration(): array
{
    return [
        'kafka' => [
            'brokers' => explode(',', $_ENV['KAFKA_BROKERS'] ?? '10.70.1.23:9092'),
            'clientId' => $_ENV['KAFKA_CLIENT_ID'] ?? 'dukcapil-service',
            'groupId' => $_ENV['KAFKA_GROUP_ID'] ?? 'service-1-group',
            'requestTimeoutMs' => (int)($_ENV['KAFKA_REQUEST_TIMEOUT_MS'] ?? 30000),
            'consumerTopic' => $_ENV['KAFKA_CONSUMER_TOPIC'] ?? 'service-1-topic',
            'producerTopic' => $_ENV['KAFKA_PRODUCER_TOPIC'] ?? 'command-center-inbox'
        ],
        'cassandra' => [
            'contactPoints' => explode(',', $_ENV['CASSANDRA_CONTACT_POINTS'] ?? 'localhost'),
            'localDataCenter' => $_ENV['CASSANDRA_LOCAL_DATACENTER'] ?? 'datacenter1',
            'keyspace' => $_ENV['CASSANDRA_KEYSPACE'] ?? 'service_1_keyspace'
        ],
        'encryption' => [
            'key' => $_ENV['ENCRYPTION_KEY'] ?? 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d'
        ],
        'service' => [
            'name' => $_ENV['SERVICE_NAME'] ?? 'Dukcapil Service',
            'version' => $_ENV['SERVICE_VERSION'] ?? '1.0.0',
            'workerName' => $_ENV['SERVICE_WORKER_NAME'] ?? 'service-1-publisher'
        ]
    ];
}

function printConfiguration(array $config): void
{
    echo "📋 Configuration:\n";
    echo "   Kafka Brokers: " . implode(', ', $config['kafka']['brokers']) . "\n";
    echo "   Client ID: {$config['kafka']['clientId']}\n";
    echo "   Group ID: {$config['kafka']['groupId']}\n";
    echo "   Consumer Topic: {$config['kafka']['consumerTopic']}\n";
    echo "   Producer Topic: {$config['kafka']['producerTopic']}\n";
    echo "   Cassandra Keyspace: {$config['cassandra']['keyspace']}\n";
    echo "   Service Name: {$config['service']['name']}\n";
    echo "   Service Version: {$config['service']['version']}\n";
    echo "   Worker Name: {$config['service']['workerName']}\n";
}
