<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

/**
 * Mock Laravel Integration Example
 * 
 * Demonstrates SPLP integration with Laravel framework using mock implementation
 */
class MockLaravelExample
{
    private array $handlers = [];
    private array $config;

    public function __construct()
    {
        $this->config = [
            'kafka' => [
                'brokers' => ['10.70.1.23:9092'],
                'clientId' => 'laravel-app',
                'groupId' => 'laravel-app-group'
            ],
            'cassandra' => [
                'contactPoints' => ['localhost'],
                'localDataCenter' => 'datacenter1',
                'keyspace' => 'laravel_keyspace'
            ],
            'encryption' => [
                'encryptionKey' => 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7'
            ]
        ];
    }

    public function run(): void
    {
        echo "=== SPLP Mock Laravel Integration Example ===\n";
        echo "🔧 Configuration:\n";
        echo "   - Kafka brokers: " . implode(', ', $this->config['kafka']['brokers']) . "\n";
        echo "   - Cassandra keyspace: " . $this->config['cassandra']['keyspace'] . "\n";
        echo "   - Encryption enabled: " . (isset($this->config['encryption']['encryptionKey']) ? 'Yes' : 'No') . "\n\n";
        
        // This would typically be in a Laravel service provider
        $this->registerHandlers();
        
        // This would be in a controller or service
        $this->sendRequests();
        
        echo "🎉 Laravel integration example completed!\n";
    }

    private function registerHandlers(): void
    {
        echo "📝 Registering handlers...\n";
        
        // Register order processing handler
        $this->registerHandler('order-processing', new MockOrderProcessingHandler());
        
        // Register payment processing handler
        $this->registerHandler('payment-processing', new MockPaymentProcessingHandler());
        
        echo "✅ Handlers registered\n\n";
    }

    private function registerHandler(string $topic, MockRequestHandlerInterface $handler): void
    {
        $this->handlers[$topic] = $handler;
        echo "   📋 Registered handler for topic: {$topic}\n";
    }

    private function sendRequests(): void
    {
        echo "🚀 Sending requests...\n\n";
        
        // Simulate order processing request
        try {
            echo "📦 Order Processing Request:\n";
            $orderResponse = $this->request('order-processing', [
                'orderId' => 'ORD-123456',
                'userId' => 'user-789',
                'items' => ['laptop', 'mouse', 'keyboard'],
                'total' => 1299.99
            ]);
            
            echo "✅ Order processed: " . json_encode($orderResponse, JSON_PRETTY_PRINT) . "\n\n";
        } catch (\Exception $e) {
            echo "❌ Order processing failed: " . $e->getMessage() . "\n\n";
        }
        
        // Simulate payment processing request
        try {
            echo "💳 Payment Processing Request:\n";
            $paymentResponse = $this->request('payment-processing', [
                'paymentId' => 'PAY-789012',
                'orderId' => 'ORD-123456',
                'amount' => 1299.99,
                'method' => 'credit_card'
            ]);
            
            echo "✅ Payment processed: " . json_encode($paymentResponse, JSON_PRETTY_PRINT) . "\n\n";
        } catch (\Exception $e) {
            echo "❌ Payment processing failed: " . $e->getMessage() . "\n\n";
        }
    }

    public function request(string $topic, array $payload): array
    {
        echo "📤 Sending request to topic: {$topic}\n";
        echo "📦 Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        
        if (!isset($this->handlers[$topic])) {
            throw new \Exception("No handler registered for topic: {$topic}");
        }

        $requestId = $this->generateRequestId();
        echo "🆔 Request ID: {$requestId}\n";
        
        // Simulate processing time
        usleep(200000); // 200ms
        
        $response = $this->handlers[$topic]->handle($requestId, $payload);
        
        echo "📥 Response received:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
        
        return $response;
    }

    private function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . mt_rand(1000, 9999);
    }
}

/**
 * Mock Request Handler Interface
 */
interface MockRequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed;
}

/**
 * Mock Order Processing Handler
 */
