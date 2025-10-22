<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\Core\MessagingClient;
use Throwable;

// Load Composer autoload
$autoloadPaths = [
	__DIR__ . '/../../vendor/autoload.php',
	__DIR__ . '/../../../vendor/autoload.php',
];
foreach ($autoloadPaths as $autoload) {
	if (file_exists($autoload)) {
		require_once $autoload;
		break;
	}
}

// Check if MessagingClient exists, fallback to MockMessagingClient
$useMock = false;
if (!class_exists('Splp\\Messaging\\Core\\MessagingClient')) {
	echo "⚠️  MessagingClient not found, using MockMessagingClient for testing\n";
	echo "💡 For production, install dependencies:\n";
	echo "   composer require rdkafka/rdkafka datastax/php-driver\n";
	echo "   pecl install rdkafka cassandra\n\n";
	$useMock = true;
}

// Parse CLI arguments
$options = [
	'topic:',
	'count::',
	'brokers::',
	'keyspace::',
	'custom:',
	'message-type:'
];
$cli = getopt('', $options);

$topic = (string)($cli['topic'] ?? 'service-1-topic');
$count = (int)($cli['count'] ?? 1);
$brokers = (string)($cli['brokers'] ?? '10.70.1.23:9092');
$keyspace = (string)($cli['keyspace'] ?? 'service_1_keyspace');
$customMessage = $cli['custom'] ?? null;
$messageType = (string)($cli['message-type'] ?? 'user_registration');

$config = [
	'kafka' => [
		'brokers' => array_map('trim', explode(',', $brokers)),
		'clientId' => 'production-publisher',
		'groupId' => 'production-publisher-group',
	],
	'cassandra' => [
		'contactPoints' => ['localhost'],
		'localDataCenter' => 'datacenter1',
		'keyspace' => $keyspace,
	],
	'encryption' => [
		'encryptionKey' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7',
	],
];

/**
 * Production Publisher
 */
class ProductionPublisher
{
	private $client;
	private array $messageTypes = [
		'user_registration',
		'order_created', 
		'payment_processed',
		'notification_sent',
		'data_sync',
		'dukcapil_request'
	];

	public function __construct($client)
	{
		$this->client = $client;
	}

	public function publishTestMessages(string $topic, int $count): void
	{
		echo "🚀 Production Publisher - Publishing {$count} test messages\n";
		echo "📡 Target: {$topic}\n";
		echo "🔗 Kafka: " . implode(', ', $this->client->getConfig()['kafka']['brokers']) . "\n\n";

		for ($i = 1; $i <= $count; $i++) {
			$messageType = $this->messageTypes[($i - 1) % count($this->messageTypes)];
			$message = $this->generateMessage($messageType, $i);
			
			echo "📤 Publishing message {$i}/{$count}\n";
			echo "   Type: {$messageType}\n";
			echo "   Payload: " . json_encode($message, JSON_PRETTY_PRINT) . "\n";
			
			try {
				$response = $this->client->request($topic, $message);
				echo "   ✅ Published successfully\n";
				echo "   📥 Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
			} catch (Throwable $e) {
				echo "   ❌ Failed to publish: " . $e->getMessage() . "\n\n";
			}
			
			// Wait between messages
			usleep(500000); // 500ms
		}
		
		echo "🎉 All messages published!\n";
	}

	public function publishCustomMessage(string $topic, array $message): void
	{
		echo "📤 Publishing custom message\n";
		echo "   Topic: {$topic}\n";
		echo "   Payload: " . json_encode($message, JSON_PRETTY_PRINT) . "\n";
		
		try {
			$response = $this->client->request($topic, $message);
			echo "   ✅ Custom message published successfully\n";
			echo "   📥 Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
		} catch (Throwable $e) {
			echo "   ❌ Failed to publish custom message: " . $e->getMessage() . "\n\n";
		}
	}

