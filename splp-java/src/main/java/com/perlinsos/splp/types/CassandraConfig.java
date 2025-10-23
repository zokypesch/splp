package com.perlinsos.splp.types;

import java.util.List;
import java.util.Objects;

/**
 * Configuration for Cassandra connection settings
 */
public class CassandraConfig {
    private final List<String> contactPoints;
    private final String localDataCenter;
    private final String keyspace;

    public CassandraConfig(List<String> contactPoints, String localDataCenter, String keyspace) {
        this.contactPoints = Objects.requireNonNull(contactPoints, "Contact points cannot be null");
        this.localDataCenter = Objects.requireNonNull(localDataCenter, "Local data center cannot be null");
        this.keyspace = Objects.requireNonNull(keyspace, "Keyspace cannot be null");
    }

    public List<String> getContactPoints() {
        return contactPoints;
    }

    public String getLocalDataCenter() {
        return localDataCenter;
    }

    public String getKeyspace() {
        return keyspace;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (o == null || getClass() != o.getClass()) return false;
        CassandraConfig that = (CassandraConfig) o;
        return Objects.equals(contactPoints, that.contactPoints) &&
               Objects.equals(localDataCenter, that.localDataCenter) &&
               Objects.equals(keyspace, that.keyspace);
    }

    @Override
    public int hashCode() {
        return Objects.hash(contactPoints, localDataCenter, keyspace);
    }

    @Override
    public String toString() {
        return "CassandraConfig{" +
               "contactPoints=" + contactPoints +
               ", localDataCenter='" + localDataCenter + '\'' +
               ", keyspace='" + keyspace + '\'' +
               '}';
    }
}