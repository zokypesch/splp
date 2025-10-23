<?php

namespace Splp\Messaging\Core;

use Splp\Messaging\Contracts\KafkaClientInterface;
use Splp\Messaging\Contracts\MessageProcessorInterface;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\EncryptedMessage;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Utils\RequestIdGenerator;
use Splp\Messaging\Utils\CircuitBreaker;
use Splp\Messaging\Utils\RetryManager;

class KafkaWrapper implements KafkaClientInterface
{
    private KafkaConfig $config;
    private ?MessageProcessorInterface $messageHandler = null;
    private EncryptionService $encryptionService;
    private CassandraLogger $logger;
    private RequestIdGenerator $requestIdGenerator;
    private CircuitBreaker $circuitBreaker;
    private RetryManager $retryManager;
    private bool $initialized = false;
    private bool $consuming = false;
    private array $topics = [];
    
    // Real Kafka objects (only if rdkafka extension is available)
    private $producer = null;
    private $consumer = null;
    private $producerConf = null;
    private $consumerConf = null;
    
    // Check if rdkafka extension is available
    private bool $rdkafkaAvailable = false;

    public function __construct(KafkaConfig $config, EncryptionService $encryptionService, CassandraLogger $logger)
    {
        $this->config = $config;
        $this->encryptionService = $encryptionService;
        $this->logger = $logger;
        $this->requestIdGenerator = new RequestIdGenerator();
        $this->circuitBreaker = new CircuitBreaker();
        $this->retryManager = new RetryManager();
        
        // Check if rdkafka extension is available
        $this->rdkafkaAvailable = extension_loaded('rdkafka');
        
        if (!$this->rdkafkaAvailable) {
            echo "⚠️  rdkafka extension not available, using simulation mode\n";
            echo "   To enable real Kafka integration, install rdkafka extension:\n";
            echo "   brew install librdkafka\n";
            echo "   pecl install rdkafka\n";
            echo "   echo 'extension=rdkafka.so' >> /usr/local/etc/php/8.2/php.ini\n\n";
        }
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        if ($this->rdkafkaAvailable) {
            echo "🔧 Initializing Real Kafka Wrapper...\n";
            echo "   - Brokers: " . implode(', ', $this->config->brokers) . "\n";
            echo "   - Client ID: {$this->config->clientId}\n";
            echo "   - Group ID: {$this->config->groupId}\n";
            echo "   - Request Timeout: {$this->config->requestTimeoutMs}ms\n";

            try {
                // Initialize Producer
                $this->initializeProducer();
                
                // Initialize Consumer
                $this->initializeConsumer();
                
                $this->initialized = true;
                echo "✅ Real Kafka Wrapper initialized successfully\n";
                
            } catch (\Exception $e) {
                echo "❌ Failed to initialize Kafka: " . $e->getMessage() . "\n";
                throw $e;
            }
        } else {
            echo "🔧 Initializing Simulation Kafka Wrapper...\n";
            echo "   - Brokers: " . implode(', ', $this->config->brokers) . "\n";
            echo "   - Client ID: {$this->config->clientId}\n";
            echo "   - Group ID: {$this->config->groupId}\n";
            echo "   - Request Timeout: {$this->config->requestTimeoutMs}ms\n";
            echo "   - Mode: SIMULATION (rdkafka not available)\n";
            
            $this->initialized = true;
            echo "✅ Simulation Kafka Wrapper initialized successfully\n";
        }
    }

    private function initializeProducer(): void
    {
        /** @var \RdKafka\Conf $this->producerConf */
        $this->producerConf = new \RdKafka\Conf();
        
        // Set producer configuration
        $this->producerConf->set('metadata.broker.list', implode(',', $this->config->brokers));
        $this->producerConf->set('client.id', $this->config->clientId . '-producer');
        $this->producerConf->set('acks', 'all'); // Wait for all replicas
        $this->producerConf->set('retries', '5');
        $this->producerConf->set('retry.backoff.ms', '100');
        $this->producerConf->set('message.timeout.ms', (string)$this->config->requestTimeoutMs);
        
        // Set error callback
        $this->producerConf->setErrorCb(function ($kafka, $err, $reason) {
            echo "❌ Producer error: {$err} - {$reason}\n";
        });
        
        // Set delivery report callback
        $this->producerConf->setDrMsgCb(function ($kafka, $message) {
            if ($message->err === \RD_KAFKA_RESP_ERR_NO_ERROR) {
                echo "✅ Message delivered to topic {$message->topic_name}, partition {$message->partition}, offset {$message->offset}\n";
            } else {
                echo "❌ Message delivery failed: {$message->errstr()}\n";
            }
        });

        $this->producer = new \RdKafka\Producer($this->producerConf);
        echo "✅ Kafka Producer initialized\n";
    }

