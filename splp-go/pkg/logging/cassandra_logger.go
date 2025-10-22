package logging

import (
	"fmt"
	"log"
	"time"

	"github.com/gocql/gocql"
	"github.com/perlinsos/splp-go/internal/types"
)

// SessionInterface abstracts the gocql.Session for testing
type SessionInterface interface {
	Query(stmt string, values ...interface{}) *gocql.Query
	Close()
}

// CassandraLogger implements the Logger interface for Cassandra
type CassandraLogger struct {
	session  SessionInterface
	keyspace string
	table    string
}

// NewCassandraLogger creates a new Cassandra logger instance
func NewCassandraLogger(config *types.CassandraConfig) (*CassandraLogger, error) {
	if config == nil {
		return nil, fmt.Errorf("cassandra config cannot be nil")
	}

	if err := validateConfig(config); err != nil {
		return nil, fmt.Errorf("invalid cassandra config: %w", err)
	}

	logger := &CassandraLogger{
		keyspace: config.Keyspace,
		table:    config.Table,
	}

	return logger, nil
}

// Initialize sets up the Cassandra connection and creates necessary tables
func (c *CassandraLogger) Initialize(config *types.CassandraConfig) error {
	// Create cluster configuration
	cluster := gocql.NewCluster(config.Hosts...)
	cluster.Keyspace = config.Keyspace
	cluster.Consistency = gocql.Quorum
	cluster.Timeout = time.Duration(config.TimeoutMs) * time.Millisecond
	cluster.ConnectTimeout = time.Duration(config.ConnectTimeoutMs) * time.Millisecond

	// Set authentication if provided
	if config.Username != "" && config.Password != "" {
		cluster.Authenticator = gocql.PasswordAuthenticator{
			Username: config.Username,
			Password: config.Password,
		}
	}

	// Create session
	session, err := cluster.CreateSession()
	if err != nil {
		return fmt.Errorf("failed to create cassandra session: %w", err)
	}

	c.session = session

	// Create keyspace if it doesn't exist
	if err := c.createKeyspace(config); err != nil {
		c.session.Close()
		return fmt.Errorf("failed to create keyspace: %w", err)
	}

	// Create table if it doesn't exist
	if err := c.createTable(); err != nil {
		c.session.Close()
		return fmt.Errorf("failed to create table: %w", err)
	}

	return nil
}

// LogRequest logs a request message
func (c *CassandraLogger) LogRequest(requestID, topic string, payload interface{}, encrypted bool) error {
	if c.session == nil {
		return fmt.Errorf("cassandra session not initialized")
	}

	entry := &types.LogEntry{
		RequestID: requestID,
		Timestamp: time.Now(),
		Type:      "request",
		Topic:     topic,
		Payload:   payload,
		Success:   &[]bool{true}[0],
		Encrypted: encrypted,
	}

	return c.insertLogEntry(entry)
}

// LogResponse logs a response message
func (c *CassandraLogger) LogResponse(requestID, topic string, payload interface{}, success bool, errorMsg string, duration time.Duration, encrypted bool) error {
	if c.session == nil {
		return fmt.Errorf("cassandra session not initialized")
	}

	entry := &types.LogEntry{
		RequestID:  requestID,
		Timestamp:  time.Now(),
		Type:       "response",
		Topic:      topic,
		Payload:    payload,
		Success:    &success,
		Error:      errorMsg,
		DurationMs: &[]int64{int64(duration.Milliseconds())}[0],
		Encrypted:  encrypted,
	}

	return c.insertLogEntry(entry)
}

// GetLogsByRequestID retrieves all log entries for a specific request ID
func (c *CassandraLogger) GetLogsByRequestID(requestID string) ([]*types.LogEntry, error) {
	if c.session == nil {
		return nil, fmt.Errorf("cassandra session not initialized")
	}

	query := fmt.Sprintf("SELECT request_id, timestamp, type, topic, payload, success, error, duration_ms, encrypted FROM %s.%s WHERE request_id = ?",
		c.keyspace, c.table)

	iter := c.session.Query(query, requestID).Iter()
	defer iter.Close()

	var entries []*types.LogEntry
	var entry types.LogEntry
	var payloadStr string

	for iter.Scan(&entry.RequestID, &entry.Timestamp, &entry.Type, &entry.Topic, &payloadStr, &entry.Success, &entry.Error, &entry.DurationMs, &entry.Encrypted) {
		// Parse payload string back to interface{}
		if payloadStr != "" {
			entry.Payload = payloadStr
		}

		entries = append(entries, &types.LogEntry{
			RequestID:  entry.RequestID,
			Timestamp:  entry.Timestamp,
			Type:       entry.Type,
			Topic:      entry.Topic,
			Payload:    entry.Payload,
			Success:    entry.Success,
			Error:      entry.Error,
			DurationMs: entry.DurationMs,
			Encrypted:  entry.Encrypted,
		})
	}

	if err := iter.Close(); err != nil {
		return nil, fmt.Errorf("failed to iterate over results: %w", err)
	}

	return entries, nil
}

