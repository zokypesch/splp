<?php

declare(strict_types=1);

namespace Splp\Messaging\Core\Logging;

use Splp\Messaging\Exceptions\MessagingException;
use Cassandra\Cluster;
use Cassandra\Session;
use Cassandra\ExecutionOptions;

/**
 * Cassandra Logger
 * 
 * Provides logging and tracing functionality with Cassandra backend
 * Compatible with the TypeScript/Bun implementation
 */
class CassandraLogger
{
    private Cluster $cluster;
    private Session $session;
    private array $config;
    private bool $initialized = false;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeCluster();
    }

    /**
     * Initialize Cassandra connection and create tables
     */
    public function initialize(): void
    {
        try {
            $this->session = $this->cluster->connect();
            $this->createKeyspace();
            $this->createTables();
            $this->initialized = true;
        } catch (\Exception $e) {
            throw new MessagingException('Failed to initialize Cassandra logger: ' . $e->getMessage());
        }
    }

    /**
     * Log entry to Cassandra
     */
    public function log(array $entry): void
    {
        if (!$this->initialized) {
            throw new MessagingException('Cassandra logger not initialized');
        }

        try {
            $statement = $this->session->prepare(
                "INSERT INTO {$this->config['keyspace']}.message_logs 
                 (request_id, timestamp, type, topic, payload, success, error, duration_ms) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $this->session->execute($statement, [
                'arguments' => [
                    $entry['request_id'],
                    $entry['timestamp'],
                    $entry['type'],
                    $entry['topic'],
                    $entry['payload'] ? json_encode($entry['payload']) : null,
                    $entry['success'] ?? null,
                    $entry['error'] ?? null,
                    $entry['duration_ms'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            error_log('Failed to log to Cassandra: ' . $e->getMessage());
        }
    }

    /**
     * Get logs by request ID
     */
    public function getByRequestId(string $requestId): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->config['keyspace']}.message_logs 
             WHERE request_id = ? ORDER BY timestamp DESC"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$requestId]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Get logs by topic
     */
    public function getByTopic(string $topic, int $limit = 100): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->config['keyspace']}.message_logs 
             WHERE topic = ? ORDER BY timestamp DESC LIMIT ?"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$topic, $limit]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Get logs by time range
     */
    public function getByTimeRange(\DateTime $startTime, \DateTime $endTime): array
    {
        $statement = $this->session->prepare(
            "SELECT * FROM {$this->config['keyspace']}.message_logs 
             WHERE timestamp >= ? AND timestamp <= ? ORDER BY timestamp DESC"
        );

        $result = $this->session->execute($statement, [
            'arguments' => [$startTime, $endTime]
        ]);

        return $this->formatResults($result);
    }

    /**
     * Health check
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->session->prepare("SELECT now() FROM system.local");
            $this->session->execute($statement);
            
            return [
                'status' => 'healthy',
                'responseTime' => (microtime(true) - $startTime) * 1000,
                'lastChecked' => new \DateTime(),
                'metadata' => [
                    'keyspace' => $this->config['keyspace']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'responseTime' => (microtime(true) - $startTime) * 1000,
                'lastChecked' => new \DateTime()
            ];
        }
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
    private function initializeCluster(): void
    {
        $this->cluster = \Cassandra::cluster()
            ->withContactPoints($this->config['contactPoints'])
            ->withDefaultConsistency(\Cassandra::CONSISTENCY_ONE)
            ->build();
    }

    /**
     * Create keyspace if not exists
     */
    private function createKeyspace(): void
    {
        $keyspace = $this->config['keyspace'];
        
        $this->session->execute(
            "CREATE KEYSPACE IF NOT EXISTS {$keyspace} 
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
        $keyspace = $this->config['keyspace'];
        
        // Message logs table with 1 week TTL
        $this->session->execute(
            "CREATE TABLE IF NOT EXISTS {$keyspace}.message_logs (
                request_id text,
                timestamp timestamp,
                type text,
                topic text,
                payload text,
                success boolean,
                error text,
                duration_ms int,
                PRIMARY KEY (request_id, timestamp)
            ) WITH CLUSTERING ORDER BY (timestamp DESC)
            AND default_time_to_live = 604800"
        );

        // Create index for topic queries
        $this->session->execute(
            "CREATE INDEX IF NOT EXISTS ON {$keyspace}.message_logs (topic)"
        );
    }

    /**
     * Format Cassandra results
     */
    private function formatResults($result): array
    {
        $logs = [];
        
        foreach ($result as $row) {
            $logs[] = [
                'request_id' => $row['request_id'],
                'timestamp' => $row['timestamp'],
                'type' => $row['type'],
                'topic' => $row['topic'],
                'payload' => $row['payload'] ? json_decode($row['payload'], true) : null,
                'success' => $row['success'],
                'error' => $row['error'],
                'duration_ms' => $row['duration_ms']
            ];
        }
        
        return $logs;
    }
}