    private function initializeConsumer(): void
    {
        /** @var \RdKafka\Conf $this->consumerConf */
        $this->consumerConf = new \RdKafka\Conf();
        
        // Set consumer configuration
        $this->consumerConf->set('metadata.broker.list', implode(',', $this->config->brokers));
        $this->consumerConf->set('group.id', $this->config->groupId);
        $this->consumerConf->set('client.id', $this->config->clientId . '-consumer');
        $this->consumerConf->set('auto.offset.reset', 'earliest');
        $this->consumerConf->set('enable.auto.commit', 'true');
        $this->consumerConf->set('auto.commit.interval.ms', '1000');
        $this->consumerConf->set('session.timeout.ms', '30000');
        $this->consumerConf->set('heartbeat.interval.ms', '3000');
        
        // Set error callback
        $this->consumerConf->setErrorCb(function ($kafka, $err, $reason) {
            echo "❌ Consumer error: {$err} - {$reason}\n";
        });
        
        // Set rebalance callback
        $this->consumerConf->setRebalanceCb(function (\RdKafka\KafkaConsumer $kafka, $err, array $partitions = null) {
            switch ($err) {
                case \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    echo "📋 Assigning partitions: " . count($partitions) . " partitions\n";
                    $kafka->assign($partitions);
                    break;
                case \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    echo "📋 Revoking partitions: " . count($partitions) . " partitions\n";
                    $kafka->assign(null);
                    break;
                default:
                    echo "❌ Rebalance error: {$err}\n";
                    $kafka->assign(null);
                    break;
            }
        });

        $this->consumer = new \RdKafka\KafkaConsumer($this->consumerConf);
        echo "✅ Kafka Consumer initialized\n";
    }

    public function setMessageHandler(MessageProcessorInterface $handler): void
    {
        $this->messageHandler = $handler;
        echo "📝 Message handler set: " . get_class($handler) . "\n";
    }

    public function startConsuming(array $topics): void
    {
        if (!$this->initialized) {
            throw new \Exception("Kafka Wrapper not initialized");
        }
        if ($this->messageHandler === null) {
            throw new \Exception("Message handler not set");
        }

        $this->topics = $topics;
        $this->consuming = true;

        if ($this->rdkafkaAvailable) {
            echo "🔄 Starting real consumption for topics: " . implode(', ', $topics) . "\n";
            echo "✅ Kafka consumer started - waiting for real messages\n";
            echo "Press Ctrl+C to stop.\n\n";

            // Subscribe to topics
            $this->consumer->subscribe($topics);

            // Start consuming loop
            $this->consumerLoop();
        } else {
            echo "🔄 Starting simulation consumption for topics: " . implode(', ', $topics) . "\n";
            echo "✅ Simulation consumer started - waiting for simulated messages\n";
            echo "Press Ctrl+C to stop.\n\n";

            // Start simulation consuming loop
            $this->simulationConsumerLoop();
        }
    }

