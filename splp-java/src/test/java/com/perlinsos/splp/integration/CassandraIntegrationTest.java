package com.perlinsos.splp.integration;

import com.perlinsos.splp.logging.CassandraLogger;
import com.perlinsos.splp.types.LogEntry;
import org.junit.jupiter.api.*;
import org.testcontainers.containers.CassandraContainer;
import org.testcontainers.junit.jupiter.Container;
import org.testcontainers.junit.jupiter.Testcontainers;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;

import static org.junit.jupiter.api.Assertions.*;

@Testcontainers
@DisplayName("Cassandra Integration Tests")
class CassandraIntegrationTest {

    @Container
    static final CassandraContainer<?> cassandra = new CassandraContainer<>("cassandra:4.1")
            .withExposedPorts(9042)
            .withInitScript("cassandra-init.cql");

    private CassandraLogger cassandraLogger;

    @BeforeEach
    void setUp() {
        String contactPoint = cassandra.getHost();
        int port = cassandra.getMappedPort(9042);
        
        cassandraLogger = new CassandraLogger(contactPoint, port, "test_datacenter");
        cassandraLogger.initialize();
    }

    @AfterEach
    void tearDown() {
        if (cassandraLogger != null) {
            cassandraLogger.close();
        }
    }

    @Test
    @DisplayName("Should initialize Cassandra connection and create keyspace/table")
    void shouldInitializeCassandraConnectionAndCreateKeyspaceTable() {
        // The setup already calls initialize(), so we just need to verify it worked
        assertDoesNotThrow(() -> {
            // Try to log an entry to verify the table exists
            LogEntry testEntry = LogEntry.createRequest(
                "INIT_TEST_001",
                "test.topic",
                "Test initialization",
                Instant.now()
            );
            cassandraLogger.log(testEntry);
        }, "Should be able to log after initialization");
    }

    @Test
    @DisplayName("Should log and retrieve entries by request ID")
    void shouldLogAndRetrieveEntriesByRequestId() {
        String requestId = "LOG_RETRIEVE_TEST_" + UUID.randomUUID().toString().substring(0, 8);
        Instant now = Instant.now();

        // Create and log multiple entries with the same request ID
        LogEntry requestEntry = LogEntry.createRequest(requestId, "test.topic", "Test request", now);
        LogEntry responseEntry = LogEntry.createResponse(requestId, "test.topic", "Test response", now.plusMillis(100), 100);
        LogEntry errorEntry = LogEntry.createError(requestId, "test.topic", "Test error", "Error message", now.plusMillis(200));

        cassandraLogger.log(requestEntry);
        cassandraLogger.log(responseEntry);
        cassandraLogger.log(errorEntry);

        // Retrieve entries by request ID
        List<LogEntry> retrievedEntries = cassandraLogger.getLogsByRequestId(requestId);

        assertEquals(3, retrievedEntries.size(), "Should retrieve all 3 logged entries");

        // Verify entries (they should be ordered by timestamp)
        LogEntry firstEntry = retrievedEntries.get(0);
        LogEntry secondEntry = retrievedEntries.get(1);
        LogEntry thirdEntry = retrievedEntries.get(2);

        assertEquals(requestId, firstEntry.getRequestId());
        assertEquals(LogEntry.Type.REQUEST, firstEntry.getType());
        assertEquals("Test request", firstEntry.getMessage());

        assertEquals(requestId, secondEntry.getRequestId());
        assertEquals(LogEntry.Type.RESPONSE, secondEntry.getType());
        assertEquals("Test response", secondEntry.getMessage());
        assertEquals(100L, secondEntry.getDuration());

        assertEquals(requestId, thirdEntry.getRequestId());
        assertEquals(LogEntry.Type.ERROR, thirdEntry.getType());
        assertEquals("Test error", thirdEntry.getMessage());
        assertEquals("Error message", thirdEntry.getErrorDetails());
    }

