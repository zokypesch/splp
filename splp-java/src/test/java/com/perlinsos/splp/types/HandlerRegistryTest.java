package com.perlinsos.splp.types;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.BeforeEach;

import java.util.Set;
import java.util.concurrent.CompletableFuture;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("HandlerRegistry Tests")
class HandlerRegistryTest {

    private HandlerRegistry<String, String> registry;
    private RequestHandler<String, String> testHandler;

    @BeforeEach
    void setUp() {
        registry = new HandlerRegistry<>();
        testHandler = (requestId, payload) -> CompletableFuture.completedFuture("Response: " + payload);
    }

    @Test
    @DisplayName("Should start with empty registry")
    void shouldStartWithEmptyRegistry() {
        assertTrue(registry.getAllTopics().isEmpty(), "Registry should start empty");
        assertEquals(0, registry.getHandlerCount(), "Handler count should be 0");
    }

    @Test
    @DisplayName("Should register handler successfully")
    void shouldRegisterHandlerSuccessfully() {
        registry.registerHandler("test-topic", testHandler);
        
        assertTrue(registry.hasHandler("test-topic"), "Should have handler for registered topic");
        assertEquals(1, registry.getHandlerCount(), "Handler count should be 1");
        assertTrue(registry.getAllTopics().contains("test-topic"), "Topics should contain registered topic");
    }

    @Test
    @DisplayName("Should retrieve registered handler")
    void shouldRetrieveRegisteredHandler() {
        registry.registerHandler("test-topic", testHandler);
        
        RequestHandler<String, String> retrievedHandler = registry.getHandler("test-topic");
        
        assertNotNull(retrievedHandler, "Retrieved handler should not be null");
        assertEquals(testHandler, retrievedHandler, "Retrieved handler should match registered handler");
    }

    @Test
    @DisplayName("Should return null for unregistered topic")
    void shouldReturnNullForUnregisteredTopic() {
        RequestHandler<String, String> handler = registry.getHandler("non-existent-topic");
        
        assertNull(handler, "Should return null for unregistered topic");
        assertFalse(registry.hasHandler("non-existent-topic"), "Should not have handler for unregistered topic");
    }

    @Test
    @DisplayName("Should replace existing handler")
    void shouldReplaceExistingHandler() {
        RequestHandler<String, String> newHandler = (requestId, payload) -> 
            CompletableFuture.completedFuture("New Response: " + payload);
        
        registry.registerHandler("test-topic", testHandler);
        registry.registerHandler("test-topic", newHandler);
        
        RequestHandler<String, String> retrievedHandler = registry.getHandler("test-topic");
        
        assertEquals(newHandler, retrievedHandler, "Should replace existing handler");
        assertEquals(1, registry.getHandlerCount(), "Handler count should remain 1");
    }

    @Test
    @DisplayName("Should unregister handler successfully")
    void shouldUnregisterHandlerSuccessfully() {
        registry.registerHandler("test-topic", testHandler);
        assertTrue(registry.hasHandler("test-topic"), "Should have handler before unregistering");
        
        boolean removed = registry.unregisterHandler("test-topic");
        
        assertTrue(removed, "Unregister should return true for existing handler");
        assertFalse(registry.hasHandler("test-topic"), "Should not have handler after unregistering");
        assertEquals(0, registry.getHandlerCount(), "Handler count should be 0");
        assertFalse(registry.getAllTopics().contains("test-topic"), "Topics should not contain unregistered topic");
    }

    @Test
    @DisplayName("Should return false when unregistering non-existent handler")
    void shouldReturnFalseWhenUnregisteringNonExistentHandler() {
        boolean removed = registry.unregisterHandler("non-existent-topic");
        
        assertFalse(removed, "Unregister should return false for non-existent handler");
    }

    @Test
    @DisplayName("Should handle multiple handlers")
    void shouldHandleMultipleHandlers() {
        RequestHandler<String, String> handler1 = (requestId, payload) -> 
            CompletableFuture.completedFuture("Handler1: " + payload);
        RequestHandler<String, String> handler2 = (requestId, payload) -> 
            CompletableFuture.completedFuture("Handler2: " + payload);
        RequestHandler<String, String> handler3 = (requestId, payload) -> 
            CompletableFuture.completedFuture("Handler3: " + payload);
        
        registry.registerHandler("topic1", handler1);
        registry.registerHandler("topic2", handler2);
        registry.registerHandler("topic3", handler3);
        
        assertEquals(3, registry.getHandlerCount(), "Should have 3 handlers");
        
        Set<String> topics = registry.getAllTopics();
        assertEquals(3, topics.size(), "Should have 3 topics");
        assertTrue(topics.contains("topic1"), "Should contain topic1");
        assertTrue(topics.contains("topic2"), "Should contain topic2");
        assertTrue(topics.contains("topic3"), "Should contain topic3");
        
        assertEquals(handler1, registry.getHandler("topic1"), "Should retrieve correct handler for topic1");
        assertEquals(handler2, registry.getHandler("topic2"), "Should retrieve correct handler for topic2");
        assertEquals(handler3, registry.getHandler("topic3"), "Should retrieve correct handler for topic3");
    }

