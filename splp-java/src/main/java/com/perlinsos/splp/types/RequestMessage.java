package com.perlinsos.splp.types;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.Objects;

/**
 * Request message structure for messaging system
 * @param <T> Type of the payload
 */
public class RequestMessage<T> {
    @JsonProperty("request_id")
    private final String requestId;
    
    private final T payload;
    
    private final long timestamp;

    public RequestMessage(String requestId, T payload, long timestamp) {
        this.requestId = Objects.requireNonNull(requestId, "Request ID cannot be null");
        this.payload = Objects.requireNonNull(payload, "Payload cannot be null");
        this.timestamp = timestamp;
    }

    public RequestMessage(String requestId, T payload) {
        this(requestId, payload, System.currentTimeMillis());
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

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        RequestMessage<?> that = (RequestMessage<?>) o;
        return timestamp == that.timestamp &&
               Objects.equals(requestId, that.requestId) &&
               Objects.equals(payload, that.payload);
    }

    @Override
    public int hashCode() {
        return Objects.hash(requestId, payload, timestamp);
    }

    @Override
    public String toString() {
        return "RequestMessage{" +
               "requestId='" + requestId + '\'' +
               ", payload=" + payload +
               ", timestamp=" + timestamp +
               '}';
    }
}