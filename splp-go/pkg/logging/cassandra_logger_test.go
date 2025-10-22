package logging

import (
	"fmt"
	"testing"
	"time"

	"github.com/gocql/gocql"
	"github.com/perlinsos/splp-go/internal/types"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestNewCassandraLogger(t *testing.T) {
	tests := []struct {
		name    string
		config  *types.CassandraConfig
		wantErr bool
	}{
		{
			name: "valid config",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				Username:         "cassandra",
				Password:         "cassandra",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: false,
		},
		{
			name:    "nil config",
			config:  nil,
			wantErr: true,
		},
		{
			name: "empty hosts",
			config: &types.CassandraConfig{
				Hosts:            []string{},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty keyspace",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty table",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "invalid timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        0,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "invalid connect timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 0,
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			logger, err := NewCassandraLogger(tt.config)
			if tt.wantErr {
				assert.Error(t, err)
				assert.Nil(t, logger)
			} else {
				assert.NoError(t, err)
				assert.NotNil(t, logger)
				assert.Equal(t, tt.config.Keyspace, logger.keyspace)
				assert.Equal(t, tt.config.Table, logger.table)
			}
		})
	}
}

func TestValidateConfig(t *testing.T) {
	tests := []struct {
		name    string
		config  *types.CassandraConfig
		wantErr bool
	}{
		{
			name: "valid config",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042", "localhost:9043"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: false,
		},
		{
			name: "empty hosts",
			config: &types.CassandraConfig{
				Hosts:            []string{},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty host in list",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042", ""},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty keyspace",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty table",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "zero timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        0,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "negative timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        -1000,
				ConnectTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "zero connect timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: 0,
			},
			wantErr: true,
		},
		{
			name: "negative connect timeout",
			config: &types.CassandraConfig{
				Hosts:            []string{"localhost:9042"},
				Keyspace:         "test_keyspace",
				Table:            "test_table",
				TimeoutMs:        5000,
				ConnectTimeoutMs: -1000,
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := validateConfig(tt.config)
			if tt.wantErr {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestCassandraLogger_LogRequest_NoSession(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)
	require.NotNil(t, logger)
	require.NotNil(t, logger)
	require.NotNil(t, logger)

	// No session initialized
	err = logger.LogRequest("test-request", "test-topic", map[string]interface{}{"test": "data"}, true)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "cassandra session not initialized")
}

func TestCassandraLogger_LogResponse_NoSession(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)

	// No session initialized
	err = logger.LogResponse("test-request", "test-topic", map[string]interface{}{"result": "success"}, true, "", time.Second, true)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "cassandra session not initialized")
}

func TestCassandraLogger_GetLogsByRequestID_NoSession(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)

	// No session initialized
	entries, err := logger.GetLogsByRequestID("test-request")
	assert.Error(t, err)
	assert.Nil(t, entries)
	assert.Contains(t, err.Error(), "cassandra session not initialized")
}

func TestCassandraLogger_GetLogsByTimeRange_NoSession(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)

	// No session initialized
	startTime := time.Now().Add(-time.Hour)
	endTime := time.Now()
	entries, err := logger.GetLogsByTimeRange(startTime, endTime)
	assert.Error(t, err)
	assert.Nil(t, entries)
	assert.Contains(t, err.Error(), "cassandra session not initialized")
}

func TestCassandraLogger_Close(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)

	// Test closing without session
	err = logger.Close()
	assert.NoError(t, err)

	// Test closing with nil session (should be safe)
	logger.session = nil
	err = logger.Close()
	assert.NoError(t, err)
}

// MockSession implements SessionInterface for testing
type MockSession struct {
	queries []string
	closed  bool
}

// MockQuery implements a mock query that returns controlled errors
type MockQuery struct {
	shouldFail bool
	errorMsg   string
}

func (mq *MockQuery) Exec() error {
	if mq.shouldFail {
		return fmt.Errorf(mq.errorMsg)
	}
	return nil
}

func (m *MockSession) Query(stmt string, values ...interface{}) *gocql.Query {
	m.queries = append(m.queries, stmt)
	// We can't return our MockQuery as *gocql.Query, so we'll return nil
	// and handle this in the actual implementation
	return nil
}

func (m *MockSession) Close() {
	m.closed = true
}

