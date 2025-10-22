package kafka

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"strings"
	"sync"
	"time"

	"github.com/IBM/sarama"
	"github.com/perlinsos/splp-go/pkg/types"
)

// KafkaWrapper implements the KafkaClient interface
type KafkaWrapper struct {
	config         *types.KafkaConfig
	producer       sarama.SyncProducer
	consumerGroup  sarama.ConsumerGroup
	messageHandler types.MessageProcessor
	mu             sync.RWMutex
	ctx            context.Context
	cancel         context.CancelFunc
	consuming      bool
}

// NewKafkaWrapper creates a new Kafka wrapper instance
func NewKafkaWrapper(config *types.KafkaConfig) (*KafkaWrapper, error) {
	if config == nil {
		return nil, fmt.Errorf("kafka config cannot be nil")
	}

	if err := validateConfig(config); err != nil {
		return nil, fmt.Errorf("invalid kafka config: %w", err)
	}

	ctx, cancel := context.WithCancel(context.Background())

	wrapper := &KafkaWrapper{
		config: config,
		ctx:    ctx,
		cancel: cancel,
	}

	return wrapper, nil
}

// Initialize sets up the Kafka producer and consumer
func (k *KafkaWrapper) Initialize() error {
	k.mu.Lock()
	defer k.mu.Unlock()

	// Create Sarama config
	saramaConfig := sarama.NewConfig()
	saramaConfig.Version = sarama.V2_6_0_0
	
	// Producer config
	saramaConfig.Producer.RequiredAcks = sarama.WaitForAll
	saramaConfig.Producer.Retry.Max = 5
	saramaConfig.Producer.Return.Successes = true
	saramaConfig.Producer.Partitioner = sarama.NewRandomPartitioner
	saramaConfig.Producer.Timeout = time.Duration(k.config.RequestTimeoutMs) * time.Millisecond

	// Consumer config
	saramaConfig.Consumer.Group.Rebalance.Strategy = sarama.BalanceStrategyRoundRobin
	saramaConfig.Consumer.Offsets.Initial = sarama.OffsetNewest
	saramaConfig.Consumer.Group.Session.Timeout = 10 * time.Second
	saramaConfig.Consumer.Group.Heartbeat.Interval = 3 * time.Second

	// Create producer
	producer, err := sarama.NewSyncProducer(k.config.Brokers, saramaConfig)
	if err != nil {
		return fmt.Errorf("failed to create producer: %w", err)
	}
	k.producer = producer

	// Create consumer group
	consumerGroup, err := sarama.NewConsumerGroup(k.config.Brokers, k.config.GroupID, saramaConfig)
	if err != nil {
		k.producer.Close()
		return fmt.Errorf("failed to create consumer group: %w", err)
	}
	k.consumerGroup = consumerGroup

	return nil
}

// SetMessageHandler sets the message processor for handling incoming messages
func (k *KafkaWrapper) SetMessageHandler(handler types.MessageProcessor) {
	k.mu.Lock()
	defer k.mu.Unlock()
	k.messageHandler = handler
}

// StartConsuming starts consuming messages from the specified topics
func (k *KafkaWrapper) StartConsuming(topics []string) error {
	k.mu.Lock()
	defer k.mu.Unlock()

	if k.consuming {
		return fmt.Errorf("already consuming")
	}

	if k.consumerGroup == nil {
		return fmt.Errorf("consumer group not initialized")
	}

	if k.messageHandler == nil {
		return fmt.Errorf("message handler not set")
	}

	if len(topics) == 0 {
		return fmt.Errorf("no topics specified")
	}

	k.consuming = true

	// Start consuming in a goroutine
	go func() {
		defer func() {
			k.mu.Lock()
			k.consuming = false
			k.mu.Unlock()
		}()

		consumer := &consumerGroupHandler{
			wrapper: k,
		}

		for {
			select {
			case <-k.ctx.Done():
				return
			default:
				if err := k.consumerGroup.Consume(k.ctx, topics, consumer); err != nil {
					log.Printf("Error consuming messages: %v", err)
					time.Sleep(time.Second) // Brief pause before retrying
				}
			}
		}
	}()

	return nil
}

