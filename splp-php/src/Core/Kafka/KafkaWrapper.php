<?php

declare(strict_types=1);

namespace Splp\Messaging\Core\Kafka;

use Splp\Messaging\Exceptions\MessagingException;
use RdKafka\Producer;
use RdKafka\Consumer;
use RdKafka\TopicConf;
use RdKafka\Conf;

/**
 * Kafka Wrapper
 * 
 * Provides simplified Kafka operations with automatic connection management
 */
class KafkaWrapper
{
    private Producer $producer;
    private Consumer $consumer;
    private array $config;
    private bool $producerConnected = false;
    private bool $consumerConnected = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeProducer();
        $this->initializeConsumer();
    }

    /**
     * Connect producer
     */
    public function connectProducer(): void
    {
        if (!$this->producerConnected) {
            $this->producer->connect();
            $this->producerConnected = true;
        }
    }

    /**
     * Connect consumer
     */
    public function connectConsumer(): void
    {
        if (!$this->consumerConnected) {
            $this->consumer->connect();
            $this->consumerConnected = true;
        }
    }

    /**
     * Send message to topic
     */
    public function sendMessage(string $topic, string $message, ?string $key = null): void
    {
        if (!$this->producerConnected) {
            throw new MessagingException('Producer not connected');
        }

        $kafkaTopic = $this->producer->newTopic($topic);
        $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, $message, $key);
        $this->producer->flush(1000);
    }

    /**
     * Subscribe to topics and process messages
     */
    public function subscribe(array $topics, callable $callback): void
    {
        if (!$this->consumerConnected) {
            throw new MessagingException('Consumer not connected');
        }

        foreach ($topics as $topic) {
            $this->consumer->subscribe([$topic]);
        }

        while (true) {
            $message = $this->consumer->consume(1000);
            
            if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
                $callback($message->topic_name, $message->partition, [
                    'value' => $message->payload,
                    'key' => $message->key,
                    'offset' => $message->offset,
                    'timestamp' => $message->timestamp
                ]);
            }
        }
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        $metadata = $this->producer->getMetadata(true, null, 1000);
        return [
            'brokers' => $metadata->getBrokers(),
            'topics' => $metadata->getTopics()
        ];
    }

    /**
     * Health check
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);
        
        try {
            $metadata = $this->getMetadata();
            
            return [
                'status' => 'healthy',
                'responseTime' => (microtime(true) - $startTime) * 1000,
                'lastChecked' => new \DateTime(),
                'metadata' => [
                    'brokers' => count($metadata['brokers']),
                    'topics' => count($metadata['topics'])
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'responseTime' => (microtime(true) - $startTime) * 1000,
                'lastChecked' => new \DateTime()
            ];
        }
    }

    /**
     * Disconnect all connections
     */
    public function disconnect(): void
    {
        if ($this->producerConnected) {
            $this->producer->flush(1000);
            $this->producerConnected = false;
        }
        
        if ($this->consumerConnected) {
            $this->consumer->unsubscribe();
            $this->consumerConnected = false;
        }
    }

    /**
     * Initialize producer
     */
    private function initializeProducer(): void
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', implode(',', $this->config['brokers']));
        $conf->set('client.id', $this->config['clientId']);
        
        $this->producer = new Producer($conf);
    }

    /**
     * Initialize consumer
     */
    private function initializeConsumer(): void
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', implode(',', $this->config['brokers']));
        $conf->set('group.id', $this->config['groupId'] ?? $this->config['clientId'] . '-group');
        $conf->set('auto.offset.reset', 'earliest');
        
        $this->consumer = new Consumer($conf);
    }
}
