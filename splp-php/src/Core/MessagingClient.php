<?php

declare(strict_types=1);

namespace Splp\Messaging\Core;

use Splp\Messaging\Contracts\MessagingClientInterface;
use Splp\Messaging\Contracts\RequestHandlerInterface;
use Splp\Messaging\Core\Kafka\KafkaWrapper;
use Splp\Messaging\Core\Logging\CassandraLogger;
use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Core\Utils\RequestIdGenerator;
use Splp\Messaging\Core\Utils\CircuitBreaker;
use Splp\Messaging\Core\Utils\RetryManager;
use Splp\Messaging\Core\Monitoring\HealthChecker;
use Splp\Messaging\Exceptions\MessagingException;
use Splp\Messaging\Exceptions\TimeoutException;
use Splp\Messaging\Exceptions\EncryptionException;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Main Messaging Client
 * 
 * Provides single-line setup for Kafka Request-Reply messaging with:
 * - Automatic encryption/decryption
 * - Request ID generation
 * - Cassandra logging
 * - Circuit breaker protection
 * - Retry mechanism
 * - Health monitoring
 */
class MessagingClient implements MessagingClientInterface
{
    private KafkaWrapper $kafka;
    private CassandraLogger $logger;
    private EncryptionService $encryption;
    private CircuitBreaker $circuitBreaker;
    private RetryManager $retryManager;
    private HealthChecker $healthChecker;
    private LoggerInterface $log;
    