    private function consumerLoop(): void
    {
        while ($this->consuming) {
            try {
                $message = $this->consumer->consume(1000); // 1 second timeout
                
                if ($message === null) {
                    continue; // No message, continue polling
                }
                
                switch ($message->err) {
                    case \RD_KAFKA_RESP_ERR_NO_ERROR:
                        // Successfully received message
                        echo "📥 Received message from topic: {$message->topic_name}, partition: {$message->partition}, offset: {$message->offset}\n";
                        
                        // Create EncryptedMessage from received data
                        $messageData = json_decode($message->payload, true);
                        if ($messageData === null) {
                            echo "❌ Failed to decode message JSON\n";
                            break;
                        }
                        
                        $encryptedMessage = new EncryptedMessage(
                            $messageData['data'] ?? '',
                            $messageData['iv'] ?? '',
                            $messageData['tag'] ?? ''
                        );
                        
                        // Process the message
                        if ($this->messageHandler) {
                            try {
                                $this->messageHandler->processMessage($encryptedMessage);
                            } catch (\Exception $e) {
                                echo "❌ Error in message handler: " . $e->getMessage() . "\n";
                                $this->logger->logError("Error in message handler: " . $e->getMessage(), [
                                    'topic' => $message->topic_name,
                                    'partition' => $message->partition,
                                    'offset' => $message->offset,
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                        break;
                        
                    case \RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        // End of partition, continue polling
                        break;
                        
                    case \RD_KAFKA_RESP_ERR__TIMED_OUT:
                        // Timeout, continue polling
                        break;
                        
                    default:
                        echo "❌ Consumer error: {$message->err} - {$message->errstr()}\n";
                        break;
                }
                
            } catch (\Exception $e) {
                echo "❌ Consumer loop error: " . $e->getMessage() . "\n";
                usleep(1000000); // Wait 1 second before retrying
            }
        }
    }

    private function simulationConsumerLoop(): void
    {
        while ($this->consuming) {
            // Simulate message reception every few seconds
            usleep(2000000); // 2 seconds
            if ($this->consuming) { // Check again in case shutdown was requested during usleep
                $this->simulateIncomingMessage();
            }
        }
    }

    private function simulateIncomingMessage(): void
    {
        $requestId = $this->requestIdGenerator->generate();
        
        // Simulate encrypted message from Command Center
        $mockPayload = [
            'registrationId' => 'REG_' . uniqid(),
            'nik' => '317' . str_pad(rand(1, 999999999999), 13, '0', STR_PAD_LEFT),
            'fullName' => 'John Doe',
            'dateOfBirth' => '1990-01-01',
            'address' => 'Jakarta Selatan',
            'assistanceType' => 'Bansos',
            'requestedAmount' => 500000
        ];

        $encryptedMessage = $this->encryptionService->encrypt($mockPayload, $requestId);
        
        echo "📥 Simulated message received from Command Center\n";
        echo "   Request ID: {$requestId}\n";
        echo "   Topic: service-1-topic\n\n";

        // Process the message
        if ($this->messageHandler) {
            try {
                $this->messageHandler->processMessage($encryptedMessage);
            } catch (\Exception $e) {
                echo "❌ Error in message handler: " . $e->getMessage() . "\n";
                $this->logger->logError("Error in message handler: " . $e->getMessage(), [
                    'request_id' => $requestId,
                    'topic' => 'service-1-topic',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public function sendMessage(string $topic, string $message): void
    {
        if (!$this->initialized) {
            throw new \Exception("Kafka Wrapper not initialized");
        }

        if (!$this->circuitBreaker->isAvailable()) {
            throw new \Exception("Circuit breaker is open");
        }

        echo "📤 Sending message to topic: {$topic}\n";
        echo "📦 Message: " . substr($message, 0, 100) . "...\n";

        if ($this->rdkafkaAvailable) {
            // Real Kafka publish implementation
            $this->retryManager->execute(function() use ($topic, $message) {
                try {
                    if ($this->producer === null) {
                        throw new \Exception("Producer not initialized");
                    }

                    // Create topic producer
                    $topicProducer = $this->producer->newTopic($topic);
                    
                    // Produce message
                    $topicProducer->produce(\RD_KAFKA_PARTITION_UA, 0, $message);
                    
                    // Wait for delivery (flush)
                    $result = $this->producer->flush(5000); // 5 second timeout
                    if ($result !== 0) {
                        throw new \Exception("Failed to flush producer, timeout reached");
                    }
                    
                    // Log the message to Cassandra
                    $this->logger->logMessage($topic, $message, 'sent');
                    
                    echo "✅ Message published to Kafka topic: {$topic}\n";
                    echo "📊 Message size: " . strlen($message) . " bytes\n";
                    
                    // Record success for circuit breaker
                    $this->circuitBreaker->recordSuccess();
                    
                } catch (\Exception $e) {
                    echo "❌ Failed to publish message: " . $e->getMessage() . "\n";
                    $this->circuitBreaker->recordFailure();
                    throw $e;
                }
            });
        } else {
            // Simulation mode
            $this->retryManager->execute(function() use ($topic, $message) {
                try {
                    // Simulate network delay
                    usleep(50000); // 50ms
                    
                    // Log the message to Cassandra
                    $this->logger->logMessage($topic, $message, 'sent');
                    
                    // Simulate successful publish
                    echo "✅ Message published to Kafka topic: {$topic} (SIMULATION)\n";
                    echo "📊 Message size: " . strlen($message) . " bytes\n";
                    
                    // Record success for circuit breaker
                    $this->circuitBreaker->recordSuccess();
                    
                } catch (\Exception $e) {
                    echo "❌ Failed to publish message: " . $e->getMessage() . "\n";
                    $this->circuitBreaker->recordFailure();
                    throw $e;
                }
            });
        }
    }

    public function getHealthStatus(): array
    {
        return [
            'status' => 'healthy',
            'initialized' => $this->initialized,
            'consuming' => $this->consuming,
            'topics' => $this->topics,
            'circuit_breaker_state' => $this->circuitBreaker->getState(),
            'rdkafka_available' => $this->rdkafkaAvailable,
            'mode' => $this->rdkafkaAvailable ? 'real_kafka' : 'simulation',
            'producer_initialized' => $this->producer !== null,
            'consumer_initialized' => $this->consumer !== null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function close(): void
    {
        $this->consuming = false;
        
        if ($this->consumer !== null) {
            $this->consumer->close();
            $this->consumer = null;
            echo "🔒 Kafka Consumer closed\n";
        }
        
        if ($this->producer !== null) {
            $this->producer->flush(1000); // Final flush
            $this->producer = null;
            echo "🔒 Kafka Producer closed\n";
        }
        
        $this->initialized = false;
        echo "🔒 Real Kafka Wrapper closed\n";
    }
}