package com.perlinsos.splp.logging;

import com.datastax.oss.driver.api.core.CqlSession;
import com.datastax.oss.driver.api.core.cql.PreparedStatement;
import com.datastax.oss.driver.api.core.cql.ResultSet;
import com.datastax.oss.driver.api.core.cql.Row;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.perlinsos.splp.types.CassandraConfig;
import com.perlinsos.splp.types.LogEntry;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.net.InetSocketAddress;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;

/**
 * Cassandra Logger for storing message logs
 */
public class CassandraLogger {
    private static final Logger logger = LoggerFactory.getLogger(CassandraLogger.class);
    private static final ObjectMapper objectMapper = new ObjectMapper();

    private final CassandraConfig config;
    private CqlSession session;
    private PreparedStatement insertStatement;
    private PreparedStatement selectByRequestIdStatement;
    private PreparedStatement selectByTimeRangeStatement;
    private volatile boolean isInitialized = false;

    public CassandraLogger(CassandraConfig config) {
        this.config = config;
    }

    /**
     * Initialize Cassandra connection and create tables if needed
     */
    public CompletableFuture<Void> initialize() {
        if (isInitialized) {
            return CompletableFuture.completedFuture(null);
        }

        return CompletableFuture.runAsync(() -> {
            try {
                // Create session
                List<InetSocketAddress> contactPoints = new ArrayList<>();
                for (String contactPoint : config.getContactPoints()) {
                    contactPoints.add(new InetSocketAddress(contactPoint, 9042));
                }

                session = CqlSession.builder()
                    .addContactPoints(contactPoints)
                    .withLocalDatacenter(config.getLocalDataCenter())
                    .build();

                createKeyspaceIfNotExists();
                createTablesIfNotExists();
                prepareStatements();

                isInitialized = true;
                logger.info("Cassandra logger initialized successfully");
            } catch (Exception e) {
                logger.error("Failed to initialize Cassandra logger", e);
                throw new RuntimeException("Failed to initialize Cassandra logger", e);
            }
        });
    }

    private void createKeyspaceIfNotExists() {
        String query = String.format(
            "CREATE KEYSPACE IF NOT EXISTS %s " +
            "WITH replication = {" +
            "'class': 'SimpleStrategy', " +
            "'replication_factor': 1" +
            "}",
            config.getKeyspace()
        );
        session.execute(query);
    }

    private void createTablesIfNotExists() {
        String query = String.format(
            "CREATE TABLE IF NOT EXISTS %s.message_logs (" +
            "request_id uuid, " +
            "timestamp timestamp, " +
            "type text, " +
            "topic text, " +
            "payload text, " +
            "success boolean, " +
            "error text, " +
            "duration_ms int, " +
            "PRIMARY KEY (request_id, timestamp)" +
            ") WITH CLUSTERING ORDER BY (timestamp DESC) " +
            "AND default_time_to_live = 604800",
            config.getKeyspace()
        );
        session.execute(query);

        // Create index for querying by timestamp
        String indexQuery = String.format(
            "CREATE INDEX IF NOT EXISTS ON %s.message_logs (timestamp)",
            config.getKeyspace()
        );
        session.execute(indexQuery);
    }

    private void prepareStatements() {
        String insertQuery = String.format(
            "INSERT INTO %s.message_logs " +
            "(request_id, timestamp, type, topic, payload, success, error, duration_ms) " +
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            config.getKeyspace()
        );
        insertStatement = session.prepare(insertQuery);

        String selectByRequestIdQuery = String.format(
            "SELECT * FROM %s.message_logs " +
            "WHERE request_id = ? " +
            "ORDER BY timestamp DESC",
            config.getKeyspace()
        );
        selectByRequestIdStatement = session.prepare(selectByRequestIdQuery);

        String selectByTimeRangeQuery = String.format(
            "SELECT * FROM %s.message_logs " +
            "WHERE timestamp >= ? AND timestamp <= ? " +
            "ALLOW FILTERING",
            config.getKeyspace()
        );
        selectByTimeRangeStatement = session.prepare(selectByTimeRangeQuery);
    }

    /**
     * Log a request or response message
     */
    public CompletableFuture<Void> log(LogEntry entry) {
        if (!isInitialized) {
            logger.warn("Cassandra logger not initialized, skipping log");
            return CompletableFuture.completedFuture(null);
        }

        return CompletableFuture.runAsync(() -> {
            try {
                String payloadJson = objectMapper.writeValueAsString(entry.getPayload());

                session.execute(insertStatement.bind(
                    UUID.fromString(entry.getRequestId()),
                    entry.getTimestamp(),
                    entry.getType().name(),
                    entry.getTopic(),
                    payloadJson,
                    entry.getSuccess(),
                    entry.getError(),
                    entry.getDurationMs()
                ));
            } catch (Exception e) {
                logger.error("Failed to log to Cassandra", e);
                // Don't throw - logging failure shouldn't break the main flow
            }
        });
    }

    /**
     * Query logs by request_id
     */
    public CompletableFuture<List<LogEntry>> getLogsByRequestId(String requestId) {
        if (!isInitialized) {
            return CompletableFuture.failedFuture(
                new IllegalStateException("Cassandra logger not initialized")
            );
        }

        return CompletableFuture.supplyAsync(() -> {
            try {
                ResultSet result = session.execute(
                    selectByRequestIdStatement.bind(UUID.fromString(requestId))
                );

                List<LogEntry> logs = new ArrayList<>();
                for (Row row : result) {
                    logs.add(mapRowToLogEntry(row));
                }
                return logs;
            } catch (Exception e) {
                throw new RuntimeException("Failed to query logs by request ID", e);
            }
        });
    }

    /**
     * Query logs by time range
     */
    public CompletableFuture<List<LogEntry>> getLogsByTimeRange(Instant startTime, Instant endTime) {
        if (!isInitialized) {
            return CompletableFuture.failedFuture(
                new IllegalStateException("Cassandra logger not initialized")
            );
        }

        return CompletableFuture.supplyAsync(() -> {
            try {
                ResultSet result = session.execute(
                    selectByTimeRangeStatement.bind(startTime, endTime)
                );

                List<LogEntry> logs = new ArrayList<>();
                for (Row row : result) {
                    logs.add(mapRowToLogEntry(row));
                }
                return logs;
            } catch (Exception e) {
                throw new RuntimeException("Failed to query logs by time range", e);
            }
        });
    }

    private LogEntry mapRowToLogEntry(Row row) {
        try {
            Object payload = objectMapper.readValue(row.getString("payload"), Object.class);
            
            return new LogEntry(
                row.getUuid("request_id").toString(),
                row.getInstant("timestamp"),
                LogEntry.LogType.valueOf(row.getString("type")),
                row.getString("topic"),
                payload,
                row.getBoolean("success"),
                row.getString("error"),
                row.getInt("duration_ms")
            );
        } catch (Exception e) {
            throw new RuntimeException("Failed to map row to LogEntry", e);
        }
    }

    /**
     * Close Cassandra connection
     */
    public CompletableFuture<Void> close() {
        return CompletableFuture.runAsync(() -> {
            if (isInitialized && session != null) {
                session.close();
                isInitialized = false;
                logger.info("Cassandra logger closed");
            }
        });
    }

    public boolean isInitialized() {
        return isInitialized;
    }
}