<?php

namespace App\Services;

use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;
use Splp\Messaging\Types\CommandCenterMessage;

class SplpMessagingService
{
    protected MessagingClient $messagingClient;
    protected KafkaWrapper $kafkaWrapper;
    protected Service1MessageProcessor $messageProcessor;
    protected array $config;

    public function __construct()
    {
        $this->config = config('splp');
        $this->initializeServices();
    }

    /**
     * Initialize SPLP services
     */
    protected function initializeServices(): void
    {
        // Create configurations
        $kafkaConfig = new KafkaConfig(
            brokers: $this->config['kafka']['brokers'],
            clientId: $this->config['kafka']['clientId'],
            groupId: $this->config['kafka']['groupId'],
            requestTimeoutMs: $this->config['kafka']['requestTimeoutMs'] ?? 30000,
            consumerTopic: $this->config['kafka']['consumerTopic'] ?? 'service-1-topic',
            producerTopic: $this->config['kafka']['producerTopic'] ?? 'command-center-inbox'
        );

        $cassandraConfig = new CassandraConfig(
            contactPoints: $this->config['cassandra']['contactPoints'],
            localDataCenter: $this->config['cassandra']['localDataCenter'],
            keyspace: $this->config['cassandra']['keyspace']
        );

        $encryptionConfig = new EncryptionConfig($this->config['encryption']['key']);

        // Create services
        $encryptionService = new EncryptionService($encryptionConfig->key);
        $logger = new CassandraLogger($cassandraConfig);
        
        $this->kafkaWrapper = new KafkaWrapper($kafkaConfig, $encryptionService, $logger);
        $this->messageProcessor = new Service1MessageProcessor(
            $encryptionService,
            $logger,
            $kafkaConfig,
            $this->kafkaWrapper,
            $this->config['service']['workerName'] ?? 'service-1-publisher'
        );

        // Initialize services
        $encryptionService->initialize();
        $logger->initialize();
        $this->kafkaWrapper->initialize();

        // Legacy MessagingClient for backward compatibility
        $this->messagingClient = new MessagingClient($this->config);
    }

    /**
     * Send population data for verification
     */
    public function sendPopulationData(array $data): array
    {
        try {
            // Add request ID
            $data['requestId'] = 'req_' . uniqid();
            
            // Send using legacy MessagingClient (for now)
            $response = $this->messagingClient->request('service-1-topic', $data);
            
            return [
                'requestId' => $data['requestId'],
                'status' => 'sent',
                'response' => $response,
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to send population data: ' . $e->getMessage());
        }
    }

    /**
     * Send message directly to Kafka topic
     */
    public function sendMessage(string $topic, array $data): array
    {
        try {
            $message = json_encode($data);
            $this->kafkaWrapper->sendMessage($topic, $message);
            
            return [
                'status' => 'sent',
                'topic' => $topic,
                'messageSize' => strlen($message),
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to send message to Kafka: ' . $e->getMessage());
        }
    }

    /**
     * Send Command Center message
     */
    public function sendCommandCenterMessage(string $requestId, array $data): array
    {
        try {
            // Encrypt the data
            $encryptionService = new EncryptionService($this->config['encryption']['key']);
            $encryptionService->initialize();
            $encryptedMessage = $encryptionService->encrypt($data, $requestId);

            // Create Command Center message
            $commandCenterMessage = new CommandCenterMessage(
                requestId: $requestId,
                workerName: $this->config['service']['workerName'] ?? 'service-1-publisher',
                data: $encryptedMessage->data,
                iv: $encryptedMessage->iv,
                tag: $encryptedMessage->tag
            );

            $messageBytes = $commandCenterMessage->toJson();
            $this->kafkaWrapper->sendMessage($this->config['kafka']['producerTopic'], $messageBytes);
            
            return [
                'status' => 'sent',
                'requestId' => $requestId,
                'workerName' => $this->config['service']['workerName'],
                'targetTopic' => $this->config['kafka']['producerTopic'],
                'messageSize' => strlen($messageBytes),
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to send Command Center message: ' . $e->getMessage());
        }
    }

    /**
     * Get health status of SPLP services
     */
    public function getHealthStatus(): array
    {
        try {
            $kafkaHealth = $this->kafkaWrapper->getHealthStatus();
            
            return [
                'kafka' => $kafkaHealth,
                'encryption' => [
                    'status' => 'initialized',
                    'algorithm' => 'AES-256-GCM'
                ],
                'cassandra' => [
                    'status' => 'initialized',
                    'keyspace' => $this->config['cassandra']['keyspace']
                ],
                'service' => [
                    'name' => $this->config['service']['name'],
                    'version' => $this->config['service']['version'],
                    'workerName' => $this->config['service']['workerName']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get SPLP configuration
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get Kafka wrapper instance
     */
    public function getKafkaWrapper(): KafkaWrapper
    {
        return $this->kafkaWrapper;
    }

    /**
     * Get message processor instance
     */
    public function getMessageProcessor(): Service1MessageProcessor
    {
        return $this->messageProcessor;
    }
}
