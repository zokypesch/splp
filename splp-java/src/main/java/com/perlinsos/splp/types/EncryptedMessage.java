package com.perlinsos.splp.types;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.Objects;

/**
 * Encrypted message structure containing encrypted data with IV and authentication tag
 */
public class EncryptedMessage {
    @JsonProperty("request_id")
    private final String requestId;
    
    private final String data;
    
    private final String iv;
    
    private final String tag;

    public EncryptedMessage(String requestId, String data, String iv, String tag) {
        this.requestId = Objects.requireNonNull(requestId, "Request ID cannot be null");
        this.data = Objects.requireNonNull(data, "Data cannot be null");
        this.iv = Objects.requireNonNull(iv, "IV cannot be null");
        this.tag = Objects.requireNonNull(tag, "Tag cannot be null");
    }

    public String getRequestId() {
        return requestId;
    }

    public String getData() {
        return data;
    }

    public String getIv() {
        return iv;
    }

    public String getTag() {
        return tag;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        EncryptedMessage that = (EncryptedMessage) o;
        return Objects.equals(requestId, that.requestId) &&
               Objects.equals(data, that.data) &&
               Objects.equals(iv, that.iv) &&
               Objects.equals(tag, that.tag);
    }

    @Override
    public int hashCode() {
        return Objects.hash(requestId, data, iv, tag);
    }

    @Override
    public String toString() {
        return "EncryptedMessage{" +
               "requestId='" + requestId + '\'' +
               ", data='[ENCRYPTED]'" +
               ", iv='" + iv + '\'' +
               ", tag='" + tag + '\'' +
               '}';
    }
}