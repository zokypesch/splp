package com.perlinsos.splp.utils;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ThreadLocalRandom;
import java.util.function.Function;
import java.util.function.Supplier;

/**
 * Retry Mechanism with Exponential Backoff
 * Provides automatic retry for transient failures
 */
public class RetryManager {
    private static final Logger logger = LoggerFactory.getLogger(RetryManager.class);

    public static class RetryConfig {
        private final int maxAttempts;
        private final long baseDelayMs;
        private final long maxDelayMs;
        private final double backoffMultiplier;
        private final boolean jitter;

        public RetryConfig(int maxAttempts, long baseDelayMs, long maxDelayMs, 
                          double backoffMultiplier, boolean jitter) {
            this.maxAttempts = maxAttempts;
            this.baseDelayMs = baseDelayMs;
            this.maxDelayMs = maxDelayMs;
            this.backoffMultiplier = backoffMultiplier;
            this.jitter = jitter;
        }

        public int getMaxAttempts() { return maxAttempts; }
        public long getBaseDelayMs() { return baseDelayMs; }
        public long getMaxDelayMs() { return maxDelayMs; }
        public double getBackoffMultiplier() { return backoffMultiplier; }
        public boolean isJitter() { return jitter; }
    }

    private final RetryConfig config;

    public RetryManager(RetryConfig config) {
        this.config = config;
    }

    /**
     * Execute function with retry logic
     * @param supplier The function to execute
     * @param isRetryableError Function to determine if error is retryable
     * @param <T> Return type
     * @return Result of the function
     * @throws Exception if all retries fail
     */
    public <T> T execute(Supplier<T> supplier, Function<Exception, Boolean> isRetryableError) throws Exception {
        Exception lastError = null;

        for (int attempt = 1; attempt <= config.getMaxAttempts(); attempt++) {
            try {
                return supplier.get();
            } catch (Exception error) {
                lastError = error;

                // Check if error is retryable
                if (isRetryableError != null && !isRetryableError.apply(error)) {
                    throw error;
                }

                // Don't retry on last attempt
                if (attempt == config.getMaxAttempts()) {
                    throw error;
                }

                // Calculate delay with exponential backoff
                long delay = calculateDelay(attempt);
                logger.warn("Attempt {} failed, retrying in {}ms...", attempt, delay, error);

                try {
                    Thread.sleep(delay);
                } catch (InterruptedException ie) {
                    Thread.currentThread().interrupt();
                    throw new RuntimeException("Retry interrupted", ie);
                }
            }
        }

        throw lastError;
    }

    /**
     * Execute function with retry logic (no retryable error check)
     * @param supplier The function to execute
     * @param <T> Return type
     * @return Result of the function
     * @throws Exception if all retries fail
     */
    public <T> T execute(Supplier<T> supplier) throws Exception {
        return execute(supplier, null);
    }

    /**
     * Execute async function with retry logic
     * @param supplier The async function to execute
     * @param isRetryableError Function to determine if error is retryable
     * @param <T> Return type
     * @return CompletableFuture with result
     */
    public <T> CompletableFuture<T> executeAsync(Supplier<CompletableFuture<T>> supplier, 
                                                Function<Exception, Boolean> isRetryableError) {
        return executeAsyncInternal(supplier, isRetryableError, 1);
    }

    /**
     * Execute async function with retry logic (no retryable error check)
     * @param supplier The async function to execute
     * @param <T> Return type
     * @return CompletableFuture with result
     */
    public <T> CompletableFuture<T> executeAsync(Supplier<CompletableFuture<T>> supplier) {
        return executeAsync(supplier, null);
    }

    private <T> CompletableFuture<T> executeAsyncInternal(Supplier<CompletableFuture<T>> supplier,
                                                         Function<Exception, Boolean> isRetryableError,
                                                         int attempt) {
        return supplier.get()
            .handle((result, throwable) -> {
                if (throwable == null) {
                    return CompletableFuture.completedFuture(result);
                }

                Exception error = (Exception) throwable;

                // Check if error is retryable
                if (isRetryableError != null && !isRetryableError.apply(error)) {
                    return CompletableFuture.<T>failedFuture(error);
                }

                // Don't retry on last attempt
                if (attempt >= config.getMaxAttempts()) {
                    return CompletableFuture.<T>failedFuture(error);
                }

                // Calculate delay and retry
                long delay = calculateDelay(attempt);
                logger.warn("Attempt {} failed, retrying in {}ms...", attempt, delay, error);

                return CompletableFuture
                    .supplyAsync(() -> null, CompletableFuture.delayedExecutor(delay, java.util.concurrent.TimeUnit.MILLISECONDS))
                    .thenCompose(v -> executeAsyncInternal(supplier, isRetryableError, attempt + 1));
            })
            .thenCompose(future -> future);
    }

    private long calculateDelay(int attempt) {
        double exponentialDelay = config.getBaseDelayMs() * 
            Math.pow(config.getBackoffMultiplier(), attempt - 1);

        long cappedDelay = Math.min((long) exponentialDelay, config.getMaxDelayMs());

        if (config.isJitter()) {
            // Add random jitter (±25%)
            double jitterRange = cappedDelay * 0.25;
            double jitter = (ThreadLocalRandom.current().nextDouble() - 0.5) * 2 * jitterRange;
            return Math.max(0, (long) (cappedDelay + jitter));
        }

        return cappedDelay;
    }

    /**
     * Default retry configurations for common scenarios
     */
    public static class RetryConfigs {
        public static final RetryConfig FAST = new RetryConfig(3, 100, 1000, 2.0, true);
        public static final RetryConfig STANDARD = new RetryConfig(5, 500, 5000, 2.0, true);
        public static final RetryConfig SLOW = new RetryConfig(3, 2000, 10000, 2.0, true);
    }
}