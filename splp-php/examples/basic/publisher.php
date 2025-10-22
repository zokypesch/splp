<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

/**
 * Service-1 Topic Publisher
 * 
 * Publishes messages to service-1-topic for testing the listener
 */
class Service1Publisher
{
    private array $config;
    private array $messageTypes = [
        'user_registration',
        'order_created', 
        'payment_processed',
        'notification_sent',
        'data_sync'
    ];

    public function __construct()
    {
        $this->config = [
            'kafka' => [
                'brokers' => ['10.70.1.23:9092'],
                'clientId' => 'service-1-publisher',
                'groupId' => 'service-1-publisher-group'
            ],
            'cassandra' => [
                'contactPoints' => ['localhost'],
                'localDataCenter' => 'datacenter1',
                'keyspace' => 'service_1_keyspace'
            ],
            'encryption' => [
                'encryptionKey' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7'
            ]
        ];
    }

    public function publishTestMessages(int $count = 5): void
    {
        echo "🚀 Service-1 Publisher - Publishing {$count} test messages\n";
        echo "📡 Target: service-1-topic\n";
        echo "🔗 Kafka: " . implode(',', $this->config['kafka']['brokers']) . "\n\n";

        for ($i = 1; $i <= $count; $i++) {
            $messageType = $this->messageTypes[($i - 1) % count($this->messageTypes)];
            $message = $this->generateMessage($messageType, $i);
            
            echo "📤 Publishing message {$i}/{$count}\n";
            echo "   Type: {$messageType}\n";
            echo "   Payload: " . json_encode($message, JSON_PRETTY_PRINT) . "\n";
            
            // Simulate publishing (in real implementation, this would use MessagingClient)
            $this->simulatePublish('service-1-topic', $message);
            
            echo "   ✅ Published successfully\n\n";
            
            // Wait between messages
            usleep(500000); // 500ms
        }
        
        echo "🎉 All messages published successfully!\n";
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
            default => $baseMessage
        };
    }

    private function simulatePublish(string $topic, array $message): void
    {
        // In a real implementation, this would use MessagingClient to publish
        // For now, we just simulate the publishing process
        echo "   📡 [SIMULATED] Publishing to topic: {$topic}\n";
        echo "   🔐 [SIMULATED] Encrypting payload\n";
        echo "   📤 [SIMULATED] Sending to Kafka broker\n";
        
        // Simulate network delay
        usleep(100000); // 100ms
    }

    public function publishCustomMessage(array $message): void
    {
        echo "📤 Publishing custom message\n";
        echo "   Payload: " . json_encode($message, JSON_PRETTY_PRINT) . "\n";
        
        $this->simulatePublish('service-1-topic', $message);
        echo "   ✅ Custom message published successfully\n\n";
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $args = getopt('', ['count:', 'custom:']);
    
    $publisher = new Service1Publisher();
    
    if (isset($args['custom'])) {
        // Publish custom message
        $customMessage = json_decode($args['custom'], true);
        if ($customMessage) {
            $publisher->publishCustomMessage($customMessage);
        } else {
            echo "❌ Invalid JSON in custom message\n";
            exit(1);
        }
    } else {
        // Publish test messages
        $count = (int)($args['count'] ?? 5);
        $publisher->publishTestMessages($count);
    }
}
