package com.perlinsos.splp.types;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.BeforeEach;

import java.time.Instant;
import java.time.temporal.ChronoUnit;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("LogEntry Tests")
class LogEntryTest {

    private String requestId;
    private Instant timestamp;
    private String topic;
    private String payload;

    @BeforeEach
    void setUp() {
        requestId = "TEST123456789ABC";
        timestamp = Instant.now();
        topic = "test-topic";
        payload = "test payload";
    }

    @Test
    @DisplayName("Should create LogEntry with all parameters")
    void shouldCreateLogEntryWithAllParameters() {
        LogEntry logEntry = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );

        assertEquals(requestId, logEntry.getRequestId());
        assertEquals(timestamp, logEntry.getTimestamp());
        assertEquals(LogEntry.Type.REQUEST, logEntry.getType());
        assertEquals(topic, logEntry.getTopic());
        assertEquals(payload, logEntry.getPayload());
        assertTrue(logEntry.isSuccess());
        assertNull(logEntry.getError());
        assertEquals(100L, logEntry.getDurationMs());
    }

    @Test
    @DisplayName("Should create LogEntry with default constructor")
    void shouldCreateLogEntryWithDefaultConstructor() {
        LogEntry logEntry = new LogEntry();

        assertNull(logEntry.getRequestId());
        assertNull(logEntry.getTimestamp());
        assertNull(logEntry.getType());
        assertNull(logEntry.getTopic());
        assertNull(logEntry.getPayload());
        assertFalse(logEntry.isSuccess());
        assertNull(logEntry.getError());
        assertNull(logEntry.getDurationMs());
    }

    @Test
    @DisplayName("Should create request log entry using factory method")
    void shouldCreateRequestLogEntryUsingFactoryMethod() {
        LogEntry logEntry = LogEntry.createRequest(requestId, topic, payload);

        assertEquals(requestId, logEntry.getRequestId());
        assertNotNull(logEntry.getTimestamp());
        assertEquals(LogEntry.Type.REQUEST, logEntry.getType());
        assertEquals(topic, logEntry.getTopic());
        assertEquals(payload, logEntry.getPayload());
        assertTrue(logEntry.isSuccess());
        assertNull(logEntry.getError());
        assertNull(logEntry.getDurationMs());

        // Timestamp should be recent (within last second)
        assertTrue(logEntry.getTimestamp().isAfter(Instant.now().minus(1, ChronoUnit.SECONDS)));
    }

    @Test
    @DisplayName("Should create response log entry using factory method")
    void shouldCreateResponseLogEntryUsingFactoryMethod() {
        Long durationMs = 250L;
        LogEntry logEntry = LogEntry.createResponse(requestId, topic, payload, durationMs);

        assertEquals(requestId, logEntry.getRequestId());
        assertNotNull(logEntry.getTimestamp());
        assertEquals(LogEntry.Type.RESPONSE, logEntry.getType());
        assertEquals(topic, logEntry.getTopic());
        assertEquals(payload, logEntry.getPayload());
        assertTrue(logEntry.isSuccess());
        assertNull(logEntry.getError());
        assertEquals(durationMs, logEntry.getDurationMs());

        // Timestamp should be recent (within last second)
        assertTrue(logEntry.getTimestamp().isAfter(Instant.now().minus(1, ChronoUnit.SECONDS)));
    }

    @Test
    @DisplayName("Should create error log entry using factory method")
    void shouldCreateErrorLogEntryUsingFactoryMethod() {
        String errorMessage = "Operation failed";
        Long durationMs = 150L;
        LogEntry logEntry = LogEntry.createError(requestId, topic, errorMessage, durationMs);

        assertEquals(requestId, logEntry.getRequestId());
        assertNotNull(logEntry.getTimestamp());
        assertEquals(LogEntry.Type.RESPONSE, logEntry.getType());
        assertEquals(topic, logEntry.getTopic());
        assertNull(logEntry.getPayload());
        assertFalse(logEntry.isSuccess());
        assertEquals(errorMessage, logEntry.getError());
        assertEquals(durationMs, logEntry.getDurationMs());

        // Timestamp should be recent (within last second)
        assertTrue(logEntry.getTimestamp().isAfter(Instant.now().minus(1, ChronoUnit.SECONDS)));
    }

    @Test
    @DisplayName("Should handle null values in factory methods")
    void shouldHandleNullValuesInFactoryMethods() {
        // Request with null payload
        LogEntry requestEntry = LogEntry.createRequest(requestId, topic, null);
        assertNull(requestEntry.getPayload());
        assertEquals(LogEntry.Type.REQUEST, requestEntry.getType());

        // Response with null payload and duration
        LogEntry responseEntry = LogEntry.createResponse(requestId, topic, null, null);
        assertNull(responseEntry.getPayload());
        assertNull(responseEntry.getDurationMs());
        assertEquals(LogEntry.Type.RESPONSE, responseEntry.getType());

        // Error with null error message and duration
        LogEntry errorEntry = LogEntry.createError(requestId, topic, null, null);
        assertNull(errorEntry.getError());
        assertNull(errorEntry.getDurationMs());
        assertEquals(LogEntry.Type.RESPONSE, errorEntry.getType());
    }

    @Test
    @DisplayName("Should implement equals correctly")
    void shouldImplementEqualsCorrectly() {
        LogEntry entry1 = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );
        LogEntry entry2 = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );
        LogEntry entry3 = new LogEntry(
            "DIFFERENT123", timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );

        assertEquals(entry1, entry2, "Entries with same values should be equal");
        assertNotEquals(entry1, entry3, "Entries with different values should not be equal");
        assertNotEquals(entry1, null, "Entry should not equal null");
        assertNotEquals(entry1, "string", "Entry should not equal different type");
    }

    @Test
    @DisplayName("Should implement hashCode correctly")
    void shouldImplementHashCodeCorrectly() {
        LogEntry entry1 = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );
        LogEntry entry2 = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );

        assertEquals(entry1.hashCode(), entry2.hashCode(), "Equal entries should have same hash code");
    }

    @Test
    @DisplayName("Should implement toString correctly")
    void shouldImplementToStringCorrectly() {
        LogEntry logEntry = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, 100L
        );

        String toString = logEntry.toString();

        assertNotNull(toString, "toString should not return null");
        assertTrue(toString.contains(requestId), "toString should contain request ID");
        assertTrue(toString.contains("REQUEST"), "toString should contain type");
        assertTrue(toString.contains(topic), "toString should contain topic");
        assertTrue(toString.contains("true"), "toString should contain success status");
    }

    @Test
    @DisplayName("Should handle all LogEntry.Type values")
    void shouldHandleAllLogEntryTypeValues() {
        // Test REQUEST type
        LogEntry requestEntry = new LogEntry(
            requestId, timestamp, LogEntry.Type.REQUEST, topic, payload, true, null, null
        );
        assertEquals(LogEntry.Type.REQUEST, requestEntry.getType());

        // Test RESPONSE type
        LogEntry responseEntry = new LogEntry(
            requestId, timestamp, LogEntry.Type.RESPONSE, topic, payload, true, null, 100L
        );
        assertEquals(LogEntry.Type.RESPONSE, responseEntry.getType());

        // Verify enum values
        LogEntry.Type[] types = LogEntry.Type.values();
        assertEquals(2, types.length, "Should have exactly 2 type values");
        assertTrue(java.util.Arrays.asList(types).contains(LogEntry.Type.REQUEST));
        assertTrue(java.util.Arrays.asList(types).contains(LogEntry.Type.RESPONSE));
    }

    @Test
    @DisplayName("Should handle setters correctly")
    void shouldHandleSettersCorrectly() {
        LogEntry logEntry = new LogEntry();

        // Set all fields
        logEntry.setRequestId(requestId);
        logEntry.setTimestamp(timestamp);
        logEntry.setType(LogEntry.Type.REQUEST);
        logEntry.setTopic(topic);
        logEntry.setPayload(payload);
        logEntry.setSuccess(true);
        logEntry.setError("test error");
        logEntry.setDurationMs(200L);

        // Verify all fields
        assertEquals(requestId, logEntry.getRequestId());
        assertEquals(timestamp, logEntry.getTimestamp());
        assertEquals(LogEntry.Type.REQUEST, logEntry.getType());
        assertEquals(topic, logEntry.getTopic());
        assertEquals(payload, logEntry.getPayload());
        assertTrue(logEntry.isSuccess());
        assertEquals("test error", logEntry.getError());
        assertEquals(200L, logEntry.getDurationMs());
    }

    @Test
    @DisplayName("Should handle edge cases in factory methods")
    void shouldHandleEdgeCasesInFactoryMethods() {
        // Empty strings
        LogEntry entry1 = LogEntry.createRequest("", "", "");
        assertEquals("", entry1.getRequestId());
        assertEquals("", entry1.getTopic());
        assertEquals("", entry1.getPayload());

        // Very long strings
        String longString = "a".repeat(10000);
        LogEntry entry2 = LogEntry.createRequest(longString, longString, longString);
        assertEquals(longString, entry2.getRequestId());
        assertEquals(longString, entry2.getTopic());
        assertEquals(longString, entry2.getPayload());

        // Zero duration
        LogEntry entry3 = LogEntry.createResponse(requestId, topic, payload, 0L);
        assertEquals(0L, entry3.getDurationMs());

        // Negative duration (should be allowed as it might represent timing issues)
        LogEntry entry4 = LogEntry.createError(requestId, topic, "error", -1L);
        assertEquals(-1L, entry4.getDurationMs());
    }

    @Test
    @DisplayName("Should maintain immutability of timestamp in factory methods")
    void shouldMaintainImmutabilityOfTimestampInFactoryMethods() {
        Instant before = Instant.now();
        
        LogEntry entry1 = LogEntry.createRequest(requestId, topic, payload);
        Thread.yield(); // Allow some time to pass
        LogEntry entry2 = LogEntry.createRequest(requestId, topic, payload);
        
        Instant after = Instant.now();

        // Timestamps should be different and within expected range
        assertNotEquals(entry1.getTimestamp(), entry2.getTimestamp(), 
                       "Different log entries should have different timestamps");
        assertTrue(entry1.getTimestamp().isAfter(before.minus(1, ChronoUnit.SECONDS)));
        assertTrue(entry1.getTimestamp().isBefore(after.plus(1, ChronoUnit.SECONDS)));
        assertTrue(entry2.getTimestamp().isAfter(before.minus(1, ChronoUnit.SECONDS)));
        assertTrue(entry2.getTimestamp().isBefore(after.plus(1, ChronoUnit.SECONDS)));
    }
}