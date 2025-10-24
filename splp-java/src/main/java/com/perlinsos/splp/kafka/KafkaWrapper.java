package com.perlinsos.splp.kafka;

import com.perlinsos.splp.types.KafkaConfig;
import org.apache.kafka.clients.admin.AdminClient;
import org.apache.kafka.clients.admin.CreateTopicsResult;
import org.apache.kafka.clients.admin.NewTopic;
import org.apache.kafka.clients.consumer.ConsumerConfig;
import org.apache.kafka.clients.consumer.ConsumerRecord;
import org.apache.kafka.clients.consumer.ConsumerRecords;
import org.apache.kafka.clients.consumer.KafkaConsumer;
import org.apache.kafka.clients.producer.KafkaProducer;
import org.apache.kafka.clients.producer.ProducerConfig;
import org.apache.kafka.clients.producer.ProducerRecord;
import org.apache.kafka.common.serialization.StringDeserializer;
import org.apache.kafka.common.serialization.StringSerializer;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.time.Duration;
import java.util.*;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.function.Consumer;

/**
 * Kafka Wrapper for managing producers and consumers
 */
public class KafkaWrapper {
    private static final Logger logger = LoggerFactory.getLogger(KafkaWrapper.class);
    private static final AtomicInteger instanceCounter = new AtomicInteger(0);

    private final KafkaConfig config;
    private final int instanceId;
    private KafkaProducer<String, String> producer;
    private KafkaConsumer<String, String> consumer;
    private AdminClient admin;
    private final AtomicBoolean isConsuming = new AtomicBoolean(false);
    private ExecutorService consumerExecutor;

    public KafkaWrapper(KafkaConfig config) {
        this.config = config;
        this.instanceId = instanceCounter.incrementAndGet();
        this.admin = AdminClient.create(createAdminProperties());
        logger.info("KafkaWrapper#{} initialized with brokers: {}", instanceId, config.getBrokers());
    }

    /**
     * Connect producer
     */
    public CompletableFuture<KafkaProducer<String, String>> connectProducer() {
        return CompletableFuture.supplyAsync(() -> {
            if (producer != null) {
                return producer;
            }

            Properties props = createProducerProperties();
            producer = new KafkaProducer<>(props);
            logger.info("KafkaWrapper#{} Producer connected", instanceId);
            return producer;
        });
    }

    /**
     * Connect consumer with optional group ID override
     */
    public CompletableFuture<KafkaConsumer<String, String>> connectConsumer(String groupId) {
        return CompletableFuture.supplyAsync(() -> {
            if (consumer != null) {
                return consumer;
            }

            Properties props = createConsumerProperties(groupId != null ? groupId : config.getGroupId());
            consumer = new KafkaConsumer<>(props);
            logger.info("KafkaWrapper#{} Consumer connected with group: {}", instanceId, 
                       groupId != null ? groupId : config.getGroupId());
            return consumer;
        });
    }

    /**
     * Connect consumer with default group ID
     */
    public CompletableFuture<KafkaConsumer<String, String>> connectConsumer() {
        return connectConsumer(null);
    }

    /**
     * Get producer (throws if not connected)
     */
    public KafkaProducer<String, String> getProducer() {
        if (producer == null) {
            throw new IllegalStateException("Producer not connected. Call connectProducer() first.");
        }
        return producer;
    }

    /**
     * Get consumer (throws if not connected)
     */
    public KafkaConsumer<String, String> getConsumer() {
        if (consumer == null) {
            throw new IllegalStateException("Consumer not connected. Call connectConsumer() first.");
        }
        return consumer;
    }

    /**
     * Send a message to a topic
     */
    public CompletableFuture<Void> sendMessage(String topic, String message, String key) {
        return CompletableFuture.runAsync(() -> {
            KafkaProducer<String, String> prod = getProducer();
            ProducerRecord<String, String> record = new ProducerRecord<>(topic, key, message);
            
            try {
                prod.send(record).get(); // Wait for send to complete
            } catch (Exception e) {
                throw new RuntimeException("Failed to send message to topic: " + topic, e);
            }
        });
    }

    /**
     * Send a message to a topic without key
     */
    public CompletableFuture<Void> sendMessage(String topic, String message) {
        return sendMessage(topic, message, null);
    }

    /**
     * Subscribe to topics and process messages
     */
    public CompletableFuture<Void> subscribe(List<String> topics, MessageHandler messageHandler) {
        return CompletableFuture.runAsync(() -> {
            KafkaConsumer<String, String> cons = getConsumer();

            // Prevent calling consumer.run() multiple times
            if (isConsuming.get()) {
                logger.warn("KafkaWrapper#{} Consumer is already running. Ignoring duplicate subscribe() call.", instanceId);
                return;
            }

            // Set flag BEFORE starting consumption to prevent race condition
            isConsuming.set(true);

            // Subscribe to topics
            cons.subscribe(topics);
            logger.info("KafkaWrapper#{} Subscribed to topics: {}", instanceId, String.join(", ", topics));

            // Start consuming in a separate thread
            consumerExecutor = Executors.newSingleThreadExecutor();
            consumerExecutor.submit(() -> {
                try {
                    while (isConsuming.get() && !Thread.currentThread().isInterrupted()) {
                        ConsumerRecords<String, String> records = cons.poll(Duration.ofMillis(100));
                        
                        for (ConsumerRecord<String, String> record : records) {
                            try {
                                MessagePayload payload = new MessagePayload(
                                    record.topic(),
                                    record.partition(),
                                    record.offset(),
                                    record.key(),
                                    record.value(),
                                    record.timestamp()
                                );
                                messageHandler.handle(payload);
                            } catch (Exception e) {
                                logger.error("Error processing message from topic {}: {}", record.topic(), e.getMessage(), e);
                            }
                        }
                    }
                } catch (Exception e) {
                    if (!Thread.currentThread().isInterrupted()) {
                        logger.error("Consumer polling error: {}", e.getMessage(), e);
                    }
                }
            });
        });
    }

