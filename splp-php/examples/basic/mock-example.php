<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

/**
 * Mock Basic Request-Reply Example
 * 
 * Demonstrates basic messaging pattern without actual Kafka/Cassandra dependencies
 * This is a simplified version for demonstration purposes
 */
class MockBasicExample
{
    private array $handlers = [];
    private array $config;

    public function __construct()
    {
        $this->config = [
            'kafka' => [
                'brokers' => ['10.70.1.23:9092'],
                'clientId' => 'basic-example',
                'groupId' => 'basic-example-group'
            ],
            'cassandra' => [
                'contactPoints' => ['localhost'],
                'localDataCenter' => 'datacenter1',
                'keyspace' => 'basic_example'
            ],
            'encryption' => [
                'encryptionKey' => '8cba970142b7fa5038b2808aed8e044aee4779821610abc88f19cc286876f90'
            ]
        ];
    }

    public function initialize(): void
    {
        echo "✅ Mock MessagingClient initialized\n";
        echo "📋 Configuration loaded:\n";
        echo "   - Kafka brokers: " . implode(', ', $this->config['kafka']['brokers']) . "\n";
        echo "   - Cassandra keyspace: " . $this->config['cassandra']['keyspace'] . "\n";
        echo "   - Encryption enabled: " . (isset($this->config['encryption']['encryptionKey']) ? 'Yes' : 'No') . "\n\n";
    }

    public function registerHandler(string $topic, MockRequestHandlerInterface $handler): void
    {
        $this->handlers[$topic] = $handler;
        echo "📝 Registered handler for topic: {$topic}\n";
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
        usleep(100000); // 100ms
        
        $response = $this->handlers[$topic]->handle($requestId, $payload);
        
        echo "📥 Response received:\n";
        echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
        
        return $response;
    }

    public function startConsuming(array $topics): void
    {
        echo "🔄 Starting consumption for topics: " . implode(', ', $topics) . "\n";
        echo "ℹ️  In a real implementation, this would start background workers\n\n";
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
 * Calculator Handler
 */
class MockCalculatorHandler implements MockRequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "🧮 Processing calculation request {$requestId}\n";
        echo "   Input: {$payload['a']} + {$payload['b']}\n";
        
        // Simulate some processing time
        usleep(50000); // 50ms
        
        $result = $payload['a'] + $payload['b'];
        
        return [
            'result' => $result,
            'requestId' => $requestId,
            'processedAt' => date('Y-m-d H:i:s'),
            'operation' => 'addition',
            'inputs' => $payload
        ];
    }
}

/**
 * Advanced Calculator Handler with multiple operations
 */
class MockAdvancedCalculatorHandler implements MockRequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "🔢 Processing advanced calculation request {$requestId}\n";
        
        $operation = $payload['operation'] ?? 'add';
        $a = $payload['a'];
        $b = $payload['b'];
        
        $result = match($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : null,
            default => throw new \Exception("Unknown operation: {$operation}")
        };
        
        if ($result === null) {
            throw new \Exception("Division by zero");
        }
        
        return [
            'result' => $result,
            'requestId' => $requestId,
            'processedAt' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'inputs' => $payload
        ];
    }
}

// Run the example
if (php_sapi_name() === 'cli') {
    echo "=== SPLP PHP Mock Basic Example ===\n\n";
    
    $client = new MockBasicExample();
    $client->initialize();
    
    // Register handlers
    $client->registerHandler('calculate', new MockCalculatorHandler());
    $client->registerHandler('advanced-calculate', new MockAdvancedCalculatorHandler());
    
    // Start consuming (mock)
    $client->startConsuming(['calculate', 'advanced-calculate']);
    
    // Send test requests
    echo "🚀 Sending test requests...\n\n";
    
    $requests = [
        ['a' => 10, 'b' => 5],
        ['a' => 20, 'b' => 15],
        ['a' => 100, 'b' => 200]
    ];

    foreach ($requests as $i => $request) {
        try {
            echo "📋 Test " . ($i + 1) . ":\n";
            $response = $client->request('calculate', $request);
            echo "✅ Success! Result: {$response['result']}\n\n";
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Test advanced calculator
    echo "🧪 Testing advanced calculator...\n\n";
    
    $advancedRequests = [
        ['operation' => 'add', 'a' => 10, 'b' => 5],
        ['operation' => 'multiply', 'a' => 7, 'b' => 8],
        ['operation' => 'divide', 'a' => 100, 'b' => 4]
    ];
    
    foreach ($advancedRequests as $i => $request) {
        try {
            echo "📋 Advanced Test " . ($i + 1) . ":\n";
            $response = $client->request('advanced-calculate', $request);
            echo "✅ Success! {$request['operation']}({$request['a']}, {$request['b']}) = {$response['result']}\n\n";
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    echo "🎉 Demo completed successfully!\n";
    echo "💡 This demonstrates the SPLP messaging pattern without actual Kafka/Cassandra dependencies\n";
    echo "🔧 To use with real infrastructure, install rdkafka/rdkafka and datastax/php-driver\n";
}
