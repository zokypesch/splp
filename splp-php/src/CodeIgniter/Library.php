<?php

declare(strict_types=1);

namespace Splp\Messaging\CodeIgniter;

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\CommandCenter\CommandCenter;
use Splp\Messaging\Exceptions\ConfigurationException;

/**
 * CodeIgniter Library for SPLP Messaging
 */
class SplpMessagingLibrary
{
    private MessagingClient $messagingClient;
    private CommandCenter $commandCenter;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $this->loadConfig($config);
        $this->initializeComponents();
    }

    /**
     * Initialize messaging client
     */
    public function initialize(): void
    {
        $this->messagingClient->initialize();
    }

    /**
     * Register handler
     */
    public function registerHandler(string $topic, callable $handler): void
    {
        $this->messagingClient->registerHandler($topic, new CallableHandler($handler));
    }

    /**
     * Start consuming
     */
    public function startConsuming(array $topics): void
    {
        $this->messagingClient->startConsuming($topics);
    }

    /**
     * Send request
     */
    public function request(string $topic, mixed $payload, int $timeoutMs = 30000): mixed
    {
        return $this->messagingClient->request($topic, $payload, $timeoutMs);
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): array
    {
        return $this->messagingClient->getHealthStatus();
    }

    /**
     * Close connections
     */
    public function close(): void
    {
        $this->messagingClient->close();
    }

    /**
     * Initialize Command Center
     */
    public function initializeCommandCenter(): void
    {
        $this->commandCenter->initialize();
    }

    /**
     * Start Command Center
     */
    public function startCommandCenter(): void
    {
        $this->commandCenter->start();
    }

    /**
     * Register route
     */
    public function registerRoute(array $routeConfig): void
    {
        $this->commandCenter->registerRoute($routeConfig);
    }

    /**
     * Get schema registry
     */
    public function getSchemaRegistry()
    {
        return $this->commandCenter->getSchemaRegistry();
    }

    /**
     * Get metadata logger
     */
    public function getMetadataLogger()
    {
        return $this->commandCenter->getMetadataLogger();
    }

    /**
     * Shutdown Command Center
     */
    public function shutdownCommandCenter(): void
    {
        $this->commandCenter->shutdown();
    }

    /**
     * Load configuration
     */
    private function loadConfig(array $config): array
    {
        // Try to load from config file
        if (file_exists(APPPATH . 'config/splp.php')) {
            $fileConfig = include APPPATH . 'config/splp.php';
            $config = array_merge($fileConfig, $config);
        }

        // Load from environment variables
        $config['kafka']['brokers'] = $config['kafka']['brokers'] ?? explode(',', getenv('SPLP_KAFKA_BROKERS') ?: 'localhost:9092');
        $config['kafka']['clientId'] = $config['kafka']['clientId'] ?? getenv('SPLP_KAFKA_CLIENT_ID') ?: 'codeigniter-app';
        $config['kafka']['groupId'] = $config['kafka']['groupId'] ?? getenv('SPLP_KAFKA_GROUP_ID') ?: 'codeigniter-app-group';

        $config['cassandra']['contactPoints'] = $config['cassandra']['contactPoints'] ?? explode(',', getenv('SPLP_CASSANDRA_CONTACT_POINTS') ?: 'localhost');
        $config['cassandra']['localDataCenter'] = $config['cassandra']['localDataCenter'] ?? getenv('SPLP_CASSANDRA_LOCAL_DATA_CENTER') ?: 'datacenter1';
        $config['cassandra']['keyspace'] = $config['cassandra']['keyspace'] ?? getenv('SPLP_CASSANDRA_KEYSPACE') ?: 'codeigniter_keyspace';

        $config['encryption']['encryptionKey'] = $config['encryption']['encryptionKey'] ?? getenv('SPLP_ENCRYPTION_KEY') ?: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        return $config;
    }

    /**
     * Initialize components
     */
    private function initializeComponents(): void
    {
        $this->messagingClient = new MessagingClient($this->config);
        $this->commandCenter = new CommandCenter($this->config);
    }
}

/**
 * Callable Handler Wrapper
 */
class CallableHandler implements \Splp\Messaging\Contracts\RequestHandlerInterface
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function handle(string $requestId, mixed $payload): mixed
    {
        return call_user_func($this->callable, $requestId, $payload);
    }
}