// SendMessage sends a message to the specified topic
// This matches the TypeScript sendMessage function signature
func (k *KafkaWrapper) SendMessage(topic string, message string) error {
	k.mu.RLock()
	producer := k.producer
	k.mu.RUnlock()

	if producer == nil {
		return fmt.Errorf("producer not initialized")
	}

	// Create Kafka message (message is already a JSON string)
	kafkaMessage := &sarama.ProducerMessage{
		Topic: topic,
		Value: sarama.StringEncoder(message),
	}

	// Send message
	partition, offset, err := producer.SendMessage(kafkaMessage)
	if err != nil {
		return fmt.Errorf("failed to send message: %w", err)
	}

	log.Printf("Message sent to topic %s, partition %d, offset %d", topic, partition, offset)
	return nil
}

// SendMessageJSON sends a message to the specified topic after marshaling to JSON
// This is a convenience method for backward compatibility
func (k *KafkaWrapper) SendMessageJSON(topic string, message interface{}) error {
	// Marshal message to JSON
	messageBytes, err := json.Marshal(message)
	if err != nil {
		return fmt.Errorf("failed to marshal message: %w", err)
	}

	return k.SendMessage(topic, string(messageBytes))
}

// Close closes the Kafka wrapper and releases resources
func (k *KafkaWrapper) Close() error {
	k.mu.Lock()
	defer k.mu.Unlock()

	// Cancel context to stop consuming
	if k.cancel != nil {
		k.cancel()
	}

	var errors []string

	// Close consumer group
	if k.consumerGroup != nil {
		if err := k.consumerGroup.Close(); err != nil {
			errors = append(errors, fmt.Sprintf("failed to close consumer group: %v", err))
		}
		k.consumerGroup = nil
	}

	// Close producer
	if k.producer != nil {
		if err := k.producer.Close(); err != nil {
			errors = append(errors, fmt.Sprintf("failed to close producer: %v", err))
		}
		k.producer = nil
	}

	if len(errors) > 0 {
		return fmt.Errorf("errors closing kafka wrapper: %s", strings.Join(errors, "; "))
	}

	return nil
}

// IsConsuming returns whether the wrapper is currently consuming messages
func (k *KafkaWrapper) IsConsuming() bool {
	k.mu.RLock()
	defer k.mu.RUnlock()
	return k.consuming
}

// consumerGroupHandler implements sarama.ConsumerGroupHandler
type consumerGroupHandler struct {
	wrapper *KafkaWrapper
}

// Setup is run at the beginning of a new session, before ConsumeClaim
func (h *consumerGroupHandler) Setup(sarama.ConsumerGroupSession) error {
	return nil
}

// Cleanup is run at the end of a session, once all ConsumeClaim goroutines have exited
func (h *consumerGroupHandler) Cleanup(sarama.ConsumerGroupSession) error {
	return nil
}

// ConsumeClaim must start a consumer loop of ConsumerGroupClaim's Messages()
func (h *consumerGroupHandler) ConsumeClaim(session sarama.ConsumerGroupSession, claim sarama.ConsumerGroupClaim) error {
	for {
		select {
		case message := <-claim.Messages():
			if message == nil {
				return nil
			}

			// Process the message
			if err := h.processMessage(message); err != nil {
				log.Printf("Error processing message: %v", err)
			}

			// Mark message as processed
			session.MarkMessage(message, "")

		case <-session.Context().Done():
			return nil
		}
	}
}

// processMessage processes an incoming Kafka message
func (h *consumerGroupHandler) processMessage(message *sarama.ConsumerMessage) error {
	h.wrapper.mu.RLock()
	handler := h.wrapper.messageHandler
	h.wrapper.mu.RUnlock()

	if handler == nil {
		return fmt.Errorf("no message handler set")
	}

	// Process message
	return handler.ProcessMessage(message.Value)
}

// validateConfig validates the Kafka configuration
func validateConfig(config *types.KafkaConfig) error {
	if len(config.Brokers) == 0 {
		return fmt.Errorf("brokers cannot be empty")
	}

	for i, broker := range config.Brokers {
		if broker == "" {
			return fmt.Errorf("broker at index %d cannot be empty", i)
		}
	}

	if config.GroupID == "" {
		return fmt.Errorf("group ID cannot be empty")
	}

	if config.RequestTimeoutMs <= 0 {
		return fmt.Errorf("request timeout must be positive")
	}

	return nil
}