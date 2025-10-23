package com.perlinsos.splp.utils;

import java.util.concurrent.CompletableFuture;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicLong;
import java.util.concurrent.atomic.AtomicReference;
import java.util.function.Supplier;

/**
 * Circuit Breaker Pattern Implementation
 * Provides fault tolerance and prevents cascading failures
 */
public class CircuitBreaker {
    
    public enum CircuitState {
        CLOSED,     // Normal operation
        OPEN,       // Circuit is open, failing fast
        HALF_OPEN   // Testing if service is back
    }

    public static class CircuitBreakerConfig {
        private final int failureThreshold;
        private final long recoveryTimeoutMs;
        private final long monitoringPeriodMs;

        public CircuitBreakerConfig(int failureThreshold, long recoveryTimeoutMs, long monitoringPeriodMs) {
            this.failureThreshold = failureThreshold;
            this.recoveryTimeoutMs = recoveryTimeoutMs;
            this.monitoringPeriodMs = monitoringPeriodMs;
        }

        public int getFailureThreshold() {
            return failureThreshold;
        }

        public long getRecoveryTimeoutMs() {
            return recoveryTimeoutMs;
        }

        public long getMonitoringPeriodMs() {
            return monitoringPeriodMs;
        }
    }

    private final AtomicReference<CircuitState> state = new AtomicReference<>(CircuitState.CLOSED);
    private final AtomicInteger failureCount = new AtomicInteger(0);
    private final AtomicLong lastFailureTime = new AtomicLong(0);
    private final CircuitBreakerConfig config;

    public CircuitBreaker(CircuitBreakerConfig config) {
        this.config = config;
    }

    /**
     * Execute function with circuit breaker protection
     * @param supplier The function to execute
     * @param <T> Return type
     * @return Result of the function
     * @throws CircuitBreakerException if circuit is open
     */
    public <T> T execute(Supplier<T> supplier) throws CircuitBreakerException {
        if (state.get() == CircuitState.OPEN) {
            if (System.currentTimeMillis() - lastFailureTime.get() > config.getRecoveryTimeoutMs()) {
                state.set(CircuitState.HALF_OPEN);
            } else {
                throw new CircuitBreakerException("Circuit breaker is OPEN - service unavailable");
            }
        }

        try {
            T result = supplier.get();
            onSuccess();
            return result;
        } catch (Exception error) {
            onFailure();
            throw new CircuitBreakerException("Circuit breaker execution failed", error);
        }
    }

    /**
     * Execute async function with circuit breaker protection
     * @param supplier The async function to execute
     * @param <T> Return type
     * @return CompletableFuture with result
     */
    public <T> CompletableFuture<T> executeAsync(Supplier<CompletableFuture<T>> supplier) {
        if (state.get() == CircuitState.OPEN) {
            if (System.currentTimeMillis() - lastFailureTime.get() > config.getRecoveryTimeoutMs()) {
                state.set(CircuitState.HALF_OPEN);
            } else {
                return CompletableFuture.failedFuture(
                    new CircuitBreakerException("Circuit breaker is OPEN - service unavailable")
                );
            }
        }

        return supplier.get()
            .whenComplete((result, throwable) -> {
                if (throwable != null) {
                    onFailure();
                } else {
                    onSuccess();
                }
            });
    }

    private void onSuccess() {
        failureCount.set(0);
        state.set(CircuitState.CLOSED);
    }

    private void onFailure() {
        int currentFailures = failureCount.incrementAndGet();
        lastFailureTime.set(System.currentTimeMillis());

        if (currentFailures >= config.getFailureThreshold()) {
            state.set(CircuitState.OPEN);
        }
    }

    public CircuitState getState() {
        return state.get();
    }

    public int getFailureCount() {
        return failureCount.get();
    }

    public long getLastFailureTime() {
        return lastFailureTime.get();
    }

    /**
     * Reset the circuit breaker to closed state
     */
    public void reset() {
        failureCount.set(0);
        lastFailureTime.set(0);
        state.set(CircuitState.CLOSED);
    }

    /**
     * Exception thrown when circuit breaker prevents execution
     */
    public static class CircuitBreakerException extends RuntimeException {
        public CircuitBreakerException(String message) {
            super(message);
        }

        public CircuitBreakerException(String message, Throwable cause) {
            super(message, cause);
        }
    }
}