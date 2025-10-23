<?php

require_once __DIR__ . '/vendor/autoload.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

echo "🔍 Debug Real Kafka Message Format\n";
echo "==================================\n\n";

try {
    // Configuration
    $config = [
        'kafka' => [
            'brokers' => ['10.70.1.23:9092'],
            'clientId' => 'debug-client',
            'groupId' => 'debug-group',
            'requestTimeoutMs' => 30000,
            'consumerTopic' => 'service-1-topic',
            'producerTopic' => 'command-center-inbox'
        ],
        'cassandra' => [
            'contactPoints' => ['localhost'],
            'localDataCenter' => 'datacenter1',
            'keyspace' => 'debug_keyspace'
        ],
        'encryption' => [
            'key' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7'
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
    echo "🔧 Initializing services...\n";
    $encryptionService->initialize();
    $logger->initialize();
    $kafkaWrapper->initialize();
    echo "✅ Services initialized\n\n";

    // Set up message handler untuk debug
    $kafkaWrapper->setMessageHandler(new class($encryptionService, $logger) implements \Splp\Messaging\Contracts\MessageProcessorInterface {
        private $encryptionService;
        private $logger;
        
        public function __construct($encryptionService, $logger) {
            $this->encryptionService = $encryptionService;
            $this->logger = $logger;
        }
        
        public function processMessage(\Splp\Messaging\Types\EncryptedMessage $message): void {
            echo "📥 Raw Message Debug:\n";
            echo "  Data: " . substr($message->data, 0, 100) . "...\n";
            echo "  IV: " . substr($message->iv, 0, 50) . "...\n";
            echo "  Tag: " . substr($message->tag, 0, 50) . "...\n";
            echo "  Data Length: " . strlen($message->data) . "\n";
            echo "  IV Length: " . strlen($message->iv) . "\n";
            echo "  Tag Length: " . strlen($message->tag) . "\n\n";
            
            try {
                [$requestId, $decryptedData] = $this->encryptionService->decrypt($message);
                echo "✅ Decryption successful!\n";
                echo "  Request ID: {$requestId}\n";
                echo "  Decrypted Data: " . json_encode($decryptedData) . "\n\n";
            } catch (\Exception $e) {
                echo "❌ Decryption failed: " . $e->getMessage() . "\n";
                echo "  Error details: " . $e->getTraceAsString() . "\n\n";
            }
        }
    });

    echo "🔄 Starting debug consumption...\n";
    echo "Press Ctrl+C to stop.\n\n";

    // Start consuming
    $kafkaWrapper->startConsuming([$config['kafka']['consumerTopic']]);

} catch (\Exception $e) {
    echo "❌ Debug error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
