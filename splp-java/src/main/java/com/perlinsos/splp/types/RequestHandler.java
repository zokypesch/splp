package com.perlinsos.splp.types;

import java.util.concurrent.CompletableFuture;

/**
 * Functional interface for handling requests in the messaging system
 * @param <TRequest> Type of the request payload
 * @param <TResponse> Type of the response payload
 */
@FunctionalInterface
public interface RequestHandler<TRequest, TResponse> {
    /**
     * Handle a request and return a response
     * @param requestId The unique request identifier
     * @param payload The request payload
     * @return A CompletableFuture containing the response payload
     */
    CompletableFuture<TResponse> handle(String requestId, TRequest payload);
}