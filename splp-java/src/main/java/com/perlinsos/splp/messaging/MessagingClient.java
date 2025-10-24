package com.perlinsos.splp.messaging;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.perlinsos.splp.crypto.EncryptionService;
import com.perlinsos.splp.kafka.KafkaWrapper;
import com.perlinsos.splp.logging.CassandraLogger;
import com.perlinsos.splp.types.*;
import com.perlinsos.splp.utils.RequestIdGenerator;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;

/**
 * Messaging Client for request-reply patterns with encryption and logging
 */
public class MessagingClient {
    private static final Logger logger = LoggerFactory.getLogger(MessagingClient.class);
    private static final ObjectMapper objectMapper = new ObjectMapper();

    private final MessagingConfig config;
    private final KafkaWrapper kafka;
    private final CassandraLogger cassandraLogger;
    private final String encryptionKey;
    private final HandlerRegistry handlers;
    private final Map<String, PendingRequest> pendingRequests = new ConcurrentHashMap<>();

    public MessagingClient(MessagingConfig config) {
        this.config = config;
        this.kafka = new KafkaWrapper(config.getKafkaConfig());
        this.cassandraLogger = new CassandraLogger(config.getCassandraConfig());
        this.encryptionKey = config.getEncryptionConfig().getEncryptionKey();
        this.handlers = new HandlerRegistry();
    }

    /**
     * Initialize the messaging client - single line setup for users
     */
    public CompletableFuture<Void> initialize() {
        return CompletableFuture.allOf(
            kafka.connectProducer().thenApply(p -> null),
            kafka.connectConsumer().thenApply(c -> null),
            cassandraLogger.initialize()
        ).thenRun(() -> logger.info("Messaging client initialized successfully"));
    }

    /**
     * Register a handler for a specific topic
     * Users only need to register their handler function
     */
    public <TRequest, TResponse> void registerHandler(String topic, RequestHandler<TRequest, TResponse> handler) {
        handlers.registerHandler(topic, handler);
        logger.info("Handler registered for topic: {}", topic);
    }

    /**
     * Start consuming messages and processing with registered handlers
     */
    public CompletableFuture<Void> startConsuming(List<String> topics) {
        // Subscribe to request topics
        List<String> requestTopics = topics;

        // Also subscribe to reply topics for request-reply pattern
        String replyTopic = config.getKafkaConfig().getClientId() + ".replies";
        List<String> allTopics = new java.util.ArrayList<>(requestTopics);
        allTopics.add(replyTopic);

        return kafka.subscribe(allTopics, payload -> {
            try {
                if (replyTopic.equals(payload.getTopic())) {
                    // Handle reply message
                    handleReply(payload.getValue()).join();
                } else {
                    // Handle request message
                    handleRequest(payload.getTopic(), payload.getValue()).join();
                }
            } catch (Exception e) {
                logger.error("Error processing message", e);
            }
        }).thenRun(() -> logger.info("Started consuming from topics: {}", String.join(", ", allTopics)));
    }

    /**
     * Send a request with automatic encryption and wait for reply
     * Returns the decrypted response
     */
    public <TRequest, TResponse> CompletableFuture<TResponse> request(String topic, TRequest payload, long timeoutMs) {
        String requestId = RequestIdGenerator.generate();
        long startTime = System.currentTimeMillis();

        // Create promise for reply
        CompletableFuture<TResponse> replyFuture = new CompletableFuture<>();
        
        PendingRequest pendingRequest = new PendingRequest(replyFuture, startTime);
        pendingRequests.put(requestId, pendingRequest);

        // Set timeout
        CompletableFuture.delayedExecutor(timeoutMs, TimeUnit.MILLISECONDS).execute(() -> {
            PendingRequest pending = pendingRequests.remove(requestId);
            if (pending != null) {
                pending.future.completeExceptionally(
                    new RuntimeException("Request timeout after " + timeoutMs + "ms")
                );
            }
        });

        return CompletableFuture.runAsync(() -> {
            try {
                // Encrypt payload
                EncryptionService.EncryptionResult encryptedMessage = EncryptionService.encryptPayload(payload, encryptionKey, requestId);

                // Log request
                LogEntry requestLog = LogEntry.createRequestLog(requestId, Instant.now(), topic, payload);
                cassandraLogger.log(requestLog).join();

                // Send to Kafka
                String replyTopicName = config.getKafkaConfig().getClientId() + ".replies";
                MessageWithReplyTopic messageWithReplyTopic = new MessageWithReplyTopic(
                    encryptedMessage.getRequestId(),
                    encryptedMessage.getData(),
                    encryptedMessage.getIv(),
                    encryptedMessage.getTag(),
                    replyTopicName
                );

                String messageJson = objectMapper.writeValueAsString(messageWithReplyTopic);
                kafka.sendMessage(topic, messageJson, requestId).join();

                logger.info("Request sent: {} to topic: {}", requestId, topic);
            } catch (Exception e) {
                PendingRequest pending = pendingRequests.remove(requestId);
                if (pending != null) {
                    pending.future.completeExceptionally(e);
                }
                throw new RuntimeException("Failed to send request", e);
            }
        }).thenCompose(v -> replyFuture);
    }

    /**
     * Send a request with default timeout (30 seconds)
     */
    public <TRequest, TResponse> CompletableFuture<TResponse> request(String topic, TRequest payload) {
        return request(topic, payload, 30000);
    }

