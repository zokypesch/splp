package crypto

import (
	"testing"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestNewEncryptor(t *testing.T) {
	tests := []struct {
		name    string
		hexKey  string
		wantErr bool
	}{
		{
			name:    "valid 64-char hex key",
			hexKey:  "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
			wantErr: false,
		},
		{
			name:    "invalid hex characters",
			hexKey:  "invalid_hex_key_with_invalid_characters_here_xxxxxxxxxx",
			wantErr: true,
		},
		{
			name:    "too short key",
			hexKey:  "0123456789abcdef",
			wantErr: true,
		},
		{
			name:    "too long key",
			hexKey:  "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
			wantErr: true,
		},
		{
			name:    "empty key",
			hexKey:  "",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			encryptor, err := NewEncryptor(tt.hexKey)
			if tt.wantErr {
				assert.Error(t, err)
				assert.Nil(t, encryptor)
			} else {
				assert.NoError(t, err)
				assert.NotNil(t, encryptor)
				assert.Equal(t, keySize, len(encryptor.key))
			}
		})
	}
}

func TestEncryptDecrypt(t *testing.T) {
	// Generate a valid key
	hexKey, err := GenerateEncryptionKey()
	require.NoError(t, err)

	encryptor, err := NewEncryptor(hexKey)
	require.NoError(t, err)

	tests := []struct {
		name      string
		payload   interface{}
		requestID string
	}{
		{
			name: "simple string payload",
			payload: map[string]interface{}{
				"message": "hello world",
			},
			requestID: "test-request-1",
		},
		{
			name: "complex nested payload",
			payload: map[string]interface{}{
				"user": map[string]interface{}{
					"id":   float64(123), // Use float64 to match JSON unmarshaling
					"name": "John Doe",
					"metadata": map[string]interface{}{
						"roles": []interface{}{"admin", "user"}, // Use []interface{} to match JSON unmarshaling
						"active": true,
					},
				},
				"timestamp": float64(1634567890), // Use float64 to match JSON unmarshaling
			},
			requestID: "test-request-2",
		},
		{
			name: "array payload",
			payload: []interface{}{
				"item1",
				"item2",
				map[string]interface{}{
					"nested": true,
				},
			},
			requestID: "test-request-3",
		},
		{
			name:      "nil payload",
			payload:   nil,
			requestID: "test-request-4",
		},
		{
			name: "empty object",
			payload: map[string]interface{}{},
			requestID: "test-request-5",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Encrypt
			encrypted, err := encryptor.Encrypt(tt.payload, tt.requestID)
			require.NoError(t, err)
			require.NotNil(t, encrypted)

			// Verify encrypted message structure
			assert.Equal(t, tt.requestID, encrypted.RequestID)
			assert.NotEmpty(t, encrypted.EncryptedData)
			assert.NotEmpty(t, encrypted.IV)
			assert.NotEmpty(t, encrypted.AuthTag)

			// Verify hex encoding
			assert.Len(t, encrypted.IV, ivSize*2) // hex encoding doubles length
			assert.Len(t, encrypted.AuthTag, tagSize*2)

			// Decrypt
			decryptedRequestID, decryptedPayload, err := encryptor.Decrypt(encrypted)
			require.NoError(t, err)

			// Verify decrypted data
			assert.Equal(t, tt.requestID, decryptedRequestID)
			assert.Equal(t, tt.payload, decryptedPayload)
		})
	}
}

func TestEncryptDecryptDifferentKeys(t *testing.T) {
	// Generate two different keys
	key1, err := GenerateEncryptionKey()
	require.NoError(t, err)

	key2, err := GenerateEncryptionKey()
	require.NoError(t, err)

	encryptor1, err := NewEncryptor(key1)
	require.NoError(t, err)

	encryptor2, err := NewEncryptor(key2)
	require.NoError(t, err)

	payload := map[string]interface{}{
		"message": "secret data",
	}
	requestID := "test-request"

	// Encrypt with first key
	encrypted, err := encryptor1.Encrypt(payload, requestID)
	require.NoError(t, err)

	// Try to decrypt with second key (should fail)
	_, _, err = encryptor2.Decrypt(encrypted)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "failed to decrypt")
}

