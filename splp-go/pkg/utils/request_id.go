package utils

import (
	"crypto/rand"
	"fmt"
	"strings"
	"time"

	"github.com/google/uuid"
)

// GenerateRequestID generates a unique request ID using UUID v4
func GenerateRequestID() string {
	return uuid.New().String()
}

// GenerateRequestIDWithPrefix generates a request ID with a custom prefix
func GenerateRequestIDWithPrefix(prefix string) string {
	if prefix == "" {
		return GenerateRequestID()
	}
	
	// Clean and normalize the prefix
	cleanPrefix := strings.ToLower(prefix)
	cleanPrefix = strings.ReplaceAll(cleanPrefix, " ", "_")
	cleanPrefix = strings.ReplaceAll(cleanPrefix, "-", "_")
	
	uuid := uuid.New()
	return fmt.Sprintf("%s_%s", cleanPrefix, uuid.String())
}

// GenerateRequestIDWithTimestamp generates a request ID with timestamp prefix
func GenerateRequestIDWithTimestamp() string {
	timestamp := time.Now().Unix()
	return fmt.Sprintf("%d_%s", timestamp, uuid.New().String())
}

// GenerateShortRequestID generates a shorter request ID using random bytes
func GenerateShortRequestID() (string, error) {
	// Generate 8 random bytes (16 hex characters)
	bytes := make([]byte, 8)
	if _, err := rand.Read(bytes); err != nil {
		return "", fmt.Errorf("failed to generate random bytes: %w", err)
	}
	
	return fmt.Sprintf("%x", bytes), nil
}

// ValidateRequestID validates if a string is a valid UUID format
func ValidateRequestID(requestID string) bool {
	if requestID == "" {
		return false
	}
	
	// Try to parse as UUID
	_, err := uuid.Parse(requestID)
	return err == nil
}

// ValidateRequestIDWithPrefix validates a request ID with a specific prefix
func ValidateRequestIDWithPrefix(requestID, expectedPrefix string) bool {
	if requestID == "" || expectedPrefix == "" {
		return false
	}

	// Clean the expected prefix the same way we do in generation
	cleanPrefix := strings.ToLower(expectedPrefix)
	cleanPrefix = strings.ReplaceAll(cleanPrefix, " ", "_")
	cleanPrefix = strings.ReplaceAll(cleanPrefix, "-", "_")
	
	expectedPrefixPattern := cleanPrefix + "_"
	if !strings.HasPrefix(requestID, expectedPrefixPattern) {
		return false
	}

	// Extract UUID part using the same logic as ExtractUUIDFromRequestID
	lastUnderscoreIndex := strings.LastIndex(requestID, "_")
	if lastUnderscoreIndex == -1 {
		return false
	}
	
	uuidPart := requestID[lastUnderscoreIndex+1:]
	return ValidateRequestID(uuidPart)
}

// ValidateRequestIDWithAnyPrefix validates a request ID that may have any prefix
func ValidateRequestIDWithAnyPrefix(requestID string) bool {
	if requestID == "" {
		return false
	}

	// If it's a standard UUID, validate directly
	if ValidateRequestID(requestID) {
		return true
	}

	// Check if it has a prefix format (prefix_uuid)
	parts := strings.Split(requestID, "_")
	if len(parts) < 2 {
		return false
	}

	// Try to reconstruct the UUID part (everything after the first underscore)
	uuidPart := strings.Join(parts[1:], "_")
	return ValidateRequestID(uuidPart)
}

// ExtractUUIDFromRequestID extracts the UUID part from a prefixed request ID
func ExtractUUIDFromRequestID(requestID string) (string, error) {
	if requestID == "" {
		return "", fmt.Errorf("request ID cannot be empty")
	}
	
	// If it's already a valid UUID, return as is
	if ValidateRequestID(requestID) {
		return requestID, nil
	}
	
	// Find the last underscore to separate prefix from UUID
	lastUnderscoreIndex := strings.LastIndex(requestID, "_")
	if lastUnderscoreIndex == -1 {
		return "", fmt.Errorf("invalid request ID format")
	}
	
	// Extract UUID part (everything after the last underscore)
	uuidPart := requestID[lastUnderscoreIndex+1:]
	if !ValidateRequestID(uuidPart) {
		return "", fmt.Errorf("invalid UUID part in request ID")
	}
	
	return uuidPart, nil
}

// GetRequestIDPrefix extracts the prefix from a prefixed request ID
func GetRequestIDPrefix(requestID string) (string, error) {
	if requestID == "" {
		return "", fmt.Errorf("request ID cannot be empty")
	}
	
	// If it's a standard UUID, no prefix
	if ValidateRequestID(requestID) {
		return "", nil
	}
	
	// Find the last underscore to separate prefix from UUID
	lastUnderscoreIndex := strings.LastIndex(requestID, "_")
	if lastUnderscoreIndex == -1 {
		return "", fmt.Errorf("invalid request ID format")
	}
	
	return requestID[:lastUnderscoreIndex], nil
}

// IsTimestampedRequestID checks if a request ID has a timestamp prefix
func IsTimestampedRequestID(requestID string) bool {
	if requestID == "" {
		return false
	}
	
	parts := strings.Split(requestID, "_")
	if len(parts) < 2 {
		return false
	}
	
	// Check if first part is a valid timestamp (numeric)
	prefix := parts[0]
	if len(prefix) != 10 { // Unix timestamp should be 10 digits
		return false
	}
	
	// Check if all characters are digits
	for _, char := range prefix {
		if char < '0' || char > '9' {
			return false
		}
	}
	
	// Verify the rest is a valid UUID
	uuidPart := strings.Join(parts[1:], "_")
	return ValidateRequestID(uuidPart)
}

// ExtractTimestampFromRequestID extracts timestamp from a timestamped request ID
func ExtractTimestampFromRequestID(requestID string) (int64, error) {
	if !IsTimestampedRequestID(requestID) {
		return 0, fmt.Errorf("request ID is not timestamped")
	}
	
	parts := strings.Split(requestID, "_")
	prefix := parts[0]
	
	var timestamp int64
	if _, err := fmt.Sscanf(prefix, "%d", &timestamp); err != nil {
		return 0, fmt.Errorf("failed to parse timestamp: %w", err)
	}
	
	return timestamp, nil
}