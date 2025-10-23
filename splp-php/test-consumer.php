<?php

require_once __DIR__ . '/vendor/autoload.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

echo "📥 Test Consumer dari Kafka\n";
echo "===========================\n\n";

try {
    // Configuration dengan consumer group yang berbeda
    $config = [
        'kafka' => [
            'brokers' => ['10.70.1.23:9092'],
            'clientId' => 'test-consumer-new',
            'groupId' => 'test-consumer-group-new', // Group baru
            'requestTimeoutMs' => 30000,
            'consumerTopic' => 'service-1-topic',
            'producerTopic' => 'command-center-inbox'
        ],
        'cassandra' => [
            'contactPoints' => ['localhost'],
            'localDataCenter' => 'datacenter1',
            'keyspace' => 'test_keyspace'
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

    // Set up message handler untuk test
    $kafkaWrapper->setMessageHandler(new class($encryptionService, $logger) implements \Splp\Messaging\Contracts\MessageProcessorInterface {
        private $encryptionService;
        private $logger;
        private $messageCount = 0;
        
        public function __construct($encryptionService, $logger) {
            $this->encryptionService = $encryptionService;
            $this->logger = $logger;
        }
        
        public function processMessage(\Splp\Messaging\Types\EncryptedMessage $message): void {
            $this->messageCount++;
            echo "📥 Message #{$this->messageCount} received!\n";
            
            try {
                [$requestId, $decryptedData] = $this->encryptionService->decrypt($message);
                
                echo "✅ Decryption successful!\n";
                echo "  Request ID: {$requestId}\n";
                echo "  Registration ID: {$decryptedData['registrationId']}\n";
                echo "  NIK: {$decryptedData['nik']}\n";
                echo "  Name: {$decryptedData['fullName']}\n";
                echo "  Address: {$decryptedData['address']}\n";
                echo "  Assistance Type: {$decryptedData['assistanceType']}\n";
                echo "  Requested Amount: {$decryptedData['requestedAmount']}\n\n";
                
                // Simulate processing
                echo "🔄 Processing verification...\n";
                usleep(500000); // 0.5 second delay
                
                echo "✅ Processing completed!\n";
                echo "🎉 Test message processed successfully!\n\n";
                
            } catch (\Exception $e) {
                echo "❌ Error processing message: " . $e->getMessage() . "\n";
                echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
            }
        }
    });

    echo "🔄 Starting test consumption...\n";
    echo "Press Ctrl+C to stop.\n\n";

    // Start consuming
    $kafkaWrapper->startConsuming([$config['kafka']['consumerTopic']]);

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
