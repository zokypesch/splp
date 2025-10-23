package com.perlinsos.splp.integration;

import com.perlinsos.splp.kafka.KafkaWrapper;
import com.perlinsos.splp.types.KafkaConfig;
import org.junit.jupiter.api.*;
import org.junit.jupiter.api.condition.EnabledIfEnvironmentVariable;
import org.testcontainers.containers.KafkaContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;
import org.testcontainers.utility.DockerImageName;

import java.time.Duration;
import java.util.Arrays;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

import static org.junit.jupiter.api.Assertions.*;

@Testcontainers
@DisplayName("Kafka Integration Tests")
@EnabledIfEnvironmentVariable(named = "RUN_INTEGRATION_TESTS", matches = "true")
class KafkaIntegrationTest {

    @Container
    static final KafkaContainer kafka = new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.4.0"))
            .withExposedPorts(9093);

    private KafkaWrapper kafkaWrapper;
    private KafkaConfig kafkaConfig;

    @BeforeEach
    void setUp() {
        String bootstrapServers = kafka.getBootstrapServers();
        kafkaConfig = new KafkaConfig(
            Arrays.asList(bootstrapServers),
            "test-client",
            "test-group"
        );
        kafkaWrapper = new KafkaWrapper(kafkaConfig);
    }

    @AfterEach
    void tearDown() {
        if (kafkaWrapper != null) {
            try {
                kafkaWrapper.disconnect().get(5, TimeUnit.SECONDS);
            } catch (Exception e) {
                // Ignore cleanup errors
            }
        }
    }

    @Test
    @DisplayName("Should connect producer and consumer successfully")
    void shouldConnectProducerAndConsumerSuccessfully() throws Exception {
        // Connect producer
        CompletableFuture<Void> producerConnection = kafkaWrapper.connectProducer()
                .thenApply(producer -> null);
        
        // Connect consumer
        CompletableFuture<Void> consumerConnection = kafkaWrapper.connectConsumer()
                .thenApply(consumer -> null);
        
        // Wait for both connections
        CompletableFuture.allOf(producerConnection, consumerConnection)
                .get(30, TimeUnit.SECONDS);
        
        // Verify connections
        assertTrue(kafkaWrapper.isProducerConnected(), "Producer should be connected");
        assertTrue(kafkaWrapper.isConsumerConnected(), "Consumer should be connected");
    }

    @Test
    @DisplayName("Should create topic successfully")
    void shouldCreateTopicSuccessfully() throws Exception {
        // Connect first
        kafkaWrapper.connectProducer().get(30, TimeUnit.SECONDS);
        
        // Create topic
        String topicName = "test-topic-creation";
        CompletableFuture<Void> topicCreation = kafkaWrapper.createTopic(topicName, 3, (short) 1);
        
        assertDoesNotThrow(() -> topicCreation.get(30, TimeUnit.SECONDS), 
                          "Topic creation should not throw exception");
    }

    @Test
    @DisplayName("Should send and receive messages successfully")
    void shouldSendAndReceiveMessagesSuccessfully() throws Exception {
        String topicName = "test-send-receive";
        String testMessage = "Hello Kafka Integration Test";
        String testKey = "test-key-123";
        
        // Connect producer and consumer
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        // Create topic
        kafkaWrapper.createTopic(topicName, 1, (short) 1).get(30, TimeUnit.SECONDS);
        
        // Set up message reception
        CountDownLatch messageLatch = new CountDownLatch(1);
        AtomicReference<String> receivedMessage = new AtomicReference<>();
        AtomicReference<String> receivedKey = new AtomicReference<>();
        
        kafkaWrapper.subscribe(Arrays.asList(topicName), payload -> {
            receivedMessage.set(payload.getValue());
            receivedKey.set(payload.getKey());
            messageLatch.countDown();
        }).get(30, TimeUnit.SECONDS);
        
        // Wait a bit for subscription to be active
        Thread.sleep(2000);
        
        // Send message
        kafkaWrapper.sendMessage(topicName, testMessage, testKey).get(30, TimeUnit.SECONDS);
        
        // Wait for message reception
        assertTrue(messageLatch.await(30, TimeUnit.SECONDS), "Should receive message within timeout");
        
        // Verify received message
        assertEquals(testMessage, receivedMessage.get(), "Received message should match sent message");
        assertEquals(testKey, receivedKey.get(), "Received key should match sent key");
    }