    @Test
    @DisplayName("Should retrieve entries by time range")
    void shouldRetrieveEntriesByTimeRange() {
        Instant startTime = Instant.now();
        
        // Log entries at different times
        String requestId1 = "TIME_RANGE_1_" + UUID.randomUUID().toString().substring(0, 8);
        String requestId2 = "TIME_RANGE_2_" + UUID.randomUUID().toString().substring(0, 8);
        String requestId3 = "TIME_RANGE_3_" + UUID.randomUUID().toString().substring(0, 8);

        LogEntry entry1 = LogEntry.createRequest(requestId1, "test.topic", "Entry 1", startTime);
        LogEntry entry2 = LogEntry.createRequest(requestId2, "test.topic", "Entry 2", startTime.plusSeconds(5));
        LogEntry entry3 = LogEntry.createRequest(requestId3, "test.topic", "Entry 3", startTime.plusSeconds(10));

        cassandraLogger.log(entry1);
        cassandraLogger.log(entry2);
        cassandraLogger.log(entry3);

        // Retrieve entries in a specific time range
        Instant rangeStart = startTime.minusSeconds(1);
        Instant rangeEnd = startTime.plusSeconds(7);

        List<LogEntry> entriesInRange = cassandraLogger.getLogsByTimeRange(rangeStart, rangeEnd);

        // Should get entry1 and entry2, but not entry3
        assertTrue(entriesInRange.size() >= 2, "Should retrieve at least 2 entries in range");
        
        boolean foundEntry1 = entriesInRange.stream().anyMatch(e -> e.getRequestId().equals(requestId1));
        boolean foundEntry2 = entriesInRange.stream().anyMatch(e -> e.getRequestId().equals(requestId2));
        boolean foundEntry3 = entriesInRange.stream().anyMatch(e -> e.getRequestId().equals(requestId3));

        assertTrue(foundEntry1, "Should find entry 1 in range");
        assertTrue(foundEntry2, "Should find entry 2 in range");
        assertFalse(foundEntry3, "Should not find entry 3 in range");
    }

    @Test
    @DisplayName("Should handle concurrent logging operations")
    void shouldHandleConcurrentLoggingOperations() throws InterruptedException {
        int threadCount = 10;
        int entriesPerThread = 20;
        ExecutorService executor = Executors.newFixedThreadPool(threadCount);
        
        AtomicInteger successCount = new AtomicInteger(0);
        AtomicInteger failureCount = new AtomicInteger(0);

        CompletableFuture<Void>[] futures = new CompletableFuture[threadCount];

        for (int i = 0; i < threadCount; i++) {
            final int threadId = i;
            futures[i] = CompletableFuture.runAsync(() -> {
                for (int j = 0; j < entriesPerThread; j++) {
                    try {
                        String requestId = "CONCURRENT_" + threadId + "_" + j;
                        LogEntry entry = LogEntry.createRequest(
                            requestId,
                            "concurrent.topic",
                            "Concurrent test message from thread " + threadId + " entry " + j,
                            Instant.now()
                        );
                        
                        cassandraLogger.log(entry);
                        successCount.incrementAndGet();
                    } catch (Exception e) {
                        failureCount.incrementAndGet();
                        System.err.println("Failed to log in thread " + threadId + ": " + e.getMessage());
                    }
                }
            }, executor);
        }

        // Wait for all operations to complete
        CompletableFuture.allOf(futures).get(60, TimeUnit.SECONDS);

        executor.shutdown();
        assertTrue(executor.awaitTermination(10, TimeUnit.SECONDS), "Executor should terminate");

        int expectedOperations = threadCount * entriesPerThread;
        assertEquals(expectedOperations, successCount.get(), "All logging operations should succeed");
        assertEquals(0, failureCount.get(), "No logging operations should fail");
    }

