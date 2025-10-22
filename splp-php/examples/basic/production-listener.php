<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Contracts\RequestHandlerInterface;
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
	'topics:' ,
	'groupId::',
	'clientId::',
	'brokers::',
	'keyspace::'
];
$cli = getopt('', $options);

$topicsArg = $cli['topics'] ?? 'service-1-topic';
$topics = array_values(array_filter(array_map('trim', explode(',', (string)$topicsArg))));
$clientId = (string)($cli['clientId'] ?? 'dukcapil-service');
$groupId = (string)($cli['groupId'] ?? 'service-1-b-group');
$brokers = (string)($cli['brokers'] ?? '10.70.1.23:9092');
$keyspace = (string)($cli['keyspace'] ?? 'service_1_keyspace');

$config = [
	'kafka' => [
		'brokers' => array_map('trim', explode(',', $brokers)),
		'clientId' => $clientId,
		'groupId' => $groupId,
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
 * Production Service Handler
 */
class ProductionServiceHandler implements RequestHandlerInterface
{
	public function handle(string $requestId, mixed $payload): mixed
	{
		$now = date('Y-m-d H:i:s');
		$messageType = $payload['type'] ?? 'unknown';
		
		echo "[{$now}] 🔄 Processing message | requestId={$requestId} | type={$messageType}\n";
		echo "   📦 Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
		
		try {
			// Process different message types
			$response = match($messageType) {
				'user_registration' => $this->handleUserRegistration($payload),
				'order_created' => $this->handleOrderCreated($payload),
				'payment_processed' => $this->handlePaymentProcessed($payload),
				'notification_sent' => $this->handleNotificationSent($payload),
				'data_sync' => $this->handleDataSync($payload),
				'dukcapil_request' => $this->handleDukcapilRequest($payload),
				default => $this->handleUnknown($payload)
			};
			
			$response['requestId'] = $requestId;
			$response['processedAt'] = $now;
			$response['status'] = 'success';
			
			echo "   ✅ Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
			return $response;
			
		} catch (Throwable $e) {
			$errorResponse = [
				'requestId' => $requestId,
				'processedAt' => $now,
				'status' => 'error',
				'error' => $e->getMessage(),
				'type' => get_class($e)
			];
			
			echo "   ❌ Error: " . $e->getMessage() . "\n";
			echo "   📋 Error Response: " . json_encode($errorResponse, JSON_PRETTY_PRINT) . "\n\n";
			
			return $errorResponse;
		}
	}
	
	private function handleUserRegistration(array $payload): array
	{
		echo "   👤 Processing user registration: {$payload['userId']}\n";
		
		// Simulate processing time
		usleep(100000); // 100ms
		
		return [
			'status' => 'user_registered',
			'userId' => $payload['userId'],
			'email' => $payload['email'] ?? null,
			'registrationDate' => date('Y-m-d H:i:s')
		];
	}
	
	private function handleOrderCreated(array $payload): array
	{
		echo "   🛒 Processing order creation: {$payload['orderId']}\n";
		
		// Simulate processing time
		usleep(150000); // 150ms
		
		return [
			'status' => 'order_processed',
			'orderId' => $payload['orderId'],
			'amount' => $payload['amount'] ?? 0,
			'currency' => $payload['currency'] ?? 'USD',
			'processedAt' => date('Y-m-d H:i:s')
		];
	}
	
	private function handlePaymentProcessed(array $payload): array
	{
		echo "   💳 Processing payment: {$payload['paymentId']}\n";
		
		// Simulate processing time
		usleep(200000); // 200ms
		
		return [
			'status' => 'payment_confirmed',
			'paymentId' => $payload['paymentId'],
			'orderId' => $payload['orderId'] ?? null,
			'amount' => $payload['amount'] ?? 0,
			'method' => $payload['method'] ?? 'unknown'
		];
	}
	
	private function handleNotificationSent(array $payload): array
	{
		echo "   📧 Processing notification: {$payload['notificationId']}\n";
		
		// Simulate processing time
		usleep(50000); // 50ms
		
		return [
			'status' => 'notification_delivered',
			'notificationId' => $payload['notificationId'],
			'channel' => $payload['channel'] ?? 'unknown',
			'recipient' => $payload['recipient'] ?? null
		];
	}
	
	private function handleDataSync(array $payload): array
	{
		echo "   🔄 Processing data sync: {$payload['syncId']}\n";
		
		// Simulate processing time
		usleep(300000); // 300ms
		
		return [
			'status' => 'sync_completed',
			'syncId' => $payload['syncId'],
			'records' => $payload['records'] ?? 0,
			'source' => $payload['source'] ?? 'unknown',
			'target' => $payload['target'] ?? 'unknown'
		];
	}
	
	private function handleDukcapilRequest(array $payload): array
	{
		echo "   🏛️ Processing Dukcapil request: {$payload['requestType']}\n";
		
		// Simulate processing time
		usleep(250000); // 250ms
		
		return [
			'status' => 'dukcapil_processed',
			'requestType' => $payload['requestType'],
			'nik' => $payload['nik'] ?? null,
			'responseCode' => '200',
			'processedAt' => date('Y-m-d H:i:s')
		];
	}
	
	private function handleUnknown(array $payload): array
	{
		echo "   ❓ Unknown message type, passing through\n";
		
		return [
			'status' => 'unknown_processed',
			'original' => $payload,
			'note' => 'Message type not recognized'
		];
	}
}

// Initialize production listener
try {
	echo "🚀 SPLP Production Listener Starting...\n";
	echo "📡 Configuration:\n";
	echo "   - Brokers: " . implode(', ', $config['kafka']['brokers']) . "\n";
	echo "   - Topics: " . implode(', ', $topics) . "\n";
	echo "   - ClientId: {$clientId}\n";
	echo "   - GroupId: {$groupId}\n";
	echo "   - Keyspace: {$keyspace}\n";
	echo "   - Encryption: Enabled\n";
	echo "   - Mode: " . ($useMock ? "Mock (Testing)" : "Production") . "\n\n";
	
	if ($useMock) {
		$client = new \Splp\Messaging\Core\MockMessagingClient($config);
	} else {
		$client = new MessagingClient($config);
	}
	$client->initialize();
	
	echo "✅ MessagingClient initialized successfully\n";
	
	// Register handler for all topics
	$handler = new ProductionServiceHandler();
	foreach ($topics as $topic) {
		$client->registerHandler($topic, $handler);
		echo "📝 Registered handler for topic: {$topic}\n";
	}
	
	echo "\n🔄 Starting message consumption...\n";
	echo "Press Ctrl+C to stop.\n\n";
	
	$client->startConsuming($topics);
	
} catch (Throwable $e) {
	echo "❌ Fatal Error: " . $e->getMessage() . "\n";
	echo "📋 Error Details:\n";
	echo "   - Type: " . get_class($e) . "\n";
	echo "   - File: " . $e->getFile() . ":" . $e->getLine() . "\n";
	echo "   - Trace: " . $e->getTraceAsString() . "\n";
	
	echo "\n💡 Troubleshooting:\n";
	echo "   1. Check if Kafka broker is running at: " . implode(', ', $config['kafka']['brokers']) . "\n";
	echo "   2. Verify Cassandra is running at: localhost:9042\n";
	echo "   3. Ensure all dependencies are installed:\n";
	echo "      - pecl install rdkafka cassandra\n";
	echo "      - composer require rdkafka/rdkafka datastax/php-driver\n";
	echo "   4. Check network connectivity to Kafka broker\n";
	
	exit(1);
}