func TestDecryptInvalidData(t *testing.T) {
	hexKey, err := GenerateEncryptionKey()
	require.NoError(t, err)

	encryptor, err := NewEncryptor(hexKey)
	require.NoError(t, err)

	tests := []struct {
		name            string
		encryptedMessage *types.EncryptedMessage
		wantErr         bool
		errorContains   string
	}{
		{
			name: "invalid hex data",
			encryptedMessage: &types.EncryptedMessage{
				RequestID:     "test",
				EncryptedData: []byte("invalid_hex"),
				IV:            []byte("0123456789abcdef01234567"), // 24 hex chars = 12 bytes for GCM
				AuthTag:       []byte("0123456789abcdef0123456789abcdef"),
			},
			wantErr:       true,
			errorContains: "failed to decode encrypted data",
		},
		{
			name: "invalid hex IV",
			encryptedMessage: &types.EncryptedMessage{
				RequestID:     "test",
				EncryptedData: []byte("0123456789abcdef"),
				IV:            []byte("invalid_hex"),
				AuthTag:       []byte("0123456789abcdef0123456789abcdef"),
			},
			wantErr:       true,
			errorContains: "failed to decode IV",
		},
		{
			name: "invalid hex tag",
			encryptedMessage: &types.EncryptedMessage{
				RequestID:     "test",
				EncryptedData: []byte("0123456789abcdef"),
				IV:            []byte("0123456789abcdef01234567"), // 24 hex chars = 12 bytes for GCM
				AuthTag:       []byte("invalid_hex"),
			},
			wantErr:       true,
			errorContains: "failed to decode tag",
		},
		{
			name: "corrupted data",
			encryptedMessage: &types.EncryptedMessage{
				RequestID:     "test",
				EncryptedData: []byte("0123456789abcdef0123456789abcdef"),
				IV:            []byte("0123456789abcdef01234567"), // 24 hex chars = 12 bytes for GCM
				AuthTag:       []byte("0123456789abcdef0123456789abcdef"),
			},
			wantErr:       true,
			errorContains: "failed to decrypt",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			_, _, err := encryptor.Decrypt(tt.encryptedMessage)
			if tt.wantErr {
				assert.Error(t, err)
				assert.Contains(t, err.Error(), tt.errorContains)
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestGenerateEncryptionKey(t *testing.T) {
	// Test multiple key generations
	keys := make(map[string]bool)
	
	for i := 0; i < 10; i++ {
		key, err := GenerateEncryptionKey()
		require.NoError(t, err)
		
		// Verify key format
		assert.Len(t, key, keySize*2) // hex encoding doubles length
		
		// Verify key is unique
		assert.False(t, keys[key], "generated duplicate key")
		keys[key] = true
		
		// Verify key can be used to create encryptor
		encryptor, err := NewEncryptor(key)
		assert.NoError(t, err)
		assert.NotNil(t, encryptor)
	}
}

func TestValidateKey(t *testing.T) {
	tests := []struct {
		name    string
		hexKey  string
		wantErr bool
	}{
		{
			name:    "valid key",
			hexKey:  "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
			wantErr: false,
		},
		{
			name:    "invalid hex",
			hexKey:  "invalid_hex_key",
			wantErr: true,
		},
		{
			name:    "too short",
			hexKey:  "0123456789abcdef",
			wantErr: true,
		},
		{
			name:    "too long",
			hexKey:  "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
			wantErr: true,
		},
		{
			name:    "empty",
			hexKey:  "",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := ValidateKey(tt.hexKey)
			if tt.wantErr {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
			}
		})
	}
}

func TestEncryptionConsistency(t *testing.T) {
	hexKey, err := GenerateEncryptionKey()
	require.NoError(t, err)

	encryptor, err := NewEncryptor(hexKey)
	require.NoError(t, err)

	payload := map[string]interface{}{
		"test": "data",
		"number": 42,
	}
	requestID := "consistency-test"

	// Encrypt the same payload multiple times
	encrypted1, err := encryptor.Encrypt(payload, requestID)
	require.NoError(t, err)

	encrypted2, err := encryptor.Encrypt(payload, requestID)
	require.NoError(t, err)

	// Results should be different (due to random IV)
	assert.NotEqual(t, encrypted1.EncryptedData, encrypted2.EncryptedData)
	assert.NotEqual(t, encrypted1.IV, encrypted2.IV)
	assert.NotEqual(t, encrypted1.AuthTag, encrypted2.AuthTag)
	assert.Equal(t, encrypted1.RequestID, encrypted2.RequestID)

	// But both should decrypt to the same payload
	_, decrypted1, err := encryptor.Decrypt(encrypted1)
	require.NoError(t, err)

	_, decrypted2, err := encryptor.Decrypt(encrypted2)
	require.NoError(t, err)

	assert.Equal(t, decrypted1, decrypted2)
	// Note: JSON unmarshaling converts numbers to float64, so we need to compare accordingly
	assert.Equal(t, "data", decrypted1.(map[string]interface{})["test"])
	assert.Equal(t, float64(42), decrypted1.(map[string]interface{})["number"])
}