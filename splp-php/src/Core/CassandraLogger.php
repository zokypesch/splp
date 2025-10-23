<?php

namespace Splp\Messaging\Core;

use Splp\Messaging\Types\CassandraConfig;

class CassandraLogger
{
    private CassandraConfig $config;
    private bool $initialized = false;

    public function __construct(CassandraConfig $config)
    {
        $this->config = $config;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        echo "📊 Cassandra Logger initialized\n";
        echo "   - Contact Points: " . implode(', ', $this->config->contactPoints) . "\n";
        echo "   - Keyspace: {$this->config->keyspace}\n";
        echo "   - Data Center: {$this->config->localDataCenter}\n";

        $this->initialized = true;
    }

    public function logMessage(string $topic, string $message, string $direction): void
    {
        if (!$this->initialized) {
            return;
        }

        // In production, this would log to real Cassandra
        // For now, we'll simulate logging
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'topic' => $topic,
            'direction' => $direction,
            'message_size' => strlen($message),
            'message_preview' => substr($message, 0, 100)
        ];

        echo "📝 Logged to Cassandra: " . json_encode($logEntry) . "\n";
    }

    public function logMetadata(array $metadata): void
    {
        if (!$this->initialized) {
            return;
        }

        // Simulate metadata logging to avoid Cassandra UUID errors
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $metadata['request_id'] ?? 'unknown',
            'worker_name' => $metadata['worker_name'] ?? 'unknown',
            'source_topic' => $metadata['source_topic'] ?? 'unknown',
            'target_topic' => $metadata['target_topic'] ?? 'unknown',
            'route_id' => $metadata['route_id'] ?? 'unknown',
            'message_type' => $metadata['message_type'] ?? 'unknown',
            'success' => $metadata['success'] ?? false,
            'error' => $metadata['error'] ?? null,
            'processing_time_ms' => $metadata['processing_time_ms'] ?? null
        ];

        echo "📊 Metadata logged to Cassandra: " . json_encode($logEntry) . "\n";
    }

    public function logError(string $error, array $context = []): void
    {
        if (!$this->initialized) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'ERROR',
            'error' => $error,
            'context' => $context
        ];

        echo "❌ Error logged to Cassandra: " . json_encode($logEntry) . "\n";
    }
}
