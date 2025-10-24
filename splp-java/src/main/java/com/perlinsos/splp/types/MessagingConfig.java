package com.perlinsos.splp.types;

import java.util.Objects;

/**
 * Complete messaging configuration combining Kafka, Cassandra, and encryption settings
 */
public class MessagingConfig {
    private final KafkaConfig kafka;
    private final CassandraConfig cassandra;
    private final EncryptionConfig encryption;

    public MessagingConfig(KafkaConfig kafka, CassandraConfig cassandra, EncryptionConfig encryption) {
        this.kafka = Objects.requireNonNull(kafka, "Kafka config cannot be null");
        this.cassandra = Objects.requireNonNull(cassandra, "Cassandra config cannot be null");
        this.encryption = Objects.requireNonNull(encryption, "Encryption config cannot be null");
    }

    public KafkaConfig getKafka() {
        return kafka;
    }

    public KafkaConfig getKafkaConfig() {
        return kafka;
    }

    public CassandraConfig getCassandra() {
        return cassandra;
    }

    public CassandraConfig getCassandraConfig() {
        return cassandra;
    }

    public EncryptionConfig getEncryption() {
        return encryption;
    }

    public EncryptionConfig getEncryptionConfig() {
        return encryption;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        MessagingConfig that = (MessagingConfig) o;
        return Objects.equals(kafka, that.kafka) &&
               Objects.equals(cassandra, that.cassandra) &&
               Objects.equals(encryption, that.encryption);
    }

    @Override
    public int hashCode() {
        return Objects.hash(kafka, cassandra, encryption);
    }

    @Override
    public String toString() {
        return "MessagingConfig{" +
               "kafka=" + kafka +
               ", cassandra=" + cassandra +
               ", encryption=" + encryption +
               '}';
    }
}