    @Test
    @DisplayName("Should handle multiple messages on same topic")
    void shouldHandleMultipleMessagesOnSameTopic() throws Exception {
        String topicName = "test-multiple-messages";
        int messageCount = 5;
        
        // Connect producer and consumer
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        // Create topic
        kafkaWrapper.createTopic(topicName, 1, (short) 1).get(30, TimeUnit.SECONDS);
        
        // Set up message reception
        CountDownLatch messageLatch = new CountDownLatch(messageCount);
        AtomicReference<Integer> receivedCount = new AtomicReference<>(0);
        
        kafkaWrapper.subscribe(Arrays.asList(topicName), payload -> {
            receivedCount.updateAndGet(count -> count + 1);
            messageLatch.countDown();
        }).get(30, TimeUnit.SECONDS);
        
        // Wait for subscription to be active
        Thread.sleep(2000);
        
        // Send multiple messages
        for (int i = 0; i < messageCount; i++) {
            String message = "Message " + i;
            String key = "key-" + i;
            kafkaWrapper.sendMessage(topicName, message, key).get(5, TimeUnit.SECONDS);
        }
        
        // Wait for all messages to be received
        assertTrue(messageLatch.await(30, TimeUnit.SECONDS), 
                  "Should receive all messages within timeout");
        assertEquals(messageCount, receivedCount.get(), "Should receive all sent messages");
    }

    @Test
    @DisplayName("Should handle subscription to multiple topics")
    void shouldHandleSubscriptionToMultipleTopics() throws Exception {
        String topic1 = "test-multi-topic-1";
        String topic2 = "test-multi-topic-2";
        
        // Connect producer and consumer
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        // Create topics
        kafkaWrapper.createTopic(topic1, 1, (short) 1).get(30, TimeUnit.SECONDS);
        kafkaWrapper.createTopic(topic2, 1, (short) 1).get(30, TimeUnit.SECONDS);
        
        // Set up message reception
        CountDownLatch messageLatch = new CountDownLatch(2);
        AtomicReference<String> receivedFromTopic1 = new AtomicReference<>();
        AtomicReference<String> receivedFromTopic2 = new AtomicReference<>();
        
        kafkaWrapper.subscribe(Arrays.asList(topic1, topic2), payload -> {
            if (payload.getTopic().equals(topic1)) {
                receivedFromTopic1.set(payload.getValue());
            } else if (payload.getTopic().equals(topic2)) {
                receivedFromTopic2.set(payload.getValue());
            }
            messageLatch.countDown();
        }).get(30, TimeUnit.SECONDS);
        
        // Wait for subscription to be active
        Thread.sleep(2000);
        
        // Send messages to both topics
        kafkaWrapper.sendMessage(topic1, "Message for topic 1", "key1").get(5, TimeUnit.SECONDS);
        kafkaWrapper.sendMessage(topic2, "Message for topic 2", "key2").get(5, TimeUnit.SECONDS);
        
        // Wait for messages
        assertTrue(messageLatch.await(30, TimeUnit.SECONDS), 
                  "Should receive messages from both topics");
        
        assertEquals("Message for topic 1", receivedFromTopic1.get(), 
                    "Should receive correct message from topic 1");
        assertEquals("Message for topic 2", receivedFromTopic2.get(), 
                    "Should receive correct message from topic 2");
    }

    @Test
    @DisplayName("Should handle disconnection gracefully")
    void shouldHandleDisconnectionGracefully() throws Exception {
        // Connect
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        assertTrue(kafkaWrapper.isProducerConnected(), "Producer should be connected");
        assertTrue(kafkaWrapper.isConsumerConnected(), "Consumer should be connected");
        
        // Disconnect
        kafkaWrapper.disconnect().get(30, TimeUnit.SECONDS);
        
        assertFalse(kafkaWrapper.isProducerConnected(), "Producer should be disconnected");
        assertFalse(kafkaWrapper.isConsumerConnected(), "Consumer should be disconnected");
    }

