package messaging

import (
	"context"
	"encoding/json"
	"fmt"
	"sync"
	"time"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/perlinsos/splp-go/pkg/crypto"
	"github.com/perlinsos/splp-go/pkg/kafka"
	"github.com/perlinsos/splp-go/pkg/logging"
	"github.com/perlinsos/splp-go/pkg/utils"
)

// MessagingClient implements the main messaging functionality
type MessagingClient struct {
	config         *types.MessagingConfig
	kafkaWrapper   types.KafkaClient
	logger         types.Logger
	encryptor      types.Encryptor
	handlers       map[string]types.RequestHandler
	pendingReplies map[string]chan *types.ResponseMessage
	replyMutex     sync.RWMutex
	isInitialized  bool
	ctx            context.Context
	cancel         context.CancelFunc
}

// NewMessagingClient creates a new MessagingClient instance
func NewMessagingClient(config *types.MessagingConfig) (*MessagingClient, error) {
	if err := validateMessagingConfig(config); err != nil {
		return nil, fmt.Errorf("invalid messaging config: %w", err)
	}

	ctx, cancel := context.WithCancel(context.Background())

	client := &MessagingClient{
		config:         config,
		handlers:       make(map[string]types.RequestHandler),
		pendingReplies: make(map[string]chan *types.ResponseMessage),
		ctx:            ctx,
		cancel:         cancel,
	}

	return client, nil
}

// Initialize sets up all components (Kafka, Cassandra, Encryption)
func (c *MessagingClient) Initialize() error {
	if c.isInitialized {
		return fmt.Errorf("client already initialized")
	}

	// Initialize encryptor
	encryptor, err := crypto.NewEncryptor(c.config.Encryption.Key)
	if err != nil {
		return fmt.Errorf("failed to create encryptor: %w", err)
	}
	c.encryptor = encryptor

	// Initialize Kafka wrapper
	kafkaWrapper, err := kafka.NewKafkaWrapper(&c.config.Kafka)
	if err != nil {
		return fmt.Errorf("failed to create kafka wrapper: %w", err)
	}
	c.kafkaWrapper = kafkaWrapper

	if err := c.kafkaWrapper.Initialize(); err != nil {
		return fmt.Errorf("failed to initialize kafka: %w", err)
	}

	// Set message handler for Kafka
	c.kafkaWrapper.SetMessageHandler(c)

	// Initialize logger if Cassandra config is provided
	if c.config.Cassandra != nil {
		logger, err := logging.NewCassandraLogger(c.config.Cassandra)
		if err != nil {
			return fmt.Errorf("failed to create cassandra logger: %w", err)
		}
		c.logger = logger

		if err := c.logger.Initialize(c.config.Cassandra); err != nil {
			return fmt.Errorf("failed to initialize cassandra logger: %w", err)
		}
	}

	c.isInitialized = true
	return nil
}

// RegisterHandler registers a handler for a specific topic
func (c *MessagingClient) RegisterHandler(topic string, handler types.RequestHandler) error {
	if !c.isInitialized {
		return fmt.Errorf("client not initialized")
	}

	c.handlers[topic] = handler
	return nil
}

// StartConsuming starts consuming messages from registered topics
func (c *MessagingClient) StartConsuming() error {
	if !c.isInitialized {
		return fmt.Errorf("client not initialized")
	}

	if len(c.handlers) == 0 {
		return fmt.Errorf("no handlers registered")
	}

	topics := make([]string, 0, len(c.handlers)*2) // Space for both request and reply topics
	for topic := range c.handlers {
		topics = append(topics, topic)
		// Also listen to reply topics for this handler
		replyTopic := fmt.Sprintf("%s-reply", topic)
		topics = append(topics, replyTopic)
	}

	return c.kafkaWrapper.StartConsuming(topics)
}

