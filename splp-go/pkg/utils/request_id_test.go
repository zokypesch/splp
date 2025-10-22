package utils

import (
	"strings"
	"testing"
	"time"

	"github.com/google/uuid"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestGenerateRequestID(t *testing.T) {
	// Generate multiple IDs to ensure uniqueness
	ids := make(map[string]bool)
	
	for i := 0; i < 100; i++ {
		id := GenerateRequestID()
		
		// Verify it's a valid UUID
		assert.True(t, ValidateRequestID(id), "Generated ID should be valid UUID: %s", id)
		
		// Verify uniqueness
		assert.False(t, ids[id], "Generated duplicate ID: %s", id)
		ids[id] = true
		
		// Verify format (UUID v4 format)
		_, err := uuid.Parse(id)
		assert.NoError(t, err, "Should be parseable as UUID")
	}
}

func TestGenerateRequestIDWithPrefix(t *testing.T) {
	tests := []struct {
		name     string
		prefix   string
		expected string
	}{
		{
			name:     "simple prefix",
			prefix:   "test",
			expected: "test_",
		},
		{
			name:     "prefix with spaces",
			prefix:   "test service",
			expected: "test_service_",
		},
		{
			name:     "uppercase prefix",
			prefix:   "TEST",
			expected: "test_",
		},
		{
			name:     "empty prefix",
			prefix:   "",
			expected: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			id := GenerateRequestIDWithPrefix(tt.prefix)
			
			if tt.prefix == "" {
				// Should be a standard UUID
				assert.True(t, ValidateRequestID(id))
			} else {
				// Should start with expected prefix
				assert.True(t, strings.HasPrefix(id, tt.expected))
				
				// Should be valid prefixed request ID
				assert.True(t, ValidateRequestIDWithPrefix(id, tt.prefix))
				
				// Extract and verify UUID part
				uuidPart, err := ExtractUUIDFromRequestID(id)
				assert.NoError(t, err)
				assert.True(t, ValidateRequestID(uuidPart))
			}
		})
	}
}

func TestGenerateRequestIDWithTimestamp(t *testing.T) {
	beforeTime := time.Now().Unix()
	id := GenerateRequestIDWithTimestamp()
	afterTime := time.Now().Unix()
	
	// Verify it's a timestamped request ID
	assert.True(t, IsTimestampedRequestID(id))
	
	// Extract and verify timestamp
	timestamp, err := ExtractTimestampFromRequestID(id)
	require.NoError(t, err)
	
	// Timestamp should be within reasonable range
	assert.GreaterOrEqual(t, timestamp, beforeTime)
	assert.LessOrEqual(t, timestamp, afterTime)
	
	// Extract and verify UUID part
	uuidPart, err := ExtractUUIDFromRequestID(id)
	assert.NoError(t, err)
	assert.True(t, ValidateRequestID(uuidPart))
}

func TestGenerateShortRequestID(t *testing.T) {
	// Generate multiple short IDs
	ids := make(map[string]bool)
	
	for i := 0; i < 100; i++ {
		id, err := GenerateShortRequestID()
		require.NoError(t, err)
		
		// Verify length (8 bytes = 16 hex characters)
		assert.Len(t, id, 16)
		
		// Verify it's hex
		for _, char := range id {
			assert.True(t, (char >= '0' && char <= '9') || (char >= 'a' && char <= 'f'),
				"Character %c should be hex", char)
		}
		
		// Verify uniqueness
		assert.False(t, ids[id], "Generated duplicate short ID: %s", id)
		ids[id] = true
	}
}

func TestValidateRequestID(t *testing.T) {
	tests := []struct {
		name      string
		requestID string
		valid     bool
	}{
		{
			name:      "valid UUID v4",
			requestID: "550e8400-e29b-41d4-a716-446655440000",
			valid:     true,
		},
		{
			name:      "valid UUID from generator",
			requestID: GenerateRequestID(),
			valid:     true,
		},
		{
			name:      "empty string",
			requestID: "",
			valid:     false,
		},
		{
			name:      "invalid format",
			requestID: "not-a-uuid",
			valid:     false,
		},
		{
			name:      "partial UUID",
			requestID: "550e8400-e29b-41d4",
			valid:     false,
		},
		{
			name:      "UUID with extra characters",
			requestID: "550e8400-e29b-41d4-a716-446655440000-extra",
			valid:     false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := ValidateRequestID(tt.requestID)
			assert.Equal(t, tt.valid, result)
		})
	}
}

func TestValidateRequestIDWithPrefix(t *testing.T) {
	tests := []struct {
		name      string
		requestID string
		valid     bool
	}{
		{
			name:      "standard UUID",
			requestID: GenerateRequestID(),
			valid:     true,
		},
		{
			name:      "prefixed UUID",
			requestID: GenerateRequestIDWithPrefix("test"),
			valid:     true,
		},
		{
			name:      "timestamped UUID",
			requestID: GenerateRequestIDWithTimestamp(),
			valid:     true,
		},
		{
			name:      "empty string",
			requestID: "",
			valid:     false,
		},
		{
			name:      "invalid prefix format",
			requestID: "test-invalid-uuid",
			valid:     false,
		},
		{
			name:      "no UUID part",
			requestID: "test-",
			valid:     false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := ValidateRequestIDWithAnyPrefix(tt.requestID)
			assert.Equal(t, tt.valid, result)
		})
	}
}