    private array $handlers = [];
    private array $pendingRequests = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $this->validateConfig($config);
        $this->initializeComponents();
    }

    /**
     * Initialize the messaging client - single line setup for users
     */
    public function initialize(): void
    {
        try {
            $this->kafka->connectProducer();
            $this->kafka->connectConsumer();
            $this->logger->initialize();
            
            $this->log->info('Messaging client initialized successfully');
        } catch (\Exception $e) {
            $this->log->error('Failed to initialize messaging client', [
                'error' => $e->getMessage()
            ]);
            throw new MessagingException('Failed to initialize messaging client: ' . $e->getMessage());
        }
    }

    /**
     * Register a handler for a specific topic
     * Users only need to register their handler function
     */
    public function registerHandler(string $topic, RequestHandlerInterface $handler): void
    {
        $this->handlers[$topic] = $handler;
        $this->log->info('Handler registered for topic', ['topic' => $topic]);
    }

    /**
     * Start consuming messages and processing with registered handlers
     */
    public function startConsuming(array $topics): void
    {
        $requestTopics = $topics;
        $replyTopic = $this->config['kafka']['clientId'] . '.replies';
        $allTopics = array_merge($requestTopics, [$replyTopic]);

        $this->kafka->subscribe($allTopics, function ($topic, $partition, $message) {
            try {
                if ($topic === $replyTopic) {
                    $this->handleReply($message['value'] ?? '');
                } else {
                    $this->handleRequest($topic, $message['value'] ?? '');
                }
            } catch (\Exception $e) {
                $this->log->error('Error processing message', [
                    'topic' => $topic,
                    'error' => $e->getMessage()
                ]);
            }
        });

        $this->log->info('Started consuming from topics', ['topics' => $allTopics]);
    }

    /**
     * Send a request with automatic encryption and wait for reply
     * Returns the decrypted response
     */
    public function request(string $topic, mixed $payload, int $timeoutMs = 30000): mixed
    {
        $requestId = RequestIdGenerator::generate();
        $startTime = microtime(true);

        // Create promise for reply
        $replyPromise = $this->createReplyPromise($requestId, $timeoutMs);

        try {
            // Encrypt payload
            $encryptedMessage = $this->encryption->encryptPayload($payload, $requestId);

            // Log request
            $this->logger->log([
                'request_id' => $requestId,
                'timestamp' => new \DateTime(),
                'type' => 'request',
                'topic' => $topic,
                'payload' => $payload,
            ]);

            // Send to Kafka with circuit breaker protection
            $replyTopic = $this->config['kafka']['clientId'] . '.replies';
            $messageWithReplyTopic = array_merge($encryptedMessage, ['replyTopic' => $replyTopic]);

            $this->circuitBreaker->execute(function () use ($topic, $messageWithReplyTopic, $requestId) {
                $this->kafka->sendMessage($topic, json_encode($messageWithReplyTopic), $requestId);
            });

            $this->log->info('Request sent', [
                'request_id' => $requestId,
                'topic' => $topic
            ]);

            return $replyPromise;
        } catch (\Exception $e) {
            unset($this->pendingRequests[$requestId]);
            $this->log->error('Failed to send request', [
                'request_id' => $requestId,
                'topic' => $topic,
                'error' => $e->getMessage()
            ]);
            throw new MessagingException('Failed to send request: ' . $e->getMessage());
        }
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): array
    {
        return $this->healthChecker->getHealthStatus();
    }

    /**
     * Close all connections
     */
    public function close(): void
    {
        $this->kafka->disconnect();
        $this->logger->close();
        $this->log->info('Messaging client closed');
    }

    /**
     * Handle incoming request - decrypt, process with handler, encrypt and send reply
     */
    private function handleRequest(string $topic, string $messageValue): void
    {
        $startTime = microtime(true);
        $requestId = '';

        try {
            // Parse message
            $encryptedMessage = json_decode($messageValue, true);
            $requestId = $encryptedMessage['request_id'];

            // Decrypt payload
            $decryptedData = $this->encryption->decryptPayload($encryptedMessage);
            $payload = $decryptedData['payload'];

            $this->log->info('Request received', [
                'request_id' => $requestId,
                'topic' => $topic
            ]);

            // Find handler
            if (!isset($this->handlers[$topic])) {
                throw new MessagingException("No handler registered for topic: {$topic}");
            }

            $handler = $this->handlers[$topic];

            // Process with handler using retry mechanism
            $responsePayload = $this->retryManager->execute(function () use ($handler, $requestId, $payload) {
                return $handler->handle($requestId, $payload);
            });

            // Create response
            $response = [
                'request_id' => $requestId,
                'payload' => $responsePayload,
                'timestamp' => time(),
                'success' => true,
            ];

            // Encrypt response
            $encryptedResponse = $this->encryption->encryptPayload($response, $requestId);

            // Send reply to reply topic
            $replyTopic = $encryptedMessage['replyTopic'] ?? $topic . '.replies';
            $this->kafka->sendMessage($replyTopic, json_encode($encryptedResponse), $requestId);

            // Log response
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->log([
                'request_id' => $requestId,
                'timestamp' => new \DateTime(),
                'type' => 'response',
                'topic' => $topic,
                'payload' => $responsePayload,
                'success' => true,
                'duration_ms' => $duration,
            ]);

            $this->log->info('Response sent', [
                'request_id' => $requestId,
                'duration_ms' => $duration
            ]);
        } catch (\Exception $e) {
            // Log error
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->log([
                'request_id' => $requestId,
                'timestamp' => new \DateTime(),
                'type' => 'response',
                'topic' => $topic,
                'payload' => null,
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            $this->log->error('Error handling request', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle incoming reply - decrypt and resolve pending request
     */
    private function handleReply(string $messageValue): void
    {
        try {
            // Parse encrypted message
            $encryptedMessage = json_decode($messageValue, true);
            $requestId = $encryptedMessage['request_id'];

            // Decrypt response
            $decryptedData = $this->encryption->decryptPayload($encryptedMessage);
            $response = $decryptedData['payload'];

            $this->log->info('Reply received', ['request_id' => $requestId]);

            // Find pending request
            if (isset($this->pendingRequests[$requestId])) {
                $pendingRequest = $this->pendingRequests[$requestId];
                unset($this->pendingRequests[$requestId]);

                // Log response
                $duration = (microtime(true) - $pendingRequest['startTime']) * 1000;
                $this->logger->log([
                    'request_id' => $requestId,
                    'timestamp' => new \DateTime(),
                    'type' => 'response',
                    'topic' => 'reply',
                    'payload' => $response['payload'],
                    'success' => $response['success'],
                    'error' => $response['error'] ?? null,
                    'duration_ms' => $duration,
                ]);

                // Resolve or reject promise
                if ($response['success']) {
                    $pendingRequest['resolve']($response['payload']);
                } else {
                    $pendingRequest['reject'](new MessagingException($response['error'] ?? 'Request failed'));
                }
            }
        } catch (\Exception $e) {
            $this->log->error('Error handling reply', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create reply promise with timeout
     */
    private function createReplyPromise(string $requestId, int $timeoutMs): mixed
    {
        return new \React\Promise\Promise(function ($resolve, $reject) use ($requestId, $timeoutMs) {
            $this->pendingRequests[$requestId] = [
                'resolve' => $resolve,
                'reject' => $reject,
                'startTime' => microtime(true)
            ];

            // Set timeout
            \React\EventLoop\Loop::addTimer($timeoutMs / 1000, function () use ($requestId) {
                if (isset($this->pendingRequests[$requestId])) {
                    unset($this->pendingRequests[$requestId]);
                    throw new TimeoutException("Request timeout after {$timeoutMs}ms");
                }
            });
        });
    }

    /**
     * Initialize all components
     */
    private function initializeComponents(): void
    {
        // Initialize logger
        $this->log = new Logger('splp-messaging');
        $this->log->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

        // Initialize Kafka wrapper
        $this->kafka = new KafkaWrapper($this->config['kafka']);

        // Initialize Cassandra logger
        $this->logger = new CassandraLogger($this->config['cassandra']);

        // Initialize encryption service
        $this->encryption = new EncryptionService($this->config['encryption']);

        // Initialize circuit breaker
        $this->circuitBreaker = new CircuitBreaker([
            'failureThreshold' => 5,
            'recoveryTimeout' => 30000,
            'monitoringPeriod' => 60000
        ]);

        // Initialize retry manager
        $this->retryManager = new RetryManager([
            'maxAttempts' => 3,
            'baseDelay' => 1000,
            'maxDelay' => 10000,
            'backoffMultiplier' => 2,
            'jitter' => true
        ]);

        // Initialize health checker
        $this->healthChecker = new HealthChecker([
            'checkInterval' => 30000,
            'timeout' => 5000,
            'criticalChecks' => ['kafka', 'cassandra']
        ]);

        // Register health checks
        $this->healthChecker->registerCheck('kafka', function () {
            return $this->kafka->healthCheck();
        });

        $this->healthChecker->registerCheck('cassandra', function () {
            return $this->logger->healthCheck();
        });
    }

    /**
     * Validate configuration
     */
    private function validateConfig(array $config): array
    {
        $required = ['kafka', 'cassandra', 'encryption'];
        
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new MessagingException("Missing required config: {$key}");
            }
        }

        return $config;
    }
}
