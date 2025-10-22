package crypto

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"

	"github.com/perlinsos/splp-go/pkg/types"
)

const (
	// AES-256-GCM constants
	algorithm = "aes-256-gcm"
	keySize   = 32 // 256 bits
	ivLength  = 16 // 128 bits (16 bytes) - matching TypeScript
	tagLength = 16 // 128 bits
)

// Encryptor implements the encryption interface using AES-256-GCM
type Encryptor struct {
	key []byte
}

// NewEncryptor creates a new encryptor with the provided hex key
func NewEncryptor(hexKey string) (*Encryptor, error) {
	key, err := hex.DecodeString(hexKey)
	if err != nil {
		return nil, fmt.Errorf("invalid hex key: %w", err)
	}

	if len(key) != keySize {
		return nil, fmt.Errorf("encryption key must be %d bytes (64 hex characters) for AES-256", keySize)
	}

	return &Encryptor{key: key}, nil
}

// EncryptPayload encrypts a JSON payload using AES-256-GCM
// This matches the TypeScript encryptPayload function exactly
func (e *Encryptor) EncryptPayload(payload interface{}, requestID string) (*types.EncryptedMessage, error) {
	// Convert payload to JSON string
	payloadBytes, err := json.Marshal(payload)
	if err != nil {
		return nil, fmt.Errorf("failed to marshal payload: %w", err)
	}

	// Create AES cipher
	block, err := aes.NewCipher(e.key)
	if err != nil {
		return nil, fmt.Errorf("failed to create AES cipher: %w", err)
	}

	// Create GCM mode with custom nonce size for 16-byte IV
	gcm, err := cipher.NewGCMWithNonceSize(block, ivLength)
	if err != nil {
		return nil, fmt.Errorf("failed to create GCM: %w", err)
	}

	// Generate random IV (16 bytes to match TypeScript)
	iv := make([]byte, ivLength)
	if _, err := io.ReadFull(rand.Reader, iv); err != nil {
		return nil, fmt.Errorf("failed to generate IV: %w", err)
	}

	// Encrypt the data - in Go, gcm.Seal appends the tag to the ciphertext
	// But TypeScript keeps them separate, so we need to split them
	sealedData := gcm.Seal(nil, iv, payloadBytes, nil)

	// The last 16 bytes are the authentication tag
	if len(sealedData) < tagLength {
		return nil, fmt.Errorf("sealed data too short")
	}

	encryptedData := sealedData[:len(sealedData)-tagLength]
	tag := sealedData[len(sealedData)-tagLength:]

	return &types.EncryptedMessage{
		RequestID: requestID, // Not encrypted for tracing
		Data:      hex.EncodeToString(encryptedData),
		IV:        hex.EncodeToString(iv),
		Tag:       hex.EncodeToString(tag),
	}, nil
}

// DecryptPayload decrypts an encrypted message using AES-256-GCM
// This matches the TypeScript decryptPayload function exactly
func (e *Encryptor) DecryptPayload(encryptedMessage *types.EncryptedMessage) (string, interface{}, error) {
	// Convert IV and tag from hex
	iv, err := hex.DecodeString(encryptedMessage.IV)
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode IV: %w", err)
	}

	tag, err := hex.DecodeString(encryptedMessage.Tag)
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode tag: %w", err)
	}

	// Convert encrypted data from hex
	encryptedData, err := hex.DecodeString(encryptedMessage.Data)
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode encrypted data: %w", err)
	}

	// Create AES cipher
	block, err := aes.NewCipher(e.key)
	if err != nil {
		return "", nil, fmt.Errorf("failed to create AES cipher: %w", err)
	}

	// Create GCM mode with custom nonce size for 16-byte IV
	gcm, err := cipher.NewGCMWithNonceSize(block, ivLength)
	if err != nil {
		return "", nil, fmt.Errorf("failed to create GCM: %w", err)
	}

	// Combine encrypted data and tag for decryption (gcm.Open expects this format)
	sealedData := append(encryptedData, tag...)

	// Decrypt the data
	plaintext, err := gcm.Open(nil, iv, sealedData, nil)
	if err != nil {
		return "", nil, fmt.Errorf("failed to decrypt: %w", err)
	}

	// Parse JSON payload
	var payload interface{}
	if err := json.Unmarshal(plaintext, &payload); err != nil {
		return "", nil, fmt.Errorf("failed to unmarshal decrypted payload: %w", err)
	}

	return encryptedMessage.RequestID, payload, nil
}

// Legacy methods for backward compatibility
// Encrypt is an alias for EncryptPayload
func (e *Encryptor) Encrypt(payload interface{}, requestID string) (*types.EncryptedMessage, error) {
	return e.EncryptPayload(payload, requestID)
}

// Decrypt is an alias for DecryptPayload
func (e *Encryptor) Decrypt(encryptedMessage *types.EncryptedMessage) (string, interface{}, error) {
	return e.DecryptPayload(encryptedMessage)
}

// GenerateEncryptionKey generates a random encryption key for AES-256
// This matches the TypeScript generateEncryptionKey function exactly
func GenerateEncryptionKey() string {
	key := make([]byte, keySize)
	if _, err := io.ReadFull(rand.Reader, key); err != nil {
		panic(fmt.Sprintf("failed to generate encryption key: %v", err))
	}
	return hex.EncodeToString(key)
}

// ValidateKey validates that a hex key is the correct length for AES-256
func ValidateKey(hexKey string) error {
	key, err := hex.DecodeString(hexKey)
	if err != nil {
		return fmt.Errorf("invalid hex key: %w", err)
	}

	if len(key) != keySize {
		return fmt.Errorf("encryption key must be %d bytes (64 hex characters) for AES-256", keySize)
	}

	return nil
}