    @Test
    @DisplayName("Should handle connection failures gracefully")
    void shouldHandleConnectionFailuresGracefully() {
        // Create wrapper with invalid configuration
        KafkaConfig invalidConfig = new KafkaConfig(
            Arrays.asList("invalid-host:9092"),
            "test-client",
            "test-group"
        );
        KafkaWrapper invalidWrapper = new KafkaWrapper(invalidConfig);
        
        // Connection should fail but not throw unhandled exceptions
        assertThrows(Exception.class, () -> {
            invalidWrapper.connectProducer().get(5, TimeUnit.SECONDS);
        }, "Should throw exception for invalid connection");
    }

    @Test
    @DisplayName("Should handle large messages")
    void shouldHandleLargeMessages() throws Exception {
        String topicName = "test-large-message";
        
        // Create a large message (1MB)
        StringBuilder largeMessageBuilder = new StringBuilder();
        for (int i = 0; i < 100000; i++) {
            largeMessageBuilder.append("This is a large message for testing Kafka integration. ");
        }
        String largeMessage = largeMessageBuilder.toString();
        
        // Connect producer and consumer
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        // Create topic
        kafkaWrapper.createTopic(topicName, 1, (short) 1).get(30, TimeUnit.SECONDS);
        
        // Set up message reception
        CountDownLatch messageLatch = new CountDownLatch(1);
        AtomicReference<String> receivedMessage = new AtomicReference<>();
        
        kafkaWrapper.subscribe(Arrays.asList(topicName), payload -> {
            receivedMessage.set(payload.getValue());
            messageLatch.countDown();
        }).get(30, TimeUnit.SECONDS);
        
        // Wait for subscription
        Thread.sleep(2000);
        
        // Send large message
        kafkaWrapper.sendMessage(topicName, largeMessage, "large-key").get(30, TimeUnit.SECONDS);
        
        // Wait for message reception
        assertTrue(messageLatch.await(60, TimeUnit.SECONDS), 
                  "Should receive large message within timeout");
        assertEquals(largeMessage, receivedMessage.get(), 
                    "Received large message should match sent message");
    }

    @Test
    @DisplayName("Should handle concurrent operations")
    void shouldHandleConcurrentOperations() throws Exception {
        String topicName = "test-concurrent";
        int threadCount = 5;
        int messagesPerThread = 10;
        
        // Connect producer and consumer
        CompletableFuture.allOf(
            kafkaWrapper.connectProducer().thenApply(p -> null),
            kafkaWrapper.connectConsumer().thenApply(c -> null)
        ).get(30, TimeUnit.SECONDS);
        
        // Create topic
        kafkaWrapper.createTopic(topicName, 3, (short) 1).get(30, TimeUnit.SECONDS);
        
        // Set up message reception
        CountDownLatch messageLatch = new CountDownLatch(threadCount * messagesPerThread);
        
        kafkaWrapper.subscribe(Arrays.asList(topicName), payload -> {
            messageLatch.countDown();
        }).get(30, TimeUnit.SECONDS);
        
        // Wait for subscription
        Thread.sleep(2000);
        
        // Send messages concurrently
        Thread[] threads = new Thread[threadCount];
        for (int i = 0; i < threadCount; i++) {
            final int threadId = i;
            threads[i] = new Thread(() -> {
                for (int j = 0; j < messagesPerThread; j++) {
                    try {
                        String message = "Thread " + threadId + " Message " + j;
                        String key = "thread-" + threadId + "-msg-" + j;
                        kafkaWrapper.sendMessage(topicName, message, key).get(5, TimeUnit.SECONDS);
                    } catch (Exception e) {
                        fail("Failed to send message: " + e.getMessage());
                    }
                }
            });
            threads[i].start();
        }
        
        // Wait for all threads to complete
        for (Thread thread : threads) {
            thread.join(30000);
        }
        
        // Wait for all messages to be received
        assertTrue(messageLatch.await(60, TimeUnit.SECONDS), 
                  "Should receive all concurrent messages within timeout");
    }
}