// Request sends a request and waits for a response
func (c *MessagingClient) Request(topic string, payload interface{}, timeout time.Duration) (*types.ResponseMessage, error) {
	if !c.isInitialized {
		return nil, fmt.Errorf("client not initialized")
	}

	requestID := utils.GenerateRequestID()
	replyTopic := fmt.Sprintf("%s-reply", topic)

	// Create request message
	request := &types.RequestMessage{
		RequestID: requestID,
		Topic:     topic,
		Payload:   payload,
		ReplyTo:   replyTopic,
		Timestamp: time.Now(),
	}

	// Log request if logger is available
	if c.logger != nil {
		if err := c.logger.LogRequest(request.RequestID, request.Topic, request.Payload, false); err != nil {
			// Log error but don't fail the request
			fmt.Printf("Failed to log request: %v\n", err)
		}
	}

	// Encrypt the request
	encryptedMessage, err := c.encryptRequest(request)
	if err != nil {
		return nil, fmt.Errorf("failed to encrypt request: %w", err)
	}

	// Create reply channel
	replyChan := make(chan *types.ResponseMessage, 1)
	c.replyMutex.Lock()
	c.pendingReplies[requestID] = replyChan
	c.replyMutex.Unlock()

	// Clean up reply channel when done
	defer func() {
		c.replyMutex.Lock()
		delete(c.pendingReplies, requestID)
		c.replyMutex.Unlock()
		close(replyChan)
	}()

	// Send the encrypted message
	if err := c.kafkaWrapper.SendMessageJSON(topic, encryptedMessage); err != nil {
		return nil, fmt.Errorf("failed to send message: %w", err)
	}

	// Wait for reply with timeout
	select {
	case response := <-replyChan:
		// Log response if logger is available
		if c.logger != nil {
			errorMsg := ""
			if response.Error != "" {
				errorMsg = response.Error
			}
			if err := c.logger.LogResponse(response.RequestID, "", response.Payload, response.Success, errorMsg, time.Since(time.Now()), false); err != nil {
				// Log error but don't fail the response
				fmt.Printf("Failed to log response: %v\n", err)
			}
		}
		return response, nil
	case <-time.After(timeout):
		return nil, fmt.Errorf("request timeout after %v", timeout)
	case <-c.ctx.Done():
		return nil, fmt.Errorf("client context cancelled")
	}
}

// Reply sends a response to a request
func (c *MessagingClient) Reply(request *types.RequestMessage, payload interface{}, err error) error {
	if !c.isInitialized {
		return fmt.Errorf("client not initialized")
	}

	if request.ReplyTo == "" {
		return fmt.Errorf("no reply topic specified in request")
	}

	// Create response message
	response := &types.ResponseMessage{
		RequestID: request.RequestID,
		Payload:   payload,
		Error:     "",
		Success:   err == nil,
		Timestamp: time.Now(),
	}

	if err != nil {
		response.Error = err.Error()
	}

	// Log response if logger is available
	if c.logger != nil {
		errorMsg := ""
		if response.Error != "" {
			errorMsg = response.Error
		}
		if logErr := c.logger.LogResponse(response.RequestID, request.ReplyTo, response.Payload, response.Success, errorMsg, time.Since(response.Timestamp), false); logErr != nil {
			// Log error but don't fail the response
			fmt.Printf("Failed to log response: %v\n", logErr)
		}
	}

	// Encrypt the response
	encryptedMessage, encErr := c.encryptResponse(response)
	if encErr != nil {
		return fmt.Errorf("failed to encrypt response: %w", encErr)
	}

	// Send the encrypted response
	return c.kafkaWrapper.SendMessageJSON(request.ReplyTo, encryptedMessage)
}

// Close shuts down the messaging client
func (c *MessagingClient) Close() error {
	if !c.isInitialized {
		return nil
	}

	c.cancel()

	var errors []error

	// Close Kafka wrapper
	if c.kafkaWrapper != nil {
		if err := c.kafkaWrapper.Close(); err != nil {
			errors = append(errors, fmt.Errorf("kafka close error: %w", err))
		}
	}

	// Close logger
	if c.logger != nil {
		if err := c.logger.Close(); err != nil {
			errors = append(errors, fmt.Errorf("logger close error: %w", err))
		}
	}

	// Close all pending reply channels
	c.replyMutex.Lock()
	for _, ch := range c.pendingReplies {
		close(ch)
	}
	c.pendingReplies = make(map[string]chan *types.ResponseMessage)
	c.replyMutex.Unlock()

	c.isInitialized = false

	if len(errors) > 0 {
		return fmt.Errorf("errors during close: %v", errors)
	}

	return nil
}

// IsConsuming returns whether the client is currently consuming messages
func (c *MessagingClient) IsConsuming() bool {
	if !c.isInitialized || c.kafkaWrapper == nil {
		return false
	}
	return c.kafkaWrapper.IsConsuming()
}

// processMessage implements the MessageProcessor interface
func (c *MessagingClient) ProcessMessage(message []byte) error {
	return c.processMessage(message)
}

