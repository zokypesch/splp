<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Simulate Laravel env() function
if (!function_exists('env')) {
    function env($key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
}

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

echo "🏛️  DUKCAPIL - Laravel Sample Project Listener\n";
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
