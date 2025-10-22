<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\CommandCenter\CommandCenter;
use Splp\Messaging\Core\Kafka\KafkaWrapper;
use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Core\Utils\RequestIdGenerator;

/**
 * Command Center Example
 * 
 * Demonstrates Command Center routing with multiple services
 */
class CommandCenterExample
{
    private CommandCenter $commandCenter;
    private KafkaWrapper $kafka;
    private EncryptionService $encryption;

    public function __construct()
    {
        $config = [
            'kafka' => [
                'brokers' => ['localhost:9092'],
                'clientId' => 'command-center-example',
                'groupId' => 'command-center-example-group'
            ],
            'cassandra' => [
                'contactPoints' => ['localhost'],
                'localDataCenter' => 'datacenter1',
                'keyspace' => 'command_center_example'
            ],
            'encryption' => [
                'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
            ],
            'commandCenter' => [
                'inboxTopic' => 'command-center-inbox',
                'enableAutoRouting' => true,
                'defaultTimeout' => 30000
            ]
        ];

        $this->commandCenter = new CommandCenter($config);
        $this->kafka = new KafkaWrapper($config['kafka']);
        $this->encryption = new EncryptionService($config['encryption']);
    }

    public function run(): void
    {
        echo "=== SPLP Command Center Example ===\n";
        
        // Initialize Command Center
        $this->commandCenter->initialize();
        
        // Register routes
        $this->registerRoutes();
        
        // Start Command Center
        $this->commandCenter->start();
        
        echo "Command Center is running...\n";
        echo "Press Ctrl+C to stop...\n";
        
        // Keep running
        while (true) {
            sleep(1);
        }
    }

    private function registerRoutes(): void
    {
        echo "Registering routes...\n";
        
        // Route 1: Order Processing
        $this->commandCenter->registerRoute([
            'routeId' => 'route-order-processing',
            'sourcePublisher' => 'order-publisher',
            'targetTopic' => 'order-processing-topic',
            'serviceInfo' => [
                'serviceName' => 'order-processing-service',
                'version' => '1.0.0',
                'description' => 'Processes customer orders',
                'endpoint' => 'http://localhost:3001',
                'tags' => ['order', 'processing', 'ecommerce']
            ],
            'enabled' => true,
            'createdAt' => new \DateTime(),
            'updatedAt' => new \DateTime()
        ]);

        // Route 2: Payment Processing
        $this->commandCenter->registerRoute([
            'routeId' => 'route-payment-processing',
            'sourcePublisher' => 'payment-publisher',
            'targetTopic' => 'payment-processing-topic',
            'serviceInfo' => [
                'serviceName' => 'payment-processing-service',
                'version' => '1.0.0',
                'description' => 'Processes payments',
                'endpoint' => 'http://localhost:3002',
                'tags' => ['payment', 'processing', 'financial']
            ],
            'enabled' => true,
            'createdAt' => new \DateTime(),
            'updatedAt' => new \DateTime()
        ]);

        // Route 3: Notification Service
        $this->commandCenter->registerRoute([
            'routeId' => 'route-notification',
            'sourcePublisher' => 'notification-publisher',
            'targetTopic' => 'notification-topic',
            'serviceInfo' => [
                'serviceName' => 'notification-service',
                'version' => '1.0.0',
                'description' => 'Sends notifications',
                'endpoint' => 'http://localhost:3003',
                'tags' => ['notification', 'email', 'sms']
            ],
            'enabled' => true,
            'createdAt' => new \DateTime(),
            'updatedAt' => new \DateTime()
        ]);

        echo "✓ Routes registered successfully\n";
    }

    public function sendTestMessage(string $workerName, array $payload): void
    {
        $requestId = RequestIdGenerator::generate();
        $encrypted = $this->encryption->encryptPayload($payload, $requestId);
        
        $message = [
            'request_id' => $requestId,
            'worker_name' => $workerName,
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag']
        ];

        $this->kafka->connectProducer();
        $this->kafka->sendMessage('command-center-inbox', json_encode($message));
        
        echo "Message sent: {$workerName} -> {$requestId}\n";
    }
}

/**
 * Publisher Example
 * 
 * Demonstrates sending messages via Command Center
 */
class PublisherExample
{
    private KafkaWrapper $kafka;
    private EncryptionService $encryption;

    public function __construct()
    {
        $config = [
            'brokers' => ['localhost:9092'],
            'clientId' => 'publisher-example'
        ];

        $this->kafka = new KafkaWrapper($config);
        $this->encryption = new EncryptionService([
            'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
        ]);
    }

    public function run(): void
    {
        echo "=== SPLP Publisher Example ===\n";
        
        $this->kafka->connectProducer();
        
        // Send test messages
        $this->sendOrderMessage();
        $this->sendPaymentMessage();
        $this->sendNotificationMessage();
        
        echo "All messages sent!\n";
    }

    private function sendOrderMessage(): void
    {
        $requestId = RequestIdGenerator::generate();
        $payload = [
            'orderId' => 'ORD-123456',
            'userId' => 'user-789',
            'items' => ['laptop', 'mouse', 'keyboard'],
            'total' => 1299.99
        ];
        
        $encrypted = $this->encryption->encryptPayload($payload, $requestId);
        
        $message = [
            'request_id' => $requestId,
            'worker_name' => 'order-publisher',
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag']
        ];

        $this->kafka->sendMessage('command-center-inbox', json_encode($message));
        echo "Order message sent: {$requestId}\n";
    }

    private function sendPaymentMessage(): void
    {
        $requestId = RequestIdGenerator::generate();
        $payload = [
            'paymentId' => 'PAY-789012',
            'orderId' => 'ORD-123456',
            'amount' => 1299.99,
            'method' => 'credit_card'
        ];
        
        $encrypted = $this->encryption->encryptPayload($payload, $requestId);
        
        $message = [
            'request_id' => $requestId,
            'worker_name' => 'payment-publisher',
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag']
        ];

        $this->kafka->sendMessage('command-center-inbox', json_encode($message));
        echo "Payment message sent: {$requestId}\n";
    }

    private function sendNotificationMessage(): void
    {
        $requestId = RequestIdGenerator::generate();
        $payload = [
            'notificationId' => 'NOTIF-345678',
            'userId' => 'user-789',
            'type' => 'order_confirmation',
            'message' => 'Your order has been confirmed!'
        ];
        
        $encrypted = $this->encryption->encryptPayload($payload, $requestId);
        
        $message = [
            'request_id' => $requestId,
            'worker_name' => 'notification-publisher',
            'data' => $encrypted['data'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag']
        ];

        $this->kafka->sendMessage('command-center-inbox', json_encode($message));
        echo "Notification message sent: {$requestId}\n";
    }
}

// Run examples
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'command-center';
    
    switch ($command) {
        case 'command-center':
            $example = new CommandCenterExample();
            $example->run();
            break;
            
        case 'publisher':
            $example = new PublisherExample();
            $example->run();
            break;
            
        default:
            echo "Usage: php index.php [command-center|publisher]\n";
            break;
    }
}