    @Test
    @DisplayName("Should handle large log entries efficiently")
    void shouldHandleLargeLogEntriesEfficiently() {
        // Create a large message (1MB)
        StringBuilder largeMessageBuilder = new StringBuilder();
        String baseString = "This is a large log message for testing Cassandra performance. ";
        for (int i = 0; i < 16000; i++) {
            largeMessageBuilder.append(baseString).append(i).append(" ");
        }
        String largeMessage = largeMessageBuilder.toString();

        String requestId = "LARGE_LOG_TEST_" + UUID.randomUUID().toString().substring(0, 8);

        long startTime = System.currentTimeMillis();

        // Log large entry
        LogEntry largeEntry = LogEntry.createRequest(requestId, "large.topic", largeMessage, Instant.now());
        cassandraLogger.log(largeEntry);

        long logTime = System.currentTimeMillis() - startTime;

        startTime = System.currentTimeMillis();

        // Retrieve large entry
        List<LogEntry> retrievedEntries = cassandraLogger.getLogsByRequestId(requestId);

        long retrieveTime = System.currentTimeMillis() - startTime;

        assertEquals(1, retrievedEntries.size(), "Should retrieve the large log entry");
        assertEquals(largeMessage, retrievedEntries.get(0).getMessage(), "Large message should be preserved");
        
        assertTrue(logTime < 5000, "Logging should complete within 5 seconds");
        assertTrue(retrieveTime < 5000, "Retrieval should complete within 5 seconds");
    }

    @Test
    @DisplayName("Should handle different log entry types correctly")
    void shouldHandleDifferentLogEntryTypesCorrectly() {
        String baseRequestId = "TYPE_TEST_" + UUID.randomUUID().toString().substring(0, 8);
        Instant now = Instant.now();

        // Create different types of log entries
        LogEntry requestEntry = LogEntry.createRequest(
            baseRequestId + "_REQ",
            "type.test.topic",
            "Request message",
            now
        );

        LogEntry responseEntry = LogEntry.createResponse(
            baseRequestId + "_RESP",
            "type.test.topic",
            "Response message",
            now.plusMillis(500),
            500L
        );

        LogEntry errorEntry = LogEntry.createError(
            baseRequestId + "_ERR",
            "type.test.topic",
            "Error message",
            "Detailed error information",
            now.plusMillis(1000)
        );

        // Log all entries
        cassandraLogger.log(requestEntry);
        cassandraLogger.log(responseEntry);
        cassandraLogger.log(errorEntry);

        // Retrieve and verify each type
        List<LogEntry> requestEntries = cassandraLogger.getLogsByRequestId(baseRequestId + "_REQ");
        assertEquals(1, requestEntries.size());
        LogEntry retrievedRequest = requestEntries.get(0);
        assertEquals(LogEntry.Type.REQUEST, retrievedRequest.getType());
        assertEquals("Request message", retrievedRequest.getMessage());
        assertNull(retrievedRequest.getDuration());
        assertNull(retrievedRequest.getErrorDetails());

        List<LogEntry> responseEntries = cassandraLogger.getLogsByRequestId(baseRequestId + "_RESP");
        assertEquals(1, responseEntries.size());
        LogEntry retrievedResponse = responseEntries.get(0);
        assertEquals(LogEntry.Type.RESPONSE, retrievedResponse.getType());
        assertEquals("Response message", retrievedResponse.getMessage());
        assertEquals(500L, retrievedResponse.getDuration());
        assertNull(retrievedResponse.getErrorDetails());

        List<LogEntry> errorEntries = cassandraLogger.getLogsByRequestId(baseRequestId + "_ERR");
        assertEquals(1, errorEntries.size());
        LogEntry retrievedError = errorEntries.get(0);
        assertEquals(LogEntry.Type.ERROR, retrievedError.getType());
        assertEquals("Error message", retrievedError.getMessage());
        assertEquals("Detailed error information", retrievedError.getErrorDetails());
        assertNull(retrievedError.getDuration());
    }

    @Test
    @DisplayName("Should handle empty result sets gracefully")
    void shouldHandleEmptyResultSetsGracefully() {
        String nonExistentRequestId = "NON_EXISTENT_" + UUID.randomUUID().toString();
        
        // Try to retrieve logs for non-existent request ID
        List<LogEntry> entries = cassandraLogger.getLogsByRequestId(nonExistentRequestId);
        assertNotNull(entries, "Result should not be null");
        assertTrue(entries.isEmpty(), "Result should be empty for non-existent request ID");

        // Try to retrieve logs for time range with no entries
        Instant futureStart = Instant.now().plusSeconds(3600);
        Instant futureEnd = futureStart.plusSeconds(3600);
        
        List<LogEntry> futureEntries = cassandraLogger.getLogsByTimeRange(futureStart, futureEnd);
        assertNotNull(futureEntries, "Future result should not be null");
        assertTrue(futureEntries.isEmpty(), "Future result should be empty");
    }

