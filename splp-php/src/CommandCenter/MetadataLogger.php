<?php

declare(strict_types=1);

namespace Splp\Messaging\CommandCenter;

use Splp\Messaging\Contracts\MetadataLoggerInterface;
use Splp\Messaging\Exceptions\MessagingException;
use Cassandra\Cluster;
use Cassandra\Session;

/**
 * Metadata Logger
 * 
 * Logs routing metadata to Cassandra (NO PAYLOAD)
 * Compatible with the TypeScript/Bun implementation
 */
class MetadataLogger implements MetadataLoggerInterface
{
    private Cluster $cluster;
    private Session $session;
    private string $keyspace;
    private bool $initialized = false;

    public function __construct(array $contactPoints, string $localDataCenter, string $keyspace)
    {
        $this->keyspace = $keyspace;
        $this->initializeCluster($contactPoints, $localDataCenter);
    }

    /**
     * Initialize metadata logger
     */
    public function initialize(): void
    {
        try {
            $this->session = $this->cluster->connect();
            $this->createKeyspace();
            $this->createTables();
            $this->initialized = true;
        } catch (\Exception $e) {
            throw new MessagingException('Failed to initialize Metadata Logger: ' . $e->getMessage());
        }
    }

    /**
     * Log routing metadata
     */
    public function log(array $metadata): void
    {
        if (!$this->initialized) {
            throw new MessagingException('Metadata Logger not initialized');
        }

        try {
            $statement = $this->session->prepare(
                "INSERT INTO {$this->keyspace}.routing_metadata 
                 (request_id, worker_name, timestamp, source_topic, target_topic, route_id, message_type, success, error, processing_time_ms) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $this->session->execute($statement, [
                'arguments' => [
                    $metadata['request_id'],
                    $metadata['worker_name'],
                    $metadata['timestamp'],
                    $metadata['source_topic'],
                    $metadata['target_topic'],
                    $metadata['route_id'],
                    $metadata['message_type'],
                    $metadata['success'],
                    $metadata['error'] ?? null,
                    $metadata['processing_time_ms'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Failed to log metadata to Cassandra: ' . $e->getMessage());
        }
    }

    /**
     * Get metadata by request ID
     */
    public function getByRequestId(string $requestId): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->keyspace}.routing_metadata 
             WHERE request_id = ? ORDER BY timestamp DESC"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$requestId]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Get metadata by worker name
     */
    public function getByWorkerName(string $workerName, int $limit = 100): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->keyspace}.routing_metadata 
             WHERE worker_name = ? ORDER BY timestamp DESC LIMIT ?"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$workerName, $limit]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Get metadata by time range
     */
    public function getByTimeRange(\DateTime $startTime, \DateTime $endTime): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->keyspace}.routing_metadata 
             WHERE timestamp >= ? AND timestamp <= ? ORDER BY timestamp DESC"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$startTime, $endTime]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Get route statistics
     */
    public function getRouteStats(string $routeId): array
    {
        $statement = $this->session->prepare(
            "SELECT COUNT(*) as total_messages,
                    SUM(CASE WHEN success = true THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN success = false THEN 1 ELSE 0 END) as failure_count,
                    AVG(processing_time_ms) as avg_processing_time
             FROM {$this->keyspace}.routing_metadata 
             WHERE route_id = ?"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$routeId]
        ]);

        $row = $result->first();
        
        return [
            'totalMessages' => $row['total_messages'],
            'successCount' => $row['success_count'],
            'failureCount' => $row['failure_count'],
            'avgProcessingTime' => $row['avg_processing_time']
        ];
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->session) {
            $this->session->close();
        }
        
        if ($this->cluster) {
            $this->cluster->close();
        }
    }

    /**
     * Initialize Cassandra cluster
     */
    private function initializeCluster(array $contactPoints, string $localDataCenter): void
    {
        $this->cluster = \Cassandra::cluster()
            ->withContactPoints($contactPoints)
            ->withDefaultConsistency(\Cassandra::CONSISTENCY_ONE)
            ->build();
    }

    /**
     * Create keyspace if not exists
     */
    private function createKeyspace(): void
    {
        $this->session->execute(
            "CREATE KEYSPACE IF NOT EXISTS {$this->keyspace} 
             WITH REPLICATION = {
                 'class': 'SimpleStrategy',
                 'replication_factor': 1
             } AND DURABLE_WRITES = true"
        );
    }

    /**
     * Create tables if not exist
     */
    private function createTables(): void
    {
        // Routing metadata table with 1 week TTL
        $this->session->execute(
            "CREATE TABLE IF NOT EXISTS {$this->keyspace}.routing_metadata (
                request_id uuid,
                worker_name text,
                timestamp timestamp,
                source_topic text,
                target_topic text,
                route_id text,
                message_type text,
                success boolean,
                error text,
                processing_time_ms int,
                PRIMARY KEY (request_id, timestamp)
            ) WITH CLUSTERING ORDER BY (timestamp DESC)
            AND default_time_to_live = 604800"
        );

        // Create indexes for efficient queries
        $this->session->execute(
            "CREATE INDEX IF NOT EXISTS ON {$this->keyspace}.routing_metadata (worker_name)"
        );
        
        $this->session->execute(
            "CREATE INDEX IF NOT EXISTS ON {$this->keyspace}.routing_metadata (route_id)"
        );
    }

    /**
     * Format Cassandra results
     */
    private function formatResults($result): array
    {
        $metadata = [];
        
        foreach ($result as $row) {
            $metadata[] = [
                'request_id' => $row['request_id'],
                'worker_name' => $row['worker_name'],
                'timestamp' => $row['timestamp'],
                'source_topic' => $row['source_topic'],
                'target_topic' => $row['target_topic'],
                'route_id' => $row['route_id'],
                'message_type' => $row['message_type'],
                'success' => $row['success'],
                'error' => $row['error'],
                'processing_time_ms' => $row['processing_time_ms']
            ];
        }
        
        return $metadata;
    }
}