// GetLogsByTimeRange retrieves log entries within a time range
func (c *CassandraLogger) GetLogsByTimeRange(startTime, endTime time.Time) ([]*types.LogEntry, error) {
	if c.session == nil {
		return nil, fmt.Errorf("cassandra session not initialized")
	}

	query := fmt.Sprintf("SELECT request_id, timestamp, type, topic, payload, success, error, duration_ms, encrypted FROM %s.%s WHERE timestamp >= ? AND timestamp <= ? ALLOW FILTERING",
		c.keyspace, c.table)

	iter := c.session.Query(query, startTime, endTime).Iter()
	defer iter.Close()

	var entries []*types.LogEntry
	var entry types.LogEntry
	var payloadStr string

	for iter.Scan(&entry.RequestID, &entry.Timestamp, &entry.Type, &entry.Topic, &payloadStr, &entry.Success, &entry.Error, &entry.DurationMs, &entry.Encrypted) {
		// Parse payload string back to interface{}
		if payloadStr != "" {
			entry.Payload = payloadStr
		}

		entries = append(entries, &types.LogEntry{
			RequestID:  entry.RequestID,
			Timestamp:  entry.Timestamp,
			Type:       entry.Type,
			Topic:      entry.Topic,
			Payload:    entry.Payload,
			Success:    entry.Success,
			Error:      entry.Error,
			DurationMs: entry.DurationMs,
			Encrypted:  entry.Encrypted,
		})
	}

	if err := iter.Close(); err != nil {
		return nil, fmt.Errorf("failed to iterate over results: %w", err)
	}

	return entries, nil
}

// Close closes the Cassandra session
func (c *CassandraLogger) Close() error {
	if c.session != nil {
		c.session.Close()
		c.session = nil
	}
	return nil
}

// insertLogEntry inserts a log entry into Cassandra
func (c *CassandraLogger) insertLogEntry(entry *types.LogEntry) error {
	// Convert payload to string for storage
	var payloadStr string
	if entry.Payload != nil {
		payloadStr = fmt.Sprintf("%v", entry.Payload)
	}

	query := fmt.Sprintf(`INSERT INTO %s.%s 
		(request_id, timestamp, type, topic, payload, success, error, duration_ms, encrypted) 
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
		c.keyspace, c.table)

	queryObj := c.session.Query(query,
		entry.RequestID,
		entry.Timestamp,
		entry.Type,
		entry.Topic,
		payloadStr,
		entry.Success,
		entry.Error,
		entry.DurationMs,
		entry.Encrypted,
	)
	
	// Handle nil query (for testing scenarios)
	if queryObj == nil {
		return fmt.Errorf("mock session error")
	}
	
	if err := queryObj.Exec(); err != nil {
		return fmt.Errorf("failed to insert log entry: %w", err)
	}

	log.Printf("Logged %s for request %s to topic %s", entry.Type, entry.RequestID, entry.Topic)
	return nil
}

// createKeyspace creates the keyspace if it doesn't exist
func (c *CassandraLogger) createKeyspace(config *types.CassandraConfig) error {
	// Connect without keyspace to create it
	cluster := gocql.NewCluster(config.Hosts...)
	cluster.Consistency = gocql.Quorum
	cluster.Timeout = time.Duration(config.TimeoutMs) * time.Millisecond

	if config.Username != "" && config.Password != "" {
		cluster.Authenticator = gocql.PasswordAuthenticator{
			Username: config.Username,
			Password: config.Password,
		}
	}

	session, err := cluster.CreateSession()
	if err != nil {
		return fmt.Errorf("failed to create session for keyspace creation: %w", err)
	}
	defer session.Close()

	// Create keyspace with simple replication strategy
	createKeyspaceQuery := fmt.Sprintf(`
		CREATE KEYSPACE IF NOT EXISTS %s 
		WITH REPLICATION = {
			'class': 'SimpleStrategy',
			'replication_factor': 1
		}`, config.Keyspace)

	if err := session.Query(createKeyspaceQuery).Exec(); err != nil {
		return fmt.Errorf("failed to create keyspace %s: %w", config.Keyspace, err)
	}

	log.Printf("Keyspace %s created or already exists", config.Keyspace)
	return nil
}

// createTable creates the log table if it doesn't exist
func (c *CassandraLogger) createTable() error {
	createTableQuery := fmt.Sprintf(`
		CREATE TABLE IF NOT EXISTS %s.%s (
			request_id text,
			timestamp timestamp,
			type text,
			topic text,
			payload text,
			success boolean,
			error text,
			duration_ms bigint,
			encrypted boolean,
			PRIMARY KEY (request_id, timestamp)
		) WITH CLUSTERING ORDER BY (timestamp DESC)`,
		c.keyspace, c.table)

	if err := c.session.Query(createTableQuery).Exec(); err != nil {
		return fmt.Errorf("failed to create table %s.%s: %w", c.keyspace, c.table, err)
	}

	log.Printf("Table %s.%s created or already exists", c.keyspace, c.table)
	return nil
}

// validateConfig validates the Cassandra configuration
func validateConfig(config *types.CassandraConfig) error {
	if len(config.Hosts) == 0 {
		return fmt.Errorf("hosts cannot be empty")
	}

	for i, host := range config.Hosts {
		if host == "" {
			return fmt.Errorf("host at index %d cannot be empty", i)
		}
	}

	if config.Keyspace == "" {
		return fmt.Errorf("keyspace cannot be empty")
	}

	if config.Table == "" {
		return fmt.Errorf("table cannot be empty")
	}

	if config.TimeoutMs <= 0 {
		return fmt.Errorf("timeout must be positive")
	}

	if config.ConnectTimeoutMs <= 0 {
		return fmt.Errorf("connect timeout must be positive")
	}

	return nil
}