    @Test
    @DisplayName("Should maintain data consistency across multiple operations")
    void shouldMaintainDataConsistencyAcrossMultipleOperations() {
        String requestId = "CONSISTENCY_TEST_" + UUID.randomUUID().toString().substring(0, 8);
        Instant baseTime = Instant.now();

        // Log multiple entries for the same request ID at different times
        for (int i = 0; i < 10; i++) {
            LogEntry entry = LogEntry.createRequest(
                requestId,
                "consistency.topic",
                "Message " + i,
                baseTime.plusMillis(i * 100)
            );
            cassandraLogger.log(entry);
        }

        // Retrieve all entries
        List<LogEntry> allEntries = cassandraLogger.getLogsByRequestId(requestId);
        assertEquals(10, allEntries.size(), "Should retrieve all 10 entries");

        // Verify entries are ordered by timestamp
        for (int i = 0; i < allEntries.size() - 1; i++) {
            Instant currentTime = allEntries.get(i).getTimestamp();
            Instant nextTime = allEntries.get(i + 1).getTimestamp();
            assertTrue(currentTime.isBefore(nextTime) || currentTime.equals(nextTime),
                "Entries should be ordered by timestamp");
        }

        // Verify message content
        for (int i = 0; i < allEntries.size(); i++) {
            assertEquals("Message " + i, allEntries.get(i).getMessage(),
                "Message content should match insertion order");
        }
    }

    @Test
    @DisplayName("Should handle connection recovery scenarios")
    void shouldHandleConnectionRecoveryScenarios() {
        String requestId = "RECOVERY_TEST_" + UUID.randomUUID().toString().substring(0, 8);

        // Log an entry before "connection loss"
        LogEntry beforeEntry = LogEntry.createRequest(requestId + "_BEFORE", "recovery.topic", "Before recovery", Instant.now());
        cassandraLogger.log(beforeEntry);

        // Simulate connection recovery by closing and reinitializing
        cassandraLogger.close();
        
        String contactPoint = cassandra.getHost();
        int port = cassandra.getMappedPort(9042);
        
        cassandraLogger = new CassandraLogger(contactPoint, port, "test_datacenter");
        cassandraLogger.initialize();

        // Log an entry after "connection recovery"
        LogEntry afterEntry = LogEntry.createRequest(requestId + "_AFTER", "recovery.topic", "After recovery", Instant.now());
        cassandraLogger.log(afterEntry);

        // Verify both entries can be retrieved
        List<LogEntry> beforeEntries = cassandraLogger.getLogsByRequestId(requestId + "_BEFORE");
        List<LogEntry> afterEntries = cassandraLogger.getLogsByRequestId(requestId + "_AFTER");

        assertEquals(1, beforeEntries.size(), "Should retrieve entry logged before recovery");
        assertEquals(1, afterEntries.size(), "Should retrieve entry logged after recovery");
        
        assertEquals("Before recovery", beforeEntries.get(0).getMessage());
        assertEquals("After recovery", afterEntries.get(0).getMessage());
    }

    @Test
    @DisplayName("Should handle special characters and Unicode in log messages")
    void shouldHandleSpecialCharactersAndUnicodeInLogMessages() {
        String requestId = "UNICODE_TEST_" + UUID.randomUUID().toString().substring(0, 8);
        
        // Test various special characters and Unicode
        String specialMessage = "Special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?`~ Unicode: äöüß中文🚀 Emoji: 😀🎉🔥";
        
        LogEntry unicodeEntry = LogEntry.createRequest(requestId, "unicode.topic", specialMessage, Instant.now());
        cassandraLogger.log(unicodeEntry);

        List<LogEntry> retrievedEntries = cassandraLogger.getLogsByRequestId(requestId);
        assertEquals(1, retrievedEntries.size(), "Should retrieve Unicode entry");
        assertEquals(specialMessage, retrievedEntries.get(0).getMessage(), "Unicode message should be preserved exactly");
    }
}