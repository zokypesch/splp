<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\Laravel\SplpMessaging;
use Splp\Messaging\Contracts\RequestHandlerInterface;

/**
 * Laravel Integration Example
 * 
 * Demonstrates SPLP integration with Laravel framework
 */
class LaravelExample
{
    public function run(): void
    {
        echo "=== SPLP Laravel Integration Example ===\n";
        
        // This would typically be in a Laravel service provider
        $this->registerHandlers();
        
        // This would be in a controller or service
        $this->sendRequests();
        
        echo "Laravel integration example completed!\n";
    }

    private function registerHandlers(): void
    {
        echo "Registering handlers...\n";
        
        // Register order processing handler
        SplpMessaging::registerHandler('order-processing', new OrderProcessingHandler());
        
        // Register payment processing handler
        SplpMessaging::registerHandler('payment-processing', new PaymentProcessingHandler());
        
        echo "✓ Handlers registered\n";
    }

    private function sendRequests(): void
    {
        echo "Sending requests...\n";
        
        // Simulate order processing request
        try {
            $orderResponse = SplpMessaging::request('order-processing', [
                'orderId' => 'ORD-123456',
                'userId' => 'user-789',
                'items' => ['laptop', 'mouse', 'keyboard'],
                'total' => 1299.99
            ]);
            
            echo "Order processed: " . json_encode($orderResponse) . "\n";
        } catch (\Exception $e) {
            echo "Order processing failed: " . $e->getMessage() . "\n";
        }
        
        // Simulate payment processing request
        try {
            $paymentResponse = SplpMessaging::request('payment-processing', [
                'paymentId' => 'PAY-789012',
                'orderId' => 'ORD-123456',
                'amount' => 1299.99,
                'method' => 'credit_card'
            ]);
            
            echo "Payment processed: " . json_encode($paymentResponse) . "\n";
        } catch (\Exception $e) {
            echo "Payment processing failed: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Order Processing Handler
 */
class OrderProcessingHandler implements RequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "Processing order: {$payload['orderId']}\n";
        
        // Simulate order processing
        sleep(1);
        
        return [
            'orderId' => $payload['orderId'],
            'status' => 'processed',
            'processedAt' => date('Y-m-d H:i:s'),
            'requestId' => $requestId
        ];
    }
}

/**
 * Payment Processing Handler
 */
class PaymentProcessingHandler implements RequestHandlerInterface
{
    public function handle(string $requestId, mixed $payload): mixed
    {
        echo "Processing payment: {$payload['paymentId']}\n";
        
        // Simulate payment processing
        sleep(1);
        
        return [
            'paymentId' => $payload['paymentId'],
            'status' => 'completed',
            'transactionId' => 'TXN-' . uniqid(),
            'processedAt' => date('Y-m-d H:i:s'),
            'requestId' => $requestId
        ];
    }
}

/**
 * Laravel Controller Example
 */
class OrderController
{
    public function processOrder(Request $request)
    {
        try {
            $response = SplpMessaging::request('order-processing', [
                'orderId' => $request->input('order_id'),
                'userId' => $request->input('user_id'),
                'items' => $request->input('items'),
                'total' => $request->input('total')
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

/**
 * Laravel Service Provider Example
 */
class SplpServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        // Register handlers when the application boots
        SplpMessaging::registerHandler('order-processing', new OrderProcessingHandler());
        SplpMessaging::registerHandler('payment-processing', new PaymentProcessingHandler());
    }
}

// Run the example
if (php_sapi_name() === 'cli') {
    $example = new LaravelExample();
    $example->run();
}