	private function generateMessage(string $type, int $sequence): array
	{
		$baseMessage = [
			'type' => $type,
			'sequence' => $sequence,
			'timestamp' => date('Y-m-d H:i:s'),
			'requestId' => 'pub_' . uniqid() . '_' . $sequence
		];

		return match($type) {
			'user_registration' => array_merge($baseMessage, [
				'userId' => 'user_' . random_int(1000, 9999),
				'email' => 'user' . $sequence . '@example.com',
				'name' => 'User ' . $sequence
			]),
			'order_created' => array_merge($baseMessage, [
				'orderId' => 'ORD_' . random_int(100000, 999999),
				'amount' => random_int(100, 5000),
				'currency' => 'USD',
				'items' => ['item1', 'item2', 'item3']
			]),
			'payment_processed' => array_merge($baseMessage, [
				'paymentId' => 'PAY_' . random_int(100000, 999999),
				'orderId' => 'ORD_' . random_int(100000, 999999),
				'amount' => random_int(100, 5000),
				'status' => 'completed',
				'method' => 'credit_card'
			]),
			'notification_sent' => array_merge($baseMessage, [
				'notificationId' => 'NOTIF_' . random_int(100000, 999999),
				'channel' => 'email',
				'recipient' => 'user' . $sequence . '@example.com',
				'subject' => 'Test Notification ' . $sequence
			]),
			'data_sync' => array_merge($baseMessage, [
				'syncId' => 'SYNC_' . random_int(100000, 999999),
				'records' => random_int(10, 1000),
				'source' => 'database',
				'target' => 'cache'
			]),
			'dukcapil_request' => array_merge($baseMessage, [
				'requestType' => 'nik_validation',
				'nik' => '1234567890123456',
				'requestData' => [
					'name' => 'John Doe',
					'birthDate' => '1990-01-01'
				]
			]),
			default => $baseMessage
		};
	}
}

// Initialize production publisher
try {
	echo "🚀 SPLP Production Publisher Starting...\n";
	echo "📡 Configuration:\n";
	echo "   - Brokers: " . implode(', ', $config['kafka']['brokers']) . "\n";
	echo "   - Topic: {$topic}\n";
	echo "   - Keyspace: {$keyspace}\n";
	echo "   - Encryption: Enabled\n";
	echo "   - Mode: " . ($useMock ? "Mock (Testing)" : "Production") . "\n\n";
	
	if ($useMock) {
		$client = new \Splp\Messaging\Core\MockMessagingClient($config);
	} else {
		$client = new MessagingClient($config);
	}
	$client->initialize();
	
	echo "✅ MessagingClient initialized successfully\n\n";
	
	$publisher = new ProductionPublisher($client);
	
	if ($customMessage) {
		// Publish custom message
		$message = json_decode($customMessage, true);
		if ($message) {
			$publisher->publishCustomMessage($topic, $message);
		} else {
			echo "❌ Invalid JSON in custom message\n";
			exit(1);
		}
	} else {
		// Publish test messages
		$publisher->publishTestMessages($topic, $count);
	}
	
} catch (Throwable $e) {
	echo "❌ Fatal Error: " . $e->getMessage() . "\n";
	echo "📋 Error Details:\n";
	echo "   - Type: " . get_class($e) . "\n";
	echo "   - File: " . $e->getFile() . ":" . $e->getLine() . "\n";
	
	echo "\n💡 Troubleshooting:\n";
	echo "   1. Check if Kafka broker is running at: " . implode(', ', $config['kafka']['brokers']) . "\n";
	echo "   2. Verify Cassandra is running at: localhost:9042\n";
	echo "   3. Ensure all dependencies are installed:\n";
	echo "      - pecl install rdkafka cassandra\n";
	echo "      - composer require rdkafka/rdkafka datastax/php-driver\n";
	echo "   4. Check network connectivity to Kafka broker\n";
	
	exit(1);
}
