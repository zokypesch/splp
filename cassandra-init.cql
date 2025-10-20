-- Cassandra initialization script with 1 week TTL
-- This sets default TTL for all tables to 604800 seconds (1 week)

-- Note: The actual tables are created by the application
-- This file is a placeholder for future custom initialization
-- TTL is set programmatically in the application code

-- Example of how TTL would be set:
-- CREATE TABLE IF NOT EXISTS keyspace.table_name (
--   id uuid PRIMARY KEY,
--   data text
-- ) WITH default_time_to_live = 604800;

-- Tables created by the application will use TTL in their creation statements
