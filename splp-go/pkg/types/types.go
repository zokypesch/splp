package types

import (
	"time"
)

// KafkaConfig represents Kafka connection configuration
type KafkaConfig struct {
	Brokers          []string `json:"brokers"`
	ClientID         string   `json:"client_id"`
	GroupID          string   `json:"group_id,omitempty"`
	RequestTimeoutMs int      `json:"request_timeout_ms,omitempty"`
}

// CassandraConfig represents Cassandra connection configuration
type CassandraConfig struct {
	ContactPoints    []string `json:"contact_points"`
	LocalDataCenter  string   `json:"local_data_center"`
	Keyspace         string   `json:"keyspace"`
	Username         string   `json:"username,omitempty"`
	Password         string   `json:"password,omitempty"`
	ConnectTimeoutMs int      `json:"connect_timeout_ms,omitempty"`
	TimeoutMs        int      `json:"timeout_ms,omitempty"`
}

// EncryptionConfig represents encryption configuration
type EncryptionConfig struct {
	Key string `json:"key"` // 32-byte hex string for AES-256
}

// MessagingConfig represents the complete messaging configuration
type MessagingConfig struct {
	Kafka      KafkaConfig       `json:"kafka"`
	Cassandra  *CassandraConfig  `json:"cassandra,omitempty"`
	Encryption EncryptionConfig  `json:"encryption"`
}

// EncryptedMessage represents an encrypted message with metadata
type EncryptedMessage struct {
	RequestID     string    `json:"request_id"`
	EncryptedData []byte    `json:"encrypted_data"`
	IV            []byte    `json:"iv"`
	AuthTag       []byte    `json:"auth_tag"`
	Timestamp     time.Time `json:"timestamp"`
}

// RequestMessage represents a request message
type RequestMessage struct {
	RequestID string      `json:"request_id"`
	Topic     string      `json:"topic"`
	Payload   interface{} `json:"payload"`
	Timestamp time.Time   `json:"timestamp"`
}

// ResponseMessage represents a response message
type ResponseMessage struct {
	RequestID string      `json:"request_id"`
	Topic     string      `json:"topic"`
	Payload   interface{} `json:"payload"`
	Success   bool        `json:"success"`
	Error     string      `json:"error,omitempty"`
	Timestamp time.Time   `json:"timestamp"`
}

// LogEntry represents a log entry for Cassandra
type LogEntry struct {
	RequestID string      `json:"request_id"`
	Topic     string      `json:"topic"`
	Payload   interface{} `json:"payload"`
	Encrypted bool        `json:"encrypted"`
	Success   bool        `json:"success"`
	Error     string      `json:"error,omitempty"`
	Duration  int64       `json:"duration_ms"`
	Timestamp time.Time   `json:"timestamp"`
}

// RequestHandler defines the function signature for handling requests
type RequestHandler func(payload interface{}) (interface{}, error)

// HandlerRegistry maps topics to their handlers
type HandlerRegistry map[string]RequestHandler

// MessageProcessor defines the interface for processing messages
type MessageProcessor interface {
	ProcessMessage(message []byte) error
}

// Encryptor defines the interface for encryption operations
type Encryptor interface {
	Encrypt(data interface{}, keyID string) (*EncryptedMessage, error)
	Decrypt(encryptedMsg *EncryptedMessage) (string, interface{}, error)
}

// Logger defines the interface for logging operations
type Logger interface {
	Initialize(config *CassandraConfig) error
	LogRequest(requestID, topic string, payload interface{}, encrypted bool) error
	LogResponse(requestID, topic string, payload interface{}, success bool, errorMsg string, duration time.Duration, encrypted bool) error
	Close() error
}

// KafkaClient defines the interface for Kafka operations
type KafkaClient interface {
	Initialize() error
	SetMessageHandler(handler MessageProcessor)
	StartConsuming(topics []string) error
	SendMessage(topic string, message interface{}) error
	Close() error
	IsConsuming() bool
}

// MessagingClient defines the main interface for the messaging client
type MessagingClient interface {
	Initialize() error
	RegisterHandler(topic string, handler RequestHandler) error
	StartConsuming() error
	Request(topic string, payload interface{}, timeout time.Duration) (*ResponseMessage, error)
	Reply(request *RequestMessage, payload interface{}, err error) error
	Close() error
	IsConsuming() bool
}

// PendingRequest represents a request waiting for a response
type PendingRequest struct {
	ResponseChan chan interface{}
	ErrorChan    chan error
	StartTime    time.Time
}