func TestExtractUUIDFromRequestID(t *testing.T) {
	tests := []struct {
		name        string
		requestID   string
		expectError bool
		setup       func() string
	}{
		{
			name:        "standard UUID",
			expectError: false,
			setup: func() string {
				return GenerateRequestID()
			},
		},
		{
			name:        "prefixed UUID",
			expectError: false,
			setup: func() string {
				return GenerateRequestIDWithPrefix("test")
			},
		},
		{
			name:        "timestamped UUID",
			expectError: false,
			setup: func() string {
				return GenerateRequestIDWithTimestamp()
			},
		},
		{
			name:        "empty string",
			requestID:   "",
			expectError: true,
		},
		{
			name:        "invalid format",
			requestID:   "invalid-format",
			expectError: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			requestID := tt.requestID
			if tt.setup != nil {
				requestID = tt.setup()
			}

			uuidPart, err := ExtractUUIDFromRequestID(requestID)
			
			if tt.expectError {
				assert.Error(t, err)
				assert.Empty(t, uuidPart)
			} else {
				assert.NoError(t, err)
				assert.True(t, ValidateRequestID(uuidPart))
			}
		})
	}
}

func TestGetRequestIDPrefix(t *testing.T) {
	tests := []struct {
		name           string
		requestID      string
		expectedPrefix string
		expectError    bool
		setup          func() (string, string)
	}{
		{
			name:           "standard UUID (no prefix)",
			expectedPrefix: "",
			expectError:    false,
			setup: func() (string, string) {
				id := GenerateRequestID()
				return id, ""
			},
		},
		{
			name:           "prefixed UUID",
			expectedPrefix: "test",
			expectError:    false,
			setup: func() (string, string) {
				id := GenerateRequestIDWithPrefix("test")
				return id, "test"
			},
		},
		{
			name:        "empty string",
			requestID:   "",
			expectError: true,
		},
		{
			name:        "invalid format",
			requestID:   "invalid",
			expectError: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			requestID := tt.requestID
			expectedPrefix := tt.expectedPrefix
			
			if tt.setup != nil {
				requestID, expectedPrefix = tt.setup()
			}

			prefix, err := GetRequestIDPrefix(requestID)
			
			if tt.expectError {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
				assert.Equal(t, expectedPrefix, prefix)
			}
		})
	}
}

func TestIsTimestampedRequestID(t *testing.T) {
	tests := []struct {
		name      string
		requestID string
		expected  bool
		setup     func() string
	}{
		{
			name:     "timestamped UUID",
			expected: true,
			setup: func() string {
				return GenerateRequestIDWithTimestamp()
			},
		},
		{
			name:     "standard UUID",
			expected: false,
			setup: func() string {
				return GenerateRequestID()
			},
		},
		{
			name:     "prefixed UUID",
			expected: false,
			setup: func() string {
				return GenerateRequestIDWithPrefix("test")
			},
		},
		{
			name:      "empty string",
			requestID: "",
			expected:  false,
		},
		{
			name:      "invalid format",
			requestID: "invalid",
			expected:  false,
		},
		{
			name:      "non-numeric prefix",
			requestID: "abc-550e8400-e29b-41d4-a716-446655440000",
			expected:  false,
		},
		{
			name:      "short timestamp",
			requestID: "123-550e8400-e29b-41d4-a716-446655440000",
			expected:  false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			requestID := tt.requestID
			if tt.setup != nil {
				requestID = tt.setup()
			}

			result := IsTimestampedRequestID(requestID)
			assert.Equal(t, tt.expected, result)
		})
	}
}

func TestExtractTimestampFromRequestID(t *testing.T) {
	t.Run("valid timestamped ID", func(t *testing.T) {
		beforeTime := time.Now().Unix()
		id := GenerateRequestIDWithTimestamp()
		afterTime := time.Now().Unix()

		timestamp, err := ExtractTimestampFromRequestID(id)
		require.NoError(t, err)
		
		assert.GreaterOrEqual(t, timestamp, beforeTime)
		assert.LessOrEqual(t, timestamp, afterTime)
	})

	t.Run("non-timestamped ID", func(t *testing.T) {
		id := GenerateRequestID()
		
		timestamp, err := ExtractTimestampFromRequestID(id)
		assert.Error(t, err)
		assert.Equal(t, int64(0), timestamp)
		assert.Contains(t, err.Error(), "not timestamped")
	})

	t.Run("empty string", func(t *testing.T) {
		timestamp, err := ExtractTimestampFromRequestID("")
		assert.Error(t, err)
		assert.Equal(t, int64(0), timestamp)
	})
}

func TestRequestIDUniqueness(t *testing.T) {
	// Test uniqueness across different generation methods
	ids := make(map[string]bool)
	
	// Generate standard UUIDs
	for i := 0; i < 50; i++ {
		id := GenerateRequestID()
		assert.False(t, ids[id], "Duplicate standard UUID: %s", id)
		ids[id] = true
	}
	
	// Generate prefixed UUIDs
	for i := 0; i < 50; i++ {
		id := GenerateRequestIDWithPrefix("test")
		assert.False(t, ids[id], "Duplicate prefixed UUID: %s", id)
		ids[id] = true
	}
	
	// Generate timestamped UUIDs
	for i := 0; i < 50; i++ {
		id := GenerateRequestIDWithTimestamp()
		assert.False(t, ids[id], "Duplicate timestamped UUID: %s", id)
		ids[id] = true
	}
	
	// Generate short IDs
	for i := 0; i < 50; i++ {
		id, err := GenerateShortRequestID()
		require.NoError(t, err)
		assert.False(t, ids[id], "Duplicate short ID: %s", id)
		ids[id] = true
	}
	
	// Verify we have all unique IDs
	assert.Len(t, ids, 200)
}