// processMessage handles incoming messages from Kafka (internal method)
func (c *MessagingClient) processMessage(message []byte) error {
	// Decrypt the message
	var encryptedMsg types.EncryptedMessage
	if err := json.Unmarshal(message, &encryptedMsg); err != nil {
		return fmt.Errorf("failed to unmarshal encrypted message: %w", err)
	}

	_, decryptedData, err := c.encryptor.Decrypt(&encryptedMsg)
	if err != nil {
		return fmt.Errorf("failed to decrypt message: %w", err)
	}

	// Convert decryptedData to []byte for unmarshaling
	var dataBytes []byte
	switch v := decryptedData.(type) {
	case []byte:
		dataBytes = v
	case string:
		dataBytes = []byte(v)
	case map[string]interface{}:
		// Handle case where decryption returns parsed JSON directly
		jsonBytes, err := json.Marshal(v)
		if err != nil {
			return fmt.Errorf("failed to marshal decrypted map data: %w", err)
		}
		dataBytes = jsonBytes
	default:
		return fmt.Errorf("unexpected decrypted data type: %T", decryptedData)
	}

	// Try to parse as request first
	var request types.RequestMessage
	if err := json.Unmarshal(dataBytes, &request); err == nil && request.Topic != "" {
		return c.handleRequest(&request)
	}

	// Try to parse as response
	var response types.ResponseMessage
	if err := json.Unmarshal(dataBytes, &response); err == nil && response.RequestID != "" {
		return c.handleResponse(&response)
	}

	return fmt.Errorf("unable to parse message as request or response")
}

// handleRequest processes incoming requests
func (c *MessagingClient) handleRequest(request *types.RequestMessage) error {
	handler, exists := c.handlers[request.Topic]
	if !exists {
		return fmt.Errorf("no handler registered for topic: %s", request.Topic)
	}

	// Log request if logger is available
	if c.logger != nil {
		if err := c.logger.LogRequest(request.RequestID, request.Topic, request.Payload, false); err != nil {
			fmt.Printf("Failed to log request: %v\n", err)
		}
	}

	// Handle the request in a goroutine to avoid blocking
	go func() {
		response, err := handler(request)
		if replyErr := c.Reply(request, response, err); replyErr != nil {
			fmt.Printf("Failed to send reply: %v\n", replyErr)
		}
	}()

	return nil
}

// handleResponse processes incoming responses
func (c *MessagingClient) handleResponse(response *types.ResponseMessage) error {
	c.replyMutex.RLock()
	replyChan, exists := c.pendingReplies[response.RequestID]
	c.replyMutex.RUnlock()

	if !exists {
		return fmt.Errorf("no pending request found for ID: %s", response.RequestID)
	}

	select {
	case replyChan <- response:
		return nil
	default:
		return fmt.Errorf("reply channel full for request ID: %s", response.RequestID)
	}
}

// encryptRequest encrypts a request message
func (c *MessagingClient) encryptRequest(request *types.RequestMessage) (*types.EncryptedMessage, error) {
	encryptedMsg, err := c.encryptor.Encrypt(request, "")
	if err != nil {
		return nil, fmt.Errorf("failed to encrypt request: %w", err)
	}

	return encryptedMsg, nil
}

// encryptResponse encrypts a response message
func (c *MessagingClient) encryptResponse(response *types.ResponseMessage) (*types.EncryptedMessage, error) {
	encryptedMsg, err := c.encryptor.Encrypt(response, "")
	if err != nil {
		return nil, fmt.Errorf("failed to encrypt response: %w", err)
	}

	return encryptedMsg, nil
}

// validateMessagingConfig validates the messaging configuration
func validateMessagingConfig(config *types.MessagingConfig) error {
	if config == nil {
		return fmt.Errorf("config cannot be nil")
	}

	if config.Kafka.Brokers == nil || len(config.Kafka.Brokers) == 0 {
		return fmt.Errorf("kafka brokers cannot be empty")
	}

	if config.Kafka.GroupID == "" {
		return fmt.Errorf("kafka group ID cannot be empty")
	}

	if config.Encryption.Key == "" {
		return fmt.Errorf("encryption key cannot be empty")
	}

	if len(config.Encryption.Key) != 64 { // 32 bytes in hex = 64 characters
		return fmt.Errorf("encryption key must be 64 characters (32 bytes in hex)")
	}

	return nil
}