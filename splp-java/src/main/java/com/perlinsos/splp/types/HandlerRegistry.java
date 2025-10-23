package com.perlinsos.splp.types;

import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Registry for managing request handlers by topic
 */
public class HandlerRegistry {
    private final Map<String, RequestHandler<?, ?>> handlers = new ConcurrentHashMap<>();

    /**
     * Register a handler for a specific topic
     * @param topic The topic to handle
     * @param handler The handler for the topic
     * @param <TRequest> Type of the request payload
     * @param <TResponse> Type of the response payload
     */
    public <TRequest, TResponse> void register(String topic, RequestHandler<TRequest, TResponse> handler) {
        handlers.put(topic, handler);
    }

    /**
     * Get a handler for a specific topic
     * @param topic The topic to get handler for
     * @return The handler for the topic, or null if not found
     */
    @SuppressWarnings("unchecked")
    public <TRequest, TResponse> RequestHandler<TRequest, TResponse> getHandler(String topic) {
        return (RequestHandler<TRequest, TResponse>) handlers.get(topic);
    }

    /**
     * Check if a handler is registered for a topic
     * @param topic The topic to check
     * @return true if handler exists, false otherwise
     */
    public boolean hasHandler(String topic) {
        return handlers.containsKey(topic);
    }

    /**
     * Remove a handler for a topic
     * @param topic The topic to remove handler for
     * @return The removed handler, or null if not found
     */
    @SuppressWarnings("unchecked")
    public <TRequest, TResponse> RequestHandler<TRequest, TResponse> unregister(String topic) {
        return (RequestHandler<TRequest, TResponse>) handlers.remove(topic);
    }

    /**
     * Get all registered topics
     * @return Set of all registered topics
     */
    public java.util.Set<String> getTopics() {
        return handlers.keySet();
    }

    /**
     * Clear all handlers
     */
    public void clear() {
        handlers.clear();
    }

    /**
     * Get the number of registered handlers
     * @return Number of registered handlers
     */
    public int size() {
        return handlers.size();
    }
}