    /**
     * Handle incoming request - decrypt, process with handler, encrypt and send reply
     */
    private CompletableFuture<Void> handleRequest(String topic, String messageValue) {
        return CompletableFuture.runAsync(() -> {
            long startTime = System.currentTimeMillis();
            String requestId = "";

            try {
                // Parse message
                MessageWithReplyTopic encryptedMessage = objectMapper.readValue(messageValue, MessageWithReplyTopic.class);
                requestId = encryptedMessage.getRequestId();

                // Decrypt payload
                var decryptionResult = EncryptionService.decryptPayload(
                    new EncryptedMessage(encryptedMessage.getRequestId(), encryptedMessage.getData(), 
                                       encryptedMessage.getIv(), encryptedMessage.getTag()),
                    encryptionKey,
                    Object.class
                );
                Object payload = decryptionResult.getPayload();

                logger.info("Request received: {} from topic: {}", requestId, topic);

                // Find handler
                RequestHandler<Object, Object> handler = handlers.getHandler(topic);
                if (handler == null) {
                    throw new RuntimeException("No handler registered for topic: " + topic);
                }

                // Process with handler
                CompletableFuture<Object> handlerResult = handler.handle(requestId, payload);
                Object responsePayload = handlerResult.join();

                // Create response
                ResponseMessage<Object> response = ResponseMessage.success(requestId, responsePayload, Instant.now());

                // Encrypt response
                EncryptionService.EncryptionResult encryptedResponse = EncryptionService.encryptPayload(response, encryptionKey, requestId);

                // Send reply to reply topic
                String replyTopic = encryptedMessage.getReplyTopic() != null ? 
                    encryptedMessage.getReplyTopic() : topic + ".replies";
                
                EncryptedMessage replyMessage = new EncryptedMessage(
                    encryptedResponse.getRequestId(),
                    encryptedResponse.getData(),
                    encryptedResponse.getIv(),
                    encryptedResponse.getTag()
                );

                String replyJson = objectMapper.writeValueAsString(replyMessage);
                kafka.sendMessage(replyTopic, replyJson, requestId).join();

                // Log response
                long duration = System.currentTimeMillis() - startTime;
                LogEntry responseLog = LogEntry.createResponseLog(requestId, Instant.now(), topic, responsePayload, true, null, (int) duration);
                cassandraLogger.log(responseLog).join();

                logger.info("Response sent: {} ({}ms)", requestId, duration);

            } catch (Exception error) {
                // Log error
                long duration = System.currentTimeMillis() - startTime;
                LogEntry errorLog = LogEntry.createErrorLog(requestId, Instant.now(), topic, null, error.getMessage(), (int) duration);
                cassandraLogger.log(errorLog).join();

                logger.error("Error handling request {}: {}", requestId, error.getMessage(), error);
            }
        });
    }

    /**
     * Handle incoming reply - decrypt and resolve pending request
     */
    private CompletableFuture<Void> handleReply(String messageValue) {
        return CompletableFuture.runAsync(() -> {
            try {
                // Parse encrypted message
                EncryptedMessage encryptedMessage = objectMapper.readValue(messageValue, EncryptedMessage.class);
                String requestId = encryptedMessage.getRequestId();

                // Decrypt response
                var decryptionResult = EncryptionService.decryptPayload(encryptedMessage, encryptionKey, ResponseMessage.class);
                ResponseMessage<?> response = decryptionResult.getPayload();

                logger.info("Reply received: {}", requestId);

                // Find pending request
                PendingRequest pendingRequest = pendingRequests.remove(requestId);
                if (pendingRequest != null) {
                    // Log response
                    long duration = System.currentTimeMillis() - pendingRequest.startTime;
                    LogEntry responseLog = LogEntry.createResponseLog(requestId, Instant.now(), "reply", 
                        response.getPayload(), response.getSuccess(), response.getError(), (int) duration);
                    cassandraLogger.log(responseLog).join();

                    // Resolve or reject promise
                    if (response.getSuccess()) {
                        pendingRequest.future.complete(response.getPayload());
                    } else {
                        pendingRequest.future.completeExceptionally(
                            new RuntimeException(response.getError() != null ? response.getError() : "Request failed")
                        );
                    }
                }
            } catch (Exception error) {
                logger.error("Error handling reply", error);
            }
        });
    }

    /**
     * Get logger instance for manual queries
     */
    public CassandraLogger getLogger() {
        return cassandraLogger;
    }

    /**
     * Close all connections
     */
    public CompletableFuture<Void> close() {
        return CompletableFuture.allOf(
            kafka.disconnect(),
            cassandraLogger.close()
        ).thenRun(() -> logger.info("Messaging client closed"));
    }

    /**
     * Internal class for tracking pending requests
     */
    private static class PendingRequest {
        final CompletableFuture<Object> future;
        final long startTime;

        PendingRequest(CompletableFuture<?> future, long startTime) {
            this.future = (CompletableFuture<Object>) future;
            this.startTime = startTime;
        }
    }

    /**
     * Internal class for messages with reply topic
     */
    private static class MessageWithReplyTopic extends EncryptedMessage {
        private String replyTopic;

        public MessageWithReplyTopic() {
            super();
        }

        public MessageWithReplyTopic(String requestId, String data, String iv, String tag, String replyTopic) {
            super(requestId, data, iv, tag);
            this.replyTopic = replyTopic;
        }

        public String getReplyTopic() {
            return replyTopic;
        }

        public void setReplyTopic(String replyTopic) {
            this.replyTopic = replyTopic;
        }
    }
}