    @Test
    @DisplayName("Should clear all handlers")
    void shouldClearAllHandlers() {
        registry.registerHandler("topic1", testHandler);
        registry.registerHandler("topic2", testHandler);
        registry.registerHandler("topic3", testHandler);
        
        assertEquals(3, registry.getHandlerCount(), "Should have 3 handlers before clearing");
        
        registry.clearAllHandlers();
        
        assertEquals(0, registry.getHandlerCount(), "Should have 0 handlers after clearing");
        assertTrue(registry.getAllTopics().isEmpty(), "Topics should be empty after clearing");
        assertFalse(registry.hasHandler("topic1"), "Should not have handler for topic1 after clearing");
        assertFalse(registry.hasHandler("topic2"), "Should not have handler for topic2 after clearing");
        assertFalse(registry.hasHandler("topic3"), "Should not have handler for topic3 after clearing");
    }

    @Test
    @DisplayName("Should handle null topic gracefully")
    void shouldHandleNullTopicGracefully() {
        assertThrows(IllegalArgumentException.class, () -> {
            registry.registerHandler(null, testHandler);
        }, "Should throw exception for null topic in register");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.getHandler(null);
        }, "Should throw exception for null topic in get");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.hasHandler(null);
        }, "Should throw exception for null topic in has");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.unregisterHandler(null);
        }, "Should throw exception for null topic in unregister");
    }

    @Test
    @DisplayName("Should handle null handler gracefully")
    void shouldHandleNullHandlerGracefully() {
        assertThrows(IllegalArgumentException.class, () -> {
            registry.registerHandler("test-topic", null);
        }, "Should throw exception for null handler");
    }

    @Test
    @DisplayName("Should handle empty topic gracefully")
    void shouldHandleEmptyTopicGracefully() {
        assertThrows(IllegalArgumentException.class, () -> {
            registry.registerHandler("", testHandler);
        }, "Should throw exception for empty topic in register");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.getHandler("");
        }, "Should throw exception for empty topic in get");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.hasHandler("");
        }, "Should throw exception for empty topic in has");
        
        assertThrows(IllegalArgumentException.class, () -> {
            registry.unregisterHandler("");
        }, "Should throw exception for empty topic in unregister");
    }

    @Test
    @DisplayName("Should be thread-safe for concurrent operations")
    void shouldBeThreadSafeForConcurrentOperations() throws InterruptedException {
        final int threadCount = 10;
        final int operationsPerThread = 100;
        Thread[] threads = new Thread[threadCount];
        
        // Create threads that register and unregister handlers concurrently
        for (int i = 0; i < threadCount; i++) {
            final int threadId = i;
            threads[i] = new Thread(() -> {
                for (int j = 0; j < operationsPerThread; j++) {
                    String topic = "topic-" + threadId + "-" + j;
                    RequestHandler<String, String> handler = (requestId, payload) -> 
                        CompletableFuture.completedFuture("Response from " + topic);
                    
                    registry.registerHandler(topic, handler);
                    assertTrue(registry.hasHandler(topic), "Handler should be registered");
                    assertNotNull(registry.getHandler(topic), "Handler should be retrievable");
                    
                    if (j % 2 == 0) {
                        registry.unregisterHandler(topic);
                        assertFalse(registry.hasHandler(topic), "Handler should be unregistered");
                    }
                }
            });
        }
        
        // Start all threads
        for (Thread thread : threads) {
            thread.start();
        }
        
        // Wait for all threads to complete
        for (Thread thread : threads) {
            thread.join();
        }
        
        // Verify final state is consistent
        assertTrue(registry.getHandlerCount() >= 0, "Handler count should be non-negative");
        assertEquals(registry.getAllTopics().size(), registry.getHandlerCount(), 
                    "Topic count should match handler count");
    }

    @Test
    @DisplayName("Should maintain immutable topic set")
    void shouldMaintainImmutableTopicSet() {
        registry.registerHandler("topic1", testHandler);
        registry.registerHandler("topic2", testHandler);
        
        Set<String> topics = registry.getAllTopics();
        
        // Attempt to modify the returned set should not affect the registry
        assertThrows(UnsupportedOperationException.class, () -> {
            topics.add("topic3");
        }, "Returned topic set should be immutable");
        
        // Verify registry is unchanged
        assertEquals(2, registry.getHandlerCount(), "Registry should be unchanged");
        assertFalse(registry.hasHandler("topic3"), "Registry should not have topic3");
    }
}