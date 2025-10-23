package com.perlinsos.splp.utils;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Timeout;

import java.time.Duration;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.function.Supplier;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("CircuitBreaker Tests")
class CircuitBreakerTest {

    private CircuitBreaker circuitBreaker;

    @BeforeEach
    void setUp() {
        circuitBreaker = new CircuitBreaker(
            3,                          // failureThreshold
            Duration.ofMillis(100),     // recoveryTimeout
            Duration.ofMillis(50)       // monitoringPeriod
        );
    }

    @Test
    @DisplayName("Should start in CLOSED state")
    void shouldStartInClosedState() {
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(0, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should execute successful operations in CLOSED state")
    void shouldExecuteSuccessfulOperationsInClosedState() {
        Supplier<String> successfulOperation = () -> "success";
        
        String result = circuitBreaker.execute(successfulOperation);
        
        assertEquals("success", result);
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(0, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should count failures in CLOSED state")
    void shouldCountFailuresInClosedState() {
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        // First failure
        assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(1, circuitBreaker.getFailureCount());
        
        // Second failure
        assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(2, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should transition to OPEN state after threshold failures")
    void shouldTransitionToOpenStateAfterThresholdFailures() {
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        // Reach failure threshold
        for (int i = 0; i < 3; i++) {
            assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        }
        
        assertEquals(CircuitBreaker.State.OPEN, circuitBreaker.getState());
        assertEquals(3, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should reject operations immediately in OPEN state")
    void shouldRejectOperationsImmediatelyInOpenState() {
        // Force circuit breaker to OPEN state
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        for (int i = 0; i < 3; i++) {
            assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        }
        
        // Now it should be OPEN and reject immediately
        Supplier<String> anyOperation = () -> "should not execute";
        
        CircuitBreaker.CircuitBreakerException exception = assertThrows(
            CircuitBreaker.CircuitBreakerException.class,
            () -> circuitBreaker.execute(anyOperation)
        );
        
        assertTrue(exception.getMessage().contains("Circuit breaker is OPEN"));
    }

    @Test
    @DisplayName("Should transition to HALF_OPEN after recovery timeout")
    @Timeout(5)
    void shouldTransitionToHalfOpenAfterRecoveryTimeout() throws InterruptedException {
        // Force circuit breaker to OPEN state
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        for (int i = 0; i < 3; i++) {
            assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        }
        
        assertEquals(CircuitBreaker.State.OPEN, circuitBreaker.getState());
        
        // Wait for recovery timeout
        Thread.sleep(150); // Recovery timeout is 100ms
        
        // Next operation should trigger transition to HALF_OPEN
        Supplier<String> testOperation = () -> "test";
        String result = circuitBreaker.execute(testOperation);
        
        assertEquals("test", result);
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState()); // Should close after successful operation
        assertEquals(0, circuitBreaker.getFailureCount()); // Failure count should reset
    }

    @Test
    @DisplayName("Should reset failure count on successful operation")
    void shouldResetFailureCountOnSuccessfulOperation() {
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        Supplier<String> successfulOperation = () -> "success";
        
        // Add some failures
        assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        assertEquals(2, circuitBreaker.getFailureCount());
        
        // Successful operation should reset count
        String result = circuitBreaker.execute(successfulOperation);
        assertEquals("success", result);
        assertEquals(0, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should handle async operations successfully")
    void shouldHandleAsyncOperationsSuccessfully() throws ExecutionException, InterruptedException {
        Supplier<CompletableFuture<String>> asyncOperation = () -> 
            CompletableFuture.completedFuture("async success");
        
        CompletableFuture<String> result = circuitBreaker.executeAsync(asyncOperation);
        
        assertEquals("async success", result.get());
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(0, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should handle async operation failures")
    void shouldHandleAsyncOperationFailures() {
        Supplier<CompletableFuture<String>> failingAsyncOperation = () -> {
            CompletableFuture<String> future = new CompletableFuture<>();
            future.completeExceptionally(new RuntimeException("Async operation failed"));
            return future;
        };
        
        CompletableFuture<String> result = circuitBreaker.executeAsync(failingAsyncOperation);
        
        assertThrows(ExecutionException.class, result::get);
        assertEquals(1, circuitBreaker.getFailureCount());
    }

    @Test
    @DisplayName("Should reject async operations when OPEN")
    void shouldRejectAsyncOperationsWhenOpen() {
        // Force circuit breaker to OPEN state
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        for (int i = 0; i < 3; i++) {
            assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        }
        
        // Async operation should be rejected
        Supplier<CompletableFuture<String>> asyncOperation = () -> 
            CompletableFuture.completedFuture("should not execute");
        
        CompletableFuture<String> result = circuitBreaker.executeAsync(asyncOperation);
        
        assertThrows(ExecutionException.class, result::get);
    }

    @Test
    @DisplayName("Should handle concurrent operations correctly")
    void shouldHandleConcurrentOperationsCorrectly() throws InterruptedException {
        AtomicInteger successCount = new AtomicInteger(0);
        AtomicInteger failureCount = new AtomicInteger(0);
        
        Supplier<String> operation = () -> {
            if (Math.random() > 0.5) {
                successCount.incrementAndGet();
                return "success";
            } else {
                failureCount.incrementAndGet();
                throw new RuntimeException("Random failure");
            }
        };
        
        // Run multiple concurrent operations
        Thread[] threads = new Thread[10];
        for (int i = 0; i < threads.length; i++) {
            threads[i] = new Thread(() -> {
                for (int j = 0; j < 10; j++) {
                    try {
                        circuitBreaker.execute(operation);
                    } catch (Exception e) {
                        // Expected for some operations
                    }
                }
            });
            threads[i].start();
        }
        
        // Wait for all threads to complete
        for (Thread thread : threads) {
            thread.join();
        }
        
        // Verify that the circuit breaker maintained consistency
        assertTrue(successCount.get() > 0 || failureCount.get() > 0, "Some operations should have executed");
        assertTrue(circuitBreaker.getFailureCount() >= 0, "Failure count should be non-negative");
    }

    @Test
    @DisplayName("Should provide accurate state information")
    void shouldProvideAccurateStateInformation() {
        assertEquals(CircuitBreaker.State.CLOSED, circuitBreaker.getState());
        assertEquals(0, circuitBreaker.getFailureCount());
        assertFalse(circuitBreaker.isOpen());
        
        // Add a failure
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        assertEquals(1, circuitBreaker.getFailureCount());
        assertFalse(circuitBreaker.isOpen());
        
        // Force to OPEN state
        for (int i = 0; i < 2; i++) {
            assertThrows(RuntimeException.class, () -> circuitBreaker.execute(failingOperation));
        }
        
        assertEquals(CircuitBreaker.State.OPEN, circuitBreaker.getState());
        assertTrue(circuitBreaker.isOpen());
    }

    @Test
    @DisplayName("Should handle custom configuration correctly")
    void shouldHandleCustomConfigurationCorrectly() {
        CircuitBreaker customCircuitBreaker = new CircuitBreaker(
            1,                          // failureThreshold = 1
            Duration.ofMillis(200),     // recoveryTimeout
            Duration.ofMillis(100)      // monitoringPeriod
        );
        
        Supplier<String> failingOperation = () -> {
            throw new RuntimeException("Operation failed");
        };
        
        // Should open after just 1 failure
        assertThrows(RuntimeException.class, () -> customCircuitBreaker.execute(failingOperation));
        assertEquals(CircuitBreaker.State.OPEN, customCircuitBreaker.getState());
        
        // Should reject immediately
        CircuitBreaker.CircuitBreakerException exception = assertThrows(
            CircuitBreaker.CircuitBreakerException.class,
            () -> customCircuitBreaker.execute(() -> "test")
        );
        
        assertTrue(exception.getMessage().contains("Circuit breaker is OPEN"));
    }
}