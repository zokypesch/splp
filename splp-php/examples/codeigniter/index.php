<?php

declare(strict_types=1);

namespace Splp\Messaging\Examples;

use Splp\Messaging\CodeIgniter\SplpMessagingLibrary;

/**
 * CodeIgniter Integration Example
 * 
 * Demonstrates SPLP integration with CodeIgniter framework
 */
class CodeIgniterExample
{
    private SplpMessagingLibrary $splp;

    public function __construct()
    {
        $this->splp = new SplpMessagingLibrary();
    }

    public function run(): void
    {
        echo "=== SPLP CodeIgniter Integration Example ===\n";
        
        // Initialize SPLP
        $this->splp->initialize();
        
        // Register handlers
        $this->registerHandlers();
        
        // Send requests
        $this->sendRequests();
        
        echo "CodeIgniter integration example completed!\n";
    }

    private function registerHandlers(): void
    {
        echo "Registering handlers...\n";
        
        // Register order processing handler
        $this->splp->registerHandler('order-processing', function ($requestId, $payload) {
            echo "Processing order: {$payload['orderId']}\n";
            
            // Simulate order processing
            sleep(1);
            
            return [
                'orderId' => $payload['orderId'],
                'status' => 'processed',
                'processedAt' => date('Y-m-d H:i:s'),
                'requestId' => $requestId
            ];
        });
        
        // Register payment processing handler
        $this->splp->registerHandler('payment-processing', function ($requestId, $payload) {
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
        });
        
        echo "✓ Handlers registered\n";
    }

    private function sendRequests(): void
    {
        echo "Sending requests...\n";
        
        // Simulate order processing request
        try {
            $orderResponse = $this->splp->request('order-processing', [
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
            $paymentResponse = $this->splp->request('payment-processing', [
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
 * CodeIgniter Controller Example
 */
class Order_controller
{
    private $splp;

    public function __construct()
    {
        $this->load->library('splp_messaging');
        $this->splp = $this->splp_messaging;
    }

    public function process_order()
    {
        try {
            $response = $this->splp->request('order-processing', [
                'orderId' => $this->input->post('order_id'),
                'userId' => $this->input->post('user_id'),
                'items' => $this->input->post('items'),
                'total' => $this->input->post('total')
            ]);
            
            $this->output->set_content_type('application/json');
            $this->output->set_output(json_encode([
                'success' => true,
                'data' => $response
            ]));
        } catch (\Exception $e) {
            $this->output->set_status_header(500);
            $this->output->set_content_type('application/json');
            $this->output->set_output(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }
    }
}

/**
 * CodeIgniter Library Example
 */
class Splp_messaging
{
    private $splp;

    public function __construct()
    {
        $this->splp = new SplpMessagingLibrary();
        $this->splp->initialize();
    }

    public function request($topic, $payload, $timeout = 30000)
    {
        return $this->splp->request($topic, $payload, $timeout);
    }

    public function registerHandler($topic, $handler)
    {
        $this->splp->registerHandler($topic, $handler);
    }

    public function startConsuming($topics)
    {
        $this->splp->startConsuming($topics);
    }

    public function getHealthStatus()
    {
        return $this->splp->getHealthStatus();
    }
}

// Run the example
if (php_sapi_name() === 'cli') {
    $example = new CodeIgniterExample();
    $example->run();
}
