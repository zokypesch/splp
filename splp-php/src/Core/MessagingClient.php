<?php

declare(strict_types=1);

namespace Splp\Messaging\Core;

use Splp\Messaging\Contracts\MessagingClientInterface;
use Splp\Messaging\Contracts\RequestHandlerInterface;

/**
 * Production MessagingClient
 * 
 * This is a placeholder implementation. In production, this would use
 * real Kafka and Cassandra drivers.
 */
class MessagingClient implements MessagingClientInterface
{
    private array $config;
    private array $handlers = [];
    private bool $initialized = false;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(): void
    {
        echo "🔧 Production MessagingClient initialized\n";
        echo "   - Kafka brokers: " . implode(', ', $this->config['kafka']['brokers']) . "\n";
        echo "   - Cassandra keyspace: " . $this->config['cassandra']['keyspace'] . "\n";
        echo "   - Encryption enabled: " . (isset($this->config['encryption']['encryptionKey']) ? 'Yes' : 'No') . "\n";
        $this->initialized = true;
    }

    public function registerHandler(string $topic, RequestHandlerInterface $handler): void
    {
        $this->handlers[$topic] = $handler;
        echo "📝 Registered handler for topic: {$topic}\n";
    }

    public function request(string $topic, array $payload): array
    {
        if (!$this->initialized) {
            throw new \Exception("MessagingClient not initialized");
        }

        if (!isset($this->handlers[$topic])) {
            throw new \Exception("No handler registered for topic: {$topic}");
        }

        $requestId = 'req_' . uniqid();
        echo "📤 Sending request to topic: {$topic}\n";
        echo "📦 Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        echo "🆔 Request ID: {$requestId}\n";

        // Simulate network delay
        usleep(100000); // 100ms

        $response = $this->handlers[$topic]->handle($requestId, $payload);
        
        echo "📥 Response received:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
        
        return $response;
    }

    public function startConsuming(array $topics): void
    {
        if (!$this->initialized) {
            throw new \Exception("MessagingClient not initialized");
        }

        echo "🔄 Starting consumption for topics: " . implode(', ', $topics) . "\n";
        echo "ℹ️  In production mode, this would connect to real Kafka\n\n";

        // In production, this would start real Kafka consumer
        // For now, we'll simulate continuous consumption
        $counter = 0;
        $messageTypes = [
            'user_registration',
            'order_created',
            'payment_processed',
            'notification_sent',
            'data_sync',
            'dukcapil_request'
        ];

        while (true) {
            $topic = $topics[$counter % count($topics)];
            $messageType = $messageTypes[$counter % count($messageTypes)];
            
            $message = $this->generateProductionMessage($messageType, $counter + 1);
            
            echo "[PRODUCTION] Consuming message from {$topic} | type={$messageType}\n";
            
            try {
                if (isset($this->handlers[$topic])) {
                    $response = $this->handlers[$topic]->handle($message['requestId'], $message);
                    echo "[PRODUCTION] Handler response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
                } else {
                    echo "[PRODUCTION] No handler for topic: {$topic}\n\n";
                }
            } catch (\Exception $e) {
                echo "[PRODUCTION] Handler error: " . $e->getMessage() . "\n\n";
            }
            
            $counter++;
            usleep(3000000); // 3 seconds between messages
        }
    }

    private function generateProductionMessage(string $type, int $sequence): array
    {
        $baseMessage = [
            'type' => $type,
            'sequence' => $sequence,
            'timestamp' => date('Y-m-d H:i:s'),
            'requestId' => 'prod_req_' . uniqid() . '_' . $sequence
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

    public function getConfig(): array
    {
        return $this->config;
    }
}