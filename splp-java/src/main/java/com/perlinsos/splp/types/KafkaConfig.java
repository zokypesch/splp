package com.perlinsos.splp.types;

import java.util.List;
import java.util.Objects;

/**
 * Configuration for Kafka connection settings
 */
public class KafkaConfig {
    private final List<String> brokers;
    private final String clientId;
    private final String groupId;

    public KafkaConfig(List<String> brokers, String clientId, String groupId) {
        this.brokers = Objects.requireNonNull(brokers, "Brokers cannot be null");
        this.clientId = Objects.requireNonNull(clientId, "Client ID cannot be null");
        this.groupId = groupId; // Can be null
    }

    public KafkaConfig(List<String> brokers, String clientId) {
        this(brokers, clientId, null);
    }

    public List<String> getBrokers() {
        return brokers;
    }

    public String getClientId() {
        return clientId;
    }

    public String getGroupId() {
        return groupId;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        KafkaConfig that = (KafkaConfig) o;
        return Objects.equals(brokers, that.brokers) &&
               Objects.equals(clientId, that.clientId) &&
               Objects.equals(groupId, that.groupId);
    }

    @Override
    public int hashCode() {
        return Objects.hash(brokers, clientId, groupId);
    }

    @Override
    public String toString() {
        return "KafkaConfig{" +
               "brokers=" + brokers +
               ", clientId='" + clientId + '\'' +
               ", groupId='" + groupId + '\'' +
               '}';
    }
}