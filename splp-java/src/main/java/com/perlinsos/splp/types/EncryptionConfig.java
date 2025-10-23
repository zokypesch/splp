package com.perlinsos.splp.types;

import java.util.Objects;

/**
 * Configuration for encryption settings
 */
public class EncryptionConfig {
    private final String encryptionKey;

    public EncryptionConfig(String encryptionKey) {
        this.encryptionKey = Objects.requireNonNull(encryptionKey, "Encryption key cannot be null");
        if (encryptionKey.length() != 64) {
            throw new IllegalArgumentException("Encryption key must be 64 hex characters (32 bytes) for AES-256");
        }
    }

    public String getEncryptionKey() {
        return encryptionKey;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        EncryptionConfig that = (EncryptionConfig) o;
        return Objects.equals(encryptionKey, that.encryptionKey);
    }

    @Override
    public int hashCode() {
        return Objects.hash(encryptionKey);
    }

    @Override
    public String toString() {
        return "EncryptionConfig{" +
               "encryptionKey='[REDACTED]'" +
               '}';
    }
}