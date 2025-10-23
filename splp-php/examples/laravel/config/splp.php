<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Kafka messaging system used by SPLP
    |
    */
    'kafka' => [
        'brokers' => explode(',', env('SPLP_KAFKA_BROKERS', '10.70.1.23:9092')),
        'clientId' => env('SPLP_KAFKA_CLIENT_ID', 'dukcapil-service'),
        'groupId' => env('SPLP_KAFKA_GROUP_ID', 'service-1z-group'),
        'requestTimeoutMs' => env('SPLP_KAFKA_REQUEST_TIMEOUT_MS', 30000),
        'consumerTopic' => env('SPLP_KAFKA_CONSUMER_TOPIC', 'service-1-topic'),
        'producerTopic' => env('SPLP_KAFKA_PRODUCER_TOPIC', 'command-center-inbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cassandra Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cassandra database used for logging and tracing
    |
    */
    'cassandra' => [
        'contactPoints' => explode(',', env('SPLP_CASSANDRA_CONTACT_POINTS', 'localhost')),
        'localDataCenter' => env('SPLP_CASSANDRA_LOCAL_DATA_CENTER', 'datacenter1'),
        'keyspace' => env('SPLP_CASSANDRA_KEYSPACE', 'service_1_keyspace'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for message encryption using AES-256-GCM
    |
    */
    'encryption' => [
        'key' => env('SPLP_ENCRYPTION_KEY', 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the SPLP service itself
    |
    */
    'service' => [
        'name' => env('SPLP_SERVICE_NAME', 'Dukcapil Service'),
        'version' => env('SPLP_SERVICE_VERSION', '1.0.0'),
        'workerName' => env('SPLP_SERVICE_WORKER_NAME', 'service-1-publisher'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Center Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Command Center integration
    |
    */
    'commandCenter' => [
        'inboxTopic' => env('SPLP_COMMAND_CENTER_INBOX_TOPIC', 'command-center-inbox'),
        'enableAutoRouting' => env('SPLP_COMMAND_CENTER_ENABLE_AUTO_ROUTING', true),
        'defaultTimeout' => env('SPLP_COMMAND_CENTER_DEFAULT_TIMEOUT', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SPLP logging
    |
    */
    'logging' => [
        'level' => env('SPLP_LOG_LEVEL', 'info'),
        'channel' => env('SPLP_LOG_CHANNEL', 'splp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for circuit breaker pattern
    |
    */
    'circuitBreaker' => [
        'failureThreshold' => env('SPLP_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'recoveryTimeout' => env('SPLP_CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 30000),
        'monitoringPeriod' => env('SPLP_CIRCUIT_BREAKER_MONITORING_PERIOD', 60000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retry mechanism
    |
    */
    'retry' => [
        'maxAttempts' => env('SPLP_RETRY_MAX_ATTEMPTS', 3),
        'baseDelay' => env('SPLP_RETRY_BASE_DELAY', 1000),
        'maxDelay' => env('SPLP_RETRY_MAX_DELAY', 10000),
        'backoffMultiplier' => env('SPLP_RETRY_BACKOFF_MULTIPLIER', 2),
        'jitter' => env('SPLP_RETRY_JITTER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for health check monitoring
    |
    */
    'healthCheck' => [
        'checkInterval' => env('SPLP_HEALTH_CHECK_INTERVAL', 30000),
        'timeout' => env('SPLP_HEALTH_CHECK_TIMEOUT', 5000),
        'criticalChecks' => explode(',', env('SPLP_HEALTH_CHECK_CRITICAL', 'kafka,cassandra')),
    ],
];
