<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Throwable;

// Try to load Composer autoload if exists
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

$useMock = false;
try {
	// Check if real MessagingClient exists
	if (!class_exists('Splp\\Messaging\\Core\\MessagingClient')) {
		$useMock = true;
	}
} catch (Throwable $e) {
	$useMock = true;
}

// Parse CLI args
$options = [
	'topics:' ,
	'groupId::',
	'clientId::'
];
$cli = getopt('', $options);

$topicsArg = $cli['topics'] ?? 'service-1-topic';
$topics = array_values(array_filter(array_map('trim', explode(',', (string)$topicsArg))));
$clientId = (string)($cli['clientId'] ?? 'dukcapil-service');
$groupId = (string)($cli['groupId'] ?? 'service-1-b-group');

$config = [
	'kafka' => [
		'brokers' => ['10.70.1.23:9092'],
		'clientId' => $clientId,
		'groupId' => $groupId,
	],
	'cassandra' => [
		'contactPoints' => ['localhost'],
		'localDataCenter' => 'datacenter1',
		'keyspace' => 'service_1_keyspace',
	],
	'encryption' => [
		'encryptionKey' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7',
	],
];

if ($useMock) {
	// Minimal mock listener to simulate consumption
	echo "[MOCK] SPLP Basic Listener starting...\n";
	echo " - Brokers: " . implode(',', $config['kafka']['brokers']) . "\n";
	echo " - Topics: " . implode(',', $topics) . "\n";
	echo " - ClientId: {$clientId}\n";
	echo " - GroupId: {$groupId}\n";
	echo "Press Ctrl+C to stop.\n\n";

	// Simulate messages every ~1s
	$counter = 0;
	$serviceMessages = [
		['type' => 'user_registration', 'userId' => 'user_' . random_int(1000, 9999), 'email' => 'user@example.com'],
		['type' => 'order_created', 'orderId' => 'ORD_' . random_int(100000, 999999), 'amount' => random_int(100, 5000)],
		['type' => 'payment_processed', 'paymentId' => 'PAY_' . random_int(100000, 999999), 'status' => 'completed'],
		['type' => 'notification_sent', 'notificationId' => 'NOTIF_' . random_int(100000, 999999), 'channel' => 'email'],
		['type' => 'data_sync', 'syncId' => 'SYNC_' . random_int(100000, 999999), 'records' => random_int(10, 1000)],
	];
	
	while (true) {
		$topic = $topics[$counter % count($topics)];
		$now = date('Y-m-d H:i:s');
		$requestId = 'req_' . uniqid();
		$message = $serviceMessages[$counter % count($serviceMessages)];
		$message['timestamp'] = $now;
		$message['requestId'] = $requestId;
		
		echo "[{$now}] [MOCK] Consumed message from {$topic} | requestId={$requestId} | payload=" . json_encode($message) . "\n";
		$counter++;
		usleep(800_000);
	}
	exit(0);
}

// Real listener with MessagingClient
use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Contracts\RequestHandlerInterface;

class Service1Handler implements RequestHandlerInterface
{
	public function handle(string $requestId, mixed $payload): mixed
	{
		$now = date('Y-m-d H:i:s');
		$messageType = $payload['type'] ?? 'unknown';
		
		echo "[{$now}] Service-1 processing | requestId={$requestId} | type={$messageType}\n";
		echo "   📦 Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
		
		// Process different message types
		$response = match($messageType) {
			'user_registration' => $this->handleUserRegistration($payload),
			'order_created' => $this->handleOrderCreated($payload),
			'payment_processed' => $this->handlePaymentProcessed($payload),
			'notification_sent' => $this->handleNotificationSent($payload),
			'data_sync' => $this->handleDataSync($payload),
			default => $this->handleUnknown($payload)
		};
		
		$response['requestId'] = $requestId;
		$response['processedAt'] = $now;
		
		echo "   ✅ Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
		return $response;
	}
	
	private function handleUserRegistration(array $payload): array
	{
		echo "   👤 Processing user registration: {$payload['userId']}\n";
		return ['status' => 'user_registered', 'userId' => $payload['userId']];
	}
	
	private function handleOrderCreated(array $payload): array
	{
		echo "   🛒 Processing order creation: {$payload['orderId']}\n";
		return ['status' => 'order_processed', 'orderId' => $payload['orderId'], 'amount' => $payload['amount']];
	}
	
	private function handlePaymentProcessed(array $payload): array
	{
		echo "   💳 Processing payment: {$payload['paymentId']}\n";
		return ['status' => 'payment_confirmed', 'paymentId' => $payload['paymentId']];
	}
	
	private function handleNotificationSent(array $payload): array
	{
		echo "   📧 Processing notification: {$payload['notificationId']}\n";
		return ['status' => 'notification_delivered', 'notificationId' => $payload['notificationId']];
	}
	
	private function handleDataSync(array $payload): array
	{
		echo "   🔄 Processing data sync: {$payload['syncId']}\n";
		return ['status' => 'sync_completed', 'syncId' => $payload['syncId'], 'records' => $payload['records']];
	}
	
	private function handleUnknown(array $payload): array
	{
		echo "   ❓ Unknown message type, passing through\n";
		return ['status' => 'unknown_processed', 'original' => $payload];
	}
}

$client = new MessagingClient($config);
$client->initialize();

// Register Service1Handler for all topics
foreach ($topics as $t) {
	$client->registerHandler($t, new Service1Handler());
}

echo "SPLP Basic Listener starting...\n";

echo " - Brokers: " . implode(',', $config['kafka']['brokers']) . "\n";

echo " - Topics: " . implode(',', $topics) . "\n";

echo " - ClientId: {$clientId}\n";

echo " - GroupId: {$groupId}\n";

echo "Press Ctrl+C to stop.\n\n";

$client->startConsuming($topics);
