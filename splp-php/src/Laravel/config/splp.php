<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    */
    'kafka' => [
        'brokers' => explode(',', env('SPLP_KAFKA_BROKERS', '10.70.1.23:9092')),
        'clientId' => env('SPLP_KAFKA_CLIENT_ID', 'laravel-app'),
        'groupId' => env('SPLP_KAFKA_GROUP_ID', 'laravel-app-group'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cassandra Configuration
    |--------------------------------------------------------------------------
    */
    'cassandra' => [
        'contactPoints' => explode(',', env('SPLP_CASSANDRA_CONTACT_POINTS', 'localhost')),
        'localDataCenter' => env('SPLP_CASSANDRA_LOCAL_DATA_CENTER', 'datacenter1'),
        'keyspace' => env('SPLP_CASSANDRA_KEYSPACE', 'laravel_keyspace'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Configuration
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'encryptionKey' => env('SPLP_ENCRYPTION_KEY', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Center Configuration
    |--------------------------------------------------------------------------
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
    */
    'logging' => [
        'level' => env('SPLP_LOG_LEVEL', 'info'),
        'channel' => env('SPLP_LOG_CHANNEL', 'splp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
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
    */
    'healthCheck' => [
        'checkInterval' => env('SPLP_HEALTH_CHECK_INTERVAL', 30000),
        'timeout' => env('SPLP_HEALTH_CHECK_TIMEOUT', 5000),
        'criticalChecks' => explode(',', env('SPLP_HEALTH_CHECK_CRITICAL', 'kafka,cassandra')),
    ],
];