    /**
     * Create topics if they don't exist
     */
    public CompletableFuture<Void> createTopics(List<String> topics) {
        return CompletableFuture.runAsync(() -> {
            try {
                List<NewTopic> newTopics = new ArrayList<>();
                for (String topic : topics) {
                    newTopics.add(new NewTopic(topic, 3, (short) 1)); // 3 partitions, replication factor 1
                }

                CreateTopicsResult result = admin.createTopics(newTopics);
                result.all().get(); // Wait for completion

                logger.info("Topics created: {}", String.join(", ", topics));
            } catch (Exception e) {
                if (e.getMessage().contains("already exists")) {
                    logger.info("Topics already exist");
                } else {
                    throw new RuntimeException("Failed to create topics", e);
                }
            }
        });
    }

    /**
     * Disconnect all connections
     */
    public CompletableFuture<Void> disconnect() {
        return CompletableFuture.runAsync(() -> {
            try {
                // Stop consuming
                isConsuming.set(false);
                
                if (consumerExecutor != null) {
                    consumerExecutor.shutdown();
                }

                if (producer != null) {
                    producer.close();
                    logger.info("Producer disconnected");
                    producer = null;
                }

                if (consumer != null) {
                    consumer.close();
                    logger.info("Consumer disconnected");
                    consumer = null;
                }

                if (admin != null) {
                    admin.close();
                }
            } catch (Exception e) {
                logger.error("Error during disconnect: {}", e.getMessage(), e);
            }
        });
    }

    /**
     * Check if producer is connected
     */
    public boolean isProducerConnected() {
        return producer != null;
    }

    /**
     * Check if consumer is connected
     */
    public boolean isConsumerConnected() {
        return consumer != null;
    }
    private Properties createProducerProperties() {
        Properties props = new Properties();
        props.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, String.join(",", config.getBrokers()));
        props.put(ProducerConfig.CLIENT_ID_CONFIG, config.getClientId());
        props.put(ProducerConfig.KEY_SERIALIZER_CLASS_CONFIG, "org.apache.kafka.common.serialization.StringSerializer");
        props.put(ProducerConfig.VALUE_SERIALIZER_CLASS_CONFIG, "org.apache.kafka.common.serialization.StringSerializer");
        props.put(ProducerConfig.ACKS_CONFIG, "all");
        props.put(ProducerConfig.RETRIES_CONFIG, 3);
        props.put(ProducerConfig.ENABLE_IDEMPOTENCE_CONFIG, true);
        return props;
    }

    /**
     * Create consumer properties
     */
    private Properties createConsumerProperties(String groupId) {
        Properties props = new Properties();
        props.put(ConsumerConfig.BOOTSTRAP_SERVERS_CONFIG, String.join(",", config.getBrokers()));
        props.put(ConsumerConfig.GROUP_ID_CONFIG, groupId);
        props.put(ConsumerConfig.CLIENT_ID_CONFIG, config.getClientId());
        props.put(ConsumerConfig.KEY_DESERIALIZER_CLASS_CONFIG, "org.apache.kafka.common.serialization.StringDeserializer");
        props.put(ConsumerConfig.VALUE_DESERIALIZER_CLASS_CONFIG, "org.apache.kafka.common.serialization.StringDeserializer");
        props.put(ConsumerConfig.AUTO_OFFSET_RESET_CONFIG, "latest");
        props.put(ConsumerConfig.ENABLE_AUTO_COMMIT_CONFIG, true);
        return props;
    }

    private Properties createAdminProperties() {
        Properties props = new Properties();
        props.put(ProducerConfig.BOOTSTRAP_SERVERS_CONFIG, String.join(",", config.getBrokers()));
        props.put(ProducerConfig.CLIENT_ID_CONFIG, config.getClientId() + "-admin");
        return props;
    }

    /**
     * Message payload wrapper
     */
    public static class MessagePayload {
        private final String topic;
        private final int partition;
        private final long offset;
        private final String key;
        private final String value;
        private final long timestamp;

        public MessagePayload(String topic, int partition, long offset, String key, String value, long timestamp) {
            this.topic = topic;
            this.partition = partition;
            this.offset = offset;
            this.key = key;
            this.value = value;
            this.timestamp = timestamp;
        }

        public String getTopic() { return topic; }
        public int getPartition() { return partition; }
        public long getOffset() { return offset; }
        public String getKey() { return key; }
        public String getValue() { return value; }
        public long getTimestamp() { return timestamp; }
    }

    /**
     * Message handler interface
     */
    @FunctionalInterface
    public interface MessageHandler {
        void handle(MessagePayload payload) throws Exception;
    }
}