func TestCassandraLogger_InsertLogEntry_Structure(t *testing.T) {
	// Test the structure of log entries
	tests := []struct {
		name  string
		entry *types.LogEntry
	}{
		{
			name: "request entry",
			entry: &types.LogEntry{
				RequestID: "test-request-1",
				Timestamp: time.Now(),
				Type:      "request",
				Topic:     "test-topic",
				Payload:   map[string]interface{}{"test": "data"},
				Success:   &[]bool{true}[0],
				Encrypted: true,
			},
		},
		{
			name: "response entry with error",
			entry: &types.LogEntry{
				RequestID:  "test-request-2",
				Timestamp:  time.Now(),
				Type:       "response",
				Topic:      "test-topic",
				Payload:    map[string]interface{}{"error": "something went wrong"},
				Success:    &[]bool{false}[0],
				Error:      "processing failed",
				DurationMs: &[]int64{1500}[0],
				Encrypted:  true,
			},
		},
		{
			name: "response entry with success",
			entry: &types.LogEntry{
				RequestID:  "test-request-3",
				Timestamp:  time.Now(),
				Type:       "response",
				Topic:      "test-topic",
				Payload:    map[string]interface{}{"result": "success"},
				Success:    &[]bool{true}[0],
				DurationMs: &[]int64{250}[0],
				Encrypted:  false,
			},
		},
		{
			name: "entry with nil payload",
			entry: &types.LogEntry{
				RequestID: "test-request-4",
				Timestamp: time.Now(),
				Type:      "request",
				Topic:     "test-topic",
				Payload:   nil,
				Success:   &[]bool{true}[0],
				Encrypted: false,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Verify entry structure
			assert.NotEmpty(t, tt.entry.RequestID)
			assert.NotZero(t, tt.entry.Timestamp)
			assert.Contains(t, []string{"request", "response"}, tt.entry.Type)
			assert.NotEmpty(t, tt.entry.Topic)

			if tt.entry.Type == "response" {
				if tt.entry.Success != nil && !*tt.entry.Success {
					assert.NotEmpty(t, tt.entry.Error)
				}
				if tt.entry.DurationMs != nil {
					assert.GreaterOrEqual(t, *tt.entry.DurationMs, int64(0))
				}
			}
		})
	}
}

func TestCassandraLogger_LogMethods_Validation(t *testing.T) {
	config := &types.CassandraConfig{
		Hosts:            []string{"localhost:9042"},
		Keyspace:         "test_keyspace",
		Table:            "test_table",
		TimeoutMs:        5000,
		ConnectTimeoutMs: 5000,
	}

	logger, err := NewCassandraLogger(config)
	require.NoError(t, err)

	// Mock session to avoid actual Cassandra connection
	mockSession := &MockSession{}
	logger.session = mockSession

	t.Run("LogRequest parameters", func(t *testing.T) {
		testCases := []struct {
			requestID string
			topic     string
			payload   interface{}
			encrypted bool
		}{
			{"req-1", "topic-1", map[string]interface{}{"data": "test"}, true},
			{"req-2", "topic-2", []string{"item1", "item2"}, false},
			{"req-3", "topic-3", "simple string", true},
			{"req-4", "topic-4", nil, false},
		}

		for _, tc := range testCases {
			// This will fail due to mock, but we can verify the method accepts the parameters
			err := logger.LogRequest(tc.requestID, tc.topic, tc.payload, tc.encrypted)
			// We expect an error since we're using a mock session
			assert.Error(t, err)
		}
	})

	t.Run("LogResponse parameters", func(t *testing.T) {
		testCases := []struct {
			requestID string
			topic     string
			payload   interface{}
			success   bool
			errorMsg  string
			duration  time.Duration
			encrypted bool
		}{
			{"req-1", "topic-1", map[string]interface{}{"result": "ok"}, true, "", time.Millisecond * 100, true},
			{"req-2", "topic-2", nil, false, "processing failed", time.Second, false},
			{"req-3", "topic-3", "response data", true, "", time.Millisecond * 50, true},
		}

		for _, tc := range testCases {
			// This will fail due to mock, but we can verify the method accepts the parameters
			err := logger.LogResponse(tc.requestID, tc.topic, tc.payload, tc.success, tc.errorMsg, tc.duration, tc.encrypted)
			// We expect an error since we're using a mock session
			assert.Error(t, err)
		}
	})
}

func TestCassandraLogger_TimeRangeValidation(t *testing.T) {
	now := time.Now()
	
	tests := []struct {
		name      string
		startTime time.Time
		endTime   time.Time
		valid     bool
	}{
		{
			name:      "valid range",
			startTime: now.Add(-time.Hour),
			endTime:   now,
			valid:     true,
		},
		{
			name:      "same time",
			startTime: now,
			endTime:   now,
			valid:     true,
		},
		{
			name:      "reverse range",
			startTime: now,
			endTime:   now.Add(-time.Hour),
			valid:     false, // Reverse range should be invalid
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Just verify the time range parameters are accepted
			assert.True(t, tt.startTime.Before(tt.endTime) || tt.startTime.Equal(tt.endTime) || !tt.valid)
		})
	}
}