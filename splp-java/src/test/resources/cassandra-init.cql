-- Cassandra initialization script for integration tests
-- This script sets up the test environment for CassandraIntegrationTest

-- Create test keyspace (will be recreated by the application, but this ensures clean state)
CREATE KEYSPACE IF NOT EXISTS splp_test
WITH replication = {
    'class': 'SimpleStrategy',
    'replication_factor': 1
};

-- Use the test keyspace
USE splp_test;

-- Create message_logs table (will be recreated by the application)
CREATE TABLE IF NOT EXISTS message_logs (
    request_id text,
    timestamp timestamp,
    type text,
    topic text,
    message text,
    duration bigint,
    error_details text,
    PRIMARY KEY (request_id, timestamp)
) WITH CLUSTERING ORDER BY (timestamp ASC);

-- Create index on timestamp for time-range queries
CREATE INDEX IF NOT EXISTS idx_message_logs_timestamp ON message_logs (timestamp);