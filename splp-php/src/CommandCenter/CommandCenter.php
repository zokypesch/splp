<?php

declare(strict_types=1);

namespace Splp\Messaging\CommandCenter;

use Splp\Messaging\Contracts\CommandCenterInterface;
use Splp\Messaging\Contracts\SchemaRegistryInterface;
use Splp\Messaging\Contracts\MetadataLoggerInterface;
use Splp\Messaging\Core\Kafka\KafkaWrapper;
use Splp\Messaging\Core\Logging\CassandraLogger;
use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Exceptions\MessagingException;

/**
 * Command Center
 * 
 * Central routing hub that:
 * 1. Receives messages from publishers on inbox topic
 * 2. Looks up routes in schema registry
 * 3. Routes messages to target topics
 * 4. Logs metadata to Cassandra (NO PAYLOAD)
 */
class CommandCenter implements CommandCenterInterface
{
    private KafkaWrapper $kafka;
    private SchemaRegistryManager $schemaRegistry;
    private MetadataLogger $metadataLogger;
    private EncryptionService $encryption;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeComponents();
    }

    /**
     * Initialize Command Center
     */
    public function initialize(): void
    {
        try {
            $this->kafka->connectProducer();
            $this->kafka->connectConsumer();
            $this->schemaRegistry->initialize();
            $this->metadataLogger->initialize();
            
            error_log('Command Center initialized successfully');
        } catch (\Exception $e) {
            throw new MessagingException('Failed to initialize Command Center: ' . $e->getMessage());
        }
    }

    /**
     * Start listening for messages on inbox topic and route them
     */
    public function start(): void
    {
        $inboxTopic = $this->config['commandCenter']['inboxTopic'];
        
        error_log("Starting Command Center, listening on: {$inboxTopic}");

        $this->kafka->subscribe([$inboxTopic], function ($topic, $partition, $message) {
            $startTime = microtime(true);

            try {
                if (empty($message['value'])) {
                    error_log('Received empty message, skipping');
                    return;
                }

                $messageValue = $message['value'];
                $this->routeMessage($messageValue, $topic, $startTime);
            } catch (\Exception $e) {
                error_log('Error processing message: ' . $e->getMessage());
            }
        });

        error_log('Command Center is running...');
    }

    /**
     * Register a route
     */
    public function registerRoute(array $routeConfig): void
    {
        $this->schemaRegistry->registerRoute($routeConfig);
    }

    /**
     * Get schema registry
     */
    public function getSchemaRegistry(): SchemaRegistryInterface
    {
        return $this->schemaRegistry;
    }

    /**
     * Get metadata logger
     */
    public function getMetadataLogger(): MetadataLoggerInterface
    {
        return $this->metadataLogger;
    }

    /**
     * Shutdown Command Center
     */
    public function shutdown(): void
    {
        error_log('Shutting down Command Center...');
        
        $this->kafka->disconnect();
        $this->schemaRegistry->close();
        $this->metadataLogger->close();
        
        error_log('Command Center shutdown complete');
    }

    /**
     * Route a message to its target topic based on schema registry
     */
    private function routeMessage(string $messageValue, string $sourceTopic, float $startTime): void
    {
        $metadata = [
            'timestamp' => new \DateTime(),
            'source_topic' => $sourceTopic,
            'message_type' => 'request',
        ];

        try {
            // Parse incoming message
            $incomingMsg = json_decode($messageValue, true);
            if (!$incomingMsg) {
                throw new MessagingException('Invalid JSON message');
            }

            $metadata['request_id'] = $incomingMsg['request_id'];
            $metadata['worker_name'] = $incomingMsg['worker_name'];

            error_log("Routing message from {$incomingMsg['worker_name']} ({$incomingMsg['request_id']})");

            // Look up route in schema registry
            $route = $this->schemaRegistry->getRoute($incomingMsg['worker_name']);

            if (!$route) {
                throw new MessagingException("No route found for publisher: {$incomingMsg['worker_name']}");
            }

            if (!$route['enabled']) {
                throw new MessagingException("Route disabled for publisher: {$incomingMsg['worker_name']}");
            }

            $metadata['route_id'] = $route['routeId'];
            $metadata['target_topic'] = $route['targetTopic'];

            // Create routed message (preserve encryption, add routing info)
            $routedMsg = [
                'request_id' => $incomingMsg['request_id'],
                'worker_name' => $incomingMsg['worker_name'],
                'source_topic' => $sourceTopic,
                'data' => $incomingMsg['data'],
                'iv' => $incomingMsg['iv'],
                'tag' => $incomingMsg['tag'],
            ];

            // Route to target topic
            $this->kafka->sendMessage(
                $route['targetTopic'],
                json_encode($routedMsg),
                $incomingMsg['request_id']
            );

            $processingTime = (microtime(true) - $startTime) * 1000;

            // Log metadata (NO PAYLOAD)
            $this->metadataLogger->log([
                'request_id' => $incomingMsg['request_id'],
                'worker_name' => $incomingMsg['worker_name'],
                'timestamp' => new \DateTime(),
                'source_topic' => $sourceTopic,
                'target_topic' => $route['targetTopic'],
                'route_id' => $route['routeId'],
                'message_type' => 'request',
                'success' => true,
                'processing_time_ms' => $processingTime,
            ]);

            error_log("Routed {$incomingMsg['request_id']}: {$incomingMsg['worker_name']} -> {$route['targetTopic']} ({$processingTime}ms)");
        } catch (\Exception $e) {
            $processingTime = (microtime(true) - $startTime) * 1000;
            $errorMessage = $e->getMessage();

            // Log failed routing
            if (isset($metadata['request_id']) && isset($metadata['worker_name'])) {
                $this->metadataLogger->log([
                    'request_id' => $metadata['request_id'],
                    'worker_name' => $metadata['worker_name'],
                    'timestamp' => new \DateTime(),
                    'source_topic' => $sourceTopic,
                    'target_topic' => $metadata['target_topic'] ?? 'unknown',
                    'route_id' => $metadata['route_id'] ?? 'unknown',
                    'message_type' => 'request',
                    'success' => false,
                    'error' => $errorMessage,
                    'processing_time_ms' => $processingTime,
                ]);
            }

            error_log("Routing failed: {$errorMessage}");
        }
    }

    /**
     * Initialize all components
     */
    private function initializeComponents(): void
    {
        $this->kafka = new KafkaWrapper($this->config['kafka']);
        $this->schemaRegistry = new SchemaRegistryManager(
            $this->config['cassandra']['contactPoints'],
            $this->config['cassandra']['localDataCenter'],
            $this->config['cassandra']['keyspace']
        );
        $this->metadataLogger = new MetadataLogger(
            $this->config['cassandra']['contactPoints'],
            $this->config['cassandra']['localDataCenter'],
            $this->config['cassandra']['keyspace']
        );
        $this->encryption = new EncryptionService($this->config['encryption']);
    }
}
