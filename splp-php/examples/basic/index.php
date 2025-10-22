<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Contracts\RequestHandlerInterface;
use Splp\Messaging\Core\Utils\RequestIdGenerator;
use Splp\Messaging\Core\Crypto\EncryptionService;

/**
 * Basic Request-Reply Example
 * 
 * Demonstrates basic messaging without Command Center
 */
class BasicExample
{
    private MessagingClient $client;

    public function __construct()
    {
        $config = [
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
                'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
            ]
        ];

        $this->client = new MessagingClient($config);
    }

    public function run(): void
    {
        echo "=== SPLP Basic Example ===\n";
        
        // Initialize client
        $this->client->initialize();
        
        // Register calculator handler
        $this->client->registerHandler('calculate', new CalculatorHandler());
        
        // Start consuming in background
        $this->startConsuming();
        
        // Send some test requests
        $this->sendTestRequests();
        
        // Keep running
        $this->keepAlive();
    }

    private function startConsuming(): void
    {
        // Start consuming in a separate process/thread
        // In a real application, this would be handled by a worker process
        echo "Starting message consumption...\n";
        $this->client->startConsuming(['calculate']);
    }

    private function sendTestRequests(): void
    {
        echo "Sending test requests...\n";
        
        $requests = [
            ['a' => 10, 'b' => 5],
            ['a' => 20, 'b' => 15],
            ['a' => 100, 'b' => 200]
        ];

        foreach ($requests as $i => $request) {
            try {
                echo "Request " . ($i + 1) . ": {$request['a']} + {$request['b']}\n";
                $response = $this->client->request('calculate', $request);
                echo "Response: {$response['result']}\n\n";
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage() . "\n\n";
            }
        }
    }

    private function keepAlive(): void
    {
        echo "Press Ctrl+C to stop...\n";
        while (true) {
            sleep(1);
        }
    }
}

/**
 * Calculator Handler
 */
class CalculatorHandler implements RequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "Processing request {$requestId}: {$payload['a']} + {$payload['b']}\n";
        
        // Simulate some processing time
        usleep(100000); // 100ms
        
        return [
            'result' => $payload['a'] + $payload['b'],
            'requestId' => $requestId,
            'processedAt' => date('Y-m-d H:i:s')
        ];
    }
}

// Run the example
if (php_sapi_name() === 'cli') {
    $example = new BasicExample();
    $example->run();
}
