package crypto

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"time"

	"github.com/perlinsos/splp-go/pkg/types"
)

const (
	// AES-256-GCM constants
	keySize = 32 // 256 bits
	ivSize  = 12 // 96 bits (standard for GCM)
	tagSize = 16 // 128 bits
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

// Encrypt encrypts a JSON payload using AES-256-GCM
func (e *Encryptor) Encrypt(payload interface{}, requestID string) (*types.EncryptedMessage, error) {
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

	// Create GCM mode
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return nil, fmt.Errorf("failed to create GCM: %w", err)
	}

	// Generate random IV
	iv := make([]byte, ivSize)
	if _, err := io.ReadFull(rand.Reader, iv); err != nil {
		return nil, fmt.Errorf("failed to generate IV: %w", err)
	}

	// Encrypt the data
	ciphertext := gcm.Seal(nil, iv, payloadBytes, nil)

	// Split ciphertext and tag
	if len(ciphertext) < tagSize {
		return nil, fmt.Errorf("ciphertext too short")
	}

	encryptedData := ciphertext[:len(ciphertext)-tagSize]
	tag := ciphertext[len(ciphertext)-tagSize:]

	return &types.EncryptedMessage{
		RequestID:     requestID, // Not encrypted for tracing
		EncryptedData: []byte(hex.EncodeToString(encryptedData)),
		IV:            []byte(hex.EncodeToString(iv)),
		AuthTag:       []byte(hex.EncodeToString(tag)),
		Timestamp:     time.Now(),
	}, nil
}

// Decrypt decrypts an encrypted message using AES-256-GCM
func (e *Encryptor) Decrypt(encryptedMessage *types.EncryptedMessage) (string, interface{}, error) {
	// Decode hex-encoded fields
	encryptedData, err := hex.DecodeString(string(encryptedMessage.EncryptedData))
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode encrypted data: %w", err)
	}
	
	iv, err := hex.DecodeString(string(encryptedMessage.IV))
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode IV: %w", err)
	}
	
	tag, err := hex.DecodeString(string(encryptedMessage.AuthTag))
	if err != nil {
		return "", nil, fmt.Errorf("failed to decode tag: %w", err)
	}

	// Create AES cipher
	block, err := aes.NewCipher(e.key)
	if err != nil {
		return "", nil, fmt.Errorf("failed to create AES cipher: %w", err)
	}

	// Create GCM mode
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", nil, fmt.Errorf("failed to create GCM: %w", err)
	}

	// Combine encrypted data and tag for decryption
	ciphertext := append(encryptedData, tag...)

	// Decrypt the data
	plaintext, err := gcm.Open(nil, iv, ciphertext, nil)
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

// GenerateKey generates a new 256-bit encryption key
func (e *Encryptor) GenerateKey() (string, error) {
	key := make([]byte, keySize)
	if _, err := io.ReadFull(rand.Reader, key); err != nil {
		return "", fmt.Errorf("failed to generate encryption key: %w", err)
	}
	return hex.EncodeToString(key), nil
}

// GenerateEncryptionKey is a standalone function to generate encryption keys
func GenerateEncryptionKey() (string, error) {
	key := make([]byte, keySize)
	if _, err := io.ReadFull(rand.Reader, key); err != nil {
		return "", fmt.Errorf("failed to generate encryption key: %w", err)
	}
	return hex.EncodeToString(key), nil
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