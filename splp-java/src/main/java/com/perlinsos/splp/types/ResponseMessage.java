package com.perlinsos.splp.types;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.Objects;

/**
 * Response message structure for messaging system
 * @param <T> Type of the payload
 */
public class ResponseMessage<T> {
    @JsonProperty("request_id")
    private final String requestId;
    
    private final T payload;
    
    private final long timestamp;
    
    private final boolean success;
    
    private final String error;

    public ResponseMessage(String requestId, T payload, long timestamp, boolean success, String error) {
        this.requestId = Objects.requireNonNull(requestId, "Request ID cannot be null");
        this.payload = payload;
        this.timestamp = timestamp;
        this.success = success;
        this.error = error;
    }

    public ResponseMessage(String requestId, T payload, boolean success) {
        this(requestId, payload, System.currentTimeMillis(), success, null);
    }

    public ResponseMessage(String requestId, T payload, boolean success, String error) {
        this(requestId, payload, System.currentTimeMillis(), success, error);
    }

    public static <T> ResponseMessage<T> success(String requestId, T payload) {
        return new ResponseMessage<>(requestId, payload, true);
    }

    public static <T> ResponseMessage<T> error(String requestId, String error) {
        return new ResponseMessage<>(requestId, null, false, error);
    }

    public String getRequestId() {
        return requestId;
    }

    public T getPayload() {
        return payload;
    }

    public long getTimestamp() {
        return timestamp;
    }

    public boolean isSuccess() {
        return success;
    }

    public String getError() {
        return error;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        ResponseMessage<?> that = (ResponseMessage<?>) o;
        return timestamp == that.timestamp &&
               success == that.success &&
               Objects.equals(requestId, that.requestId) &&
               Objects.equals(payload, that.payload) &&
               Objects.equals(error, that.error);
    }

    @Override
    public int hashCode() {
        return Objects.hash(requestId, payload, timestamp, success, error);
    }

    @Override
    public String toString() {
        return "ResponseMessage{" +
               "requestId='" + requestId + '\'' +
               ", payload=" + payload +
               ", timestamp=" + timestamp +
               ", success=" + success +
               ", error='" + error + '\'' +
               '}';
    }
}