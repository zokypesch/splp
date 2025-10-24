package com.perlinsos.splp.types;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.time.Instant;
import java.util.Objects;

/**
 * Log entry structure for Cassandra logging
 */
public class LogEntry {
    @JsonProperty("request_id")
    private final String requestId;
    
    private final Instant timestamp;
    
    private final LogType type;
    
    private final String topic;
    
    private final Object payload;
    
    private final Boolean success;
    
    private final String error;
    
    @JsonProperty("duration_ms")
    private final Long durationMs;

    public enum LogType {
        @JsonProperty("request")
        REQUEST,
        @JsonProperty("response")
        RESPONSE
    }

    public LogEntry(String requestId, Instant timestamp, LogType type, String topic, 
                   Object payload, Boolean success, String error, Long durationMs) {
        this.requestId = Objects.requireNonNull(requestId, "Request ID cannot be null");
        this.timestamp = Objects.requireNonNull(timestamp, "Timestamp cannot be null");
        this.type = Objects.requireNonNull(type, "Type cannot be null");
        this.topic = Objects.requireNonNull(topic, "Topic cannot be null");
        this.payload = payload;
        this.success = success;
        this.error = error;
        this.durationMs = durationMs;
    }

    public static LogEntry request(String requestId, String topic, Object payload) {
        return new LogEntry(requestId, Instant.now(), LogType.REQUEST, topic, payload, null, null, null);
    }

    public static LogEntry response(String requestId, String topic, Object payload, boolean success, Long durationMs) {
        return new LogEntry(requestId, Instant.now(), LogType.RESPONSE, topic, payload, success, null, durationMs);
    }

    public static LogEntry error(String requestId, String topic, String error, Long durationMs) {
        return new LogEntry(requestId, Instant.now(), LogType.RESPONSE, topic, null, false, error, durationMs);
    }

    public static LogEntry createRequestLog(String requestId, Instant timestamp, String topic, Object payload) {
        return new LogEntry(requestId, timestamp, LogType.REQUEST, topic, payload, null, null, null);
    }

    public static LogEntry createResponseLog(String requestId, Instant timestamp, String topic, Object payload, boolean success, String error, int durationMs) {
        return new LogEntry(requestId, timestamp, LogType.RESPONSE, topic, payload, success, error, (long) durationMs);
    }

    public static LogEntry createErrorLog(String requestId, Instant timestamp, String topic, Object payload, String error, int durationMs) {
        return new LogEntry(requestId, timestamp, LogType.RESPONSE, topic, payload, false, error, (long) durationMs);
    }

    public String getRequestId() {
        return requestId;
    }

    public Instant getTimestamp() {
        return timestamp;
    }

    public LogType getType() {
        return type;
    }

    public String getTopic() {
        return topic;
    }

    public Object getPayload() {
        return payload;
    }

    public Boolean getSuccess() {
        return success;
    }

    public String getError() {
        return error;
    }

    public Long getDurationMs() {
        return durationMs;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        LogEntry logEntry = (LogEntry) o;
        return Objects.equals(requestId, logEntry.requestId) &&
               Objects.equals(timestamp, logEntry.timestamp) &&
               type == logEntry.type &&
               Objects.equals(topic, logEntry.topic) &&
               Objects.equals(payload, logEntry.payload) &&
               Objects.equals(success, logEntry.success) &&
               Objects.equals(error, logEntry.error) &&
               Objects.equals(durationMs, logEntry.durationMs);
    }

    @Override
    public int hashCode() {
        return Objects.hash(requestId, timestamp, type, topic, payload, success, error, durationMs);
    }

    @Override
    public String toString() {
        return "LogEntry{" +
               "requestId='" + requestId + '\'' +
               ", timestamp=" + timestamp +
               ", type=" + type +
               ", topic='" + topic + '\'' +
               ", payload=" + payload +
               ", success=" + success +
               ", error='" + error + '\'' +
               ", durationMs=" + durationMs +
               '}';
    }
}