class MockOrderProcessingHandler implements MockRequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "🛒 Processing order: {$payload['orderId']}\n";
        echo "   👤 User: {$payload['userId']}\n";
        echo "   📦 Items: " . implode(', ', $payload['items']) . "\n";
        echo "   💰 Total: $" . number_format($payload['total'], 2) . "\n";
        
        // Simulate order processing
        usleep(100000); // 100ms
        
        return [
            'orderId' => $payload['orderId'],
            'status' => 'processed',
            'processedAt' => date('Y-m-d H:i:s'),
            'requestId' => $requestId,
            'items' => $payload['items'],
            'total' => $payload['total'],
            'userId' => $payload['userId']
        ];
    }
}

/**
 * Mock Payment Processing Handler
 */
class MockPaymentProcessingHandler implements MockRequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "💳 Processing payment: {$payload['paymentId']}\n";
        echo "   🛒 Order: {$payload['orderId']}\n";
        echo "   💰 Amount: $" . number_format($payload['amount'], 2) . "\n";
        echo "   🏦 Method: {$payload['method']}\n";
        
        // Simulate payment processing
        usleep(150000); // 150ms
        
        return [
            'paymentId' => $payload['paymentId'],
            'status' => 'completed',
            'transactionId' => 'TXN-' . uniqid(),
            'processedAt' => date('Y-m-d H:i:s'),
            'requestId' => $requestId,
            'orderId' => $payload['orderId'],
            'amount' => $payload['amount'],
            'method' => $payload['method']
        ];
    }
}

/**
 * Mock Laravel Controller Example
 */
class MockOrderController
{
    private MockLaravelExample $messaging;

    public function __construct()
    {
        $this->messaging = new MockLaravelExample();
    }

    public function processOrder(array $requestData): array
    {
        try {
            echo "🎮 Mock Laravel Controller - Processing Order\n";
            $response = $this->messaging->request('order-processing', [
                'orderId' => $requestData['order_id'],
                'userId' => $requestData['user_id'],
                'items' => $requestData['items'],
                'total' => $requestData['total']
            ]);
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Mock Laravel Service Provider Example
 */
class MockSplpServiceProvider
{
    private array $handlers = [];

    public function boot(): void
    {
        echo "🔧 Mock Laravel Service Provider - Boot\n";
        
        // Register handlers when the application boots
        $this->handlers['order-processing'] = new MockOrderProcessingHandler();
        $this->handlers['payment-processing'] = new MockPaymentProcessingHandler();
        
        echo "✅ Service provider handlers registered\n";
    }

    public function getHandlers(): array
    {
        return $this->handlers;
    }
}

// Run the example
if (php_sapi_name() === 'cli') {
    echo "🚀 Starting Mock Laravel Integration Test...\n\n";
    
    // Test 1: Basic Laravel Example
    echo "=== Test 1: Basic Laravel Example ===\n";
    $example = new MockLaravelExample();
    $example->run();
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // Test 2: Mock Controller
    echo "=== Test 2: Mock Laravel Controller ===\n";
    $controller = new MockOrderController();
    $result = $controller->processOrder([
        'order_id' => 'ORD-CONTROLLER-001',
        'user_id' => 'user-controller-123',
        'items' => ['monitor', 'cable', 'adapter'],
        'total' => 599.99
    ]);
    
    echo "Controller Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // Test 3: Mock Service Provider
    echo "=== Test 3: Mock Laravel Service Provider ===\n";
    $serviceProvider = new MockSplpServiceProvider();
    $serviceProvider->boot();
    
    $handlers = $serviceProvider->getHandlers();
    echo "📋 Available handlers: " . implode(', ', array_keys($handlers)) . "\n";
    
    echo "\n🎉 All Mock Laravel Integration Tests Completed!\n";
    echo "💡 This demonstrates Laravel integration patterns with SPLP messaging\n";
    echo "🔧 Kafka configured to use: 10.70.1.23:9092\n";
}
