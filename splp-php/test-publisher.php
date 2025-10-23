<?php

require_once __DIR__ . '/vendor/autoload.php';

use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;

echo "📤 Test Publisher ke Kafka\n";
echo "==========================\n\n";

try {
    // Configuration
    $config = [
        'kafka' => [
            'brokers' => ['10.70.1.23:9092'],
            'clientId' => 'test-publisher',
            'groupId' => 'test-group',
            'requestTimeoutMs' => 30000,
            'consumerTopic' => 'service-1-topic',
            'producerTopic' => 'service-1-topic' // Send to same topic for testing
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

    // Test data
    $testData = [
        'registrationId' => 'REG_TEST_' . uniqid(),
        'nik' => '317' . str_pad(rand(1, 999999999999), 13, '0', STR_PAD_LEFT),
        'fullName' => 'John Doe Test',
        'dateOfBirth' => '1990-01-01',
        'address' => 'Jakarta Selatan',
        'assistanceType' => 'Bansos',
        'requestedAmount' => 500000
    ];

    $requestId = 'req_test_' . uniqid();

    echo "📝 Test Data:\n";
    echo "  Request ID: {$requestId}\n";
    echo "  Registration ID: {$testData['registrationId']}\n";
    echo "  NIK: {$testData['nik']}\n";
    echo "  Name: {$testData['fullName']}\n";
    echo "  Address: {$testData['address']}\n";
    echo "  Assistance Type: {$testData['assistanceType']}\n";
    echo "  Requested Amount: {$testData['requestedAmount']}\n\n";

    // Encrypt data
    echo "🔐 Encrypting data...\n";
    $encryptedMessage = $encryptionService->encrypt($testData, $requestId);
    echo "✅ Encryption successful!\n\n";

    // Create message untuk Kafka
    $kafkaMessage = json_encode([
        'data' => $encryptedMessage->data,
        'iv' => $encryptedMessage->iv,
        'tag' => $encryptedMessage->tag
    ]);

    echo "📤 Sending message to Kafka...\n";
    echo "  Topic: {$config['kafka']['producerTopic']}\n";
    echo "  Message Size: " . strlen($kafkaMessage) . " bytes\n\n";

    // Send to Kafka
    $kafkaWrapper->sendMessage($config['kafka']['producerTopic'], $kafkaMessage);

    echo "✅ Message sent successfully to Kafka!\n";
    echo "🎉 Test completed successfully!\n\n";
    echo "💡 Now you can run the production listener to see if it can decrypt and process this message.\n";

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
