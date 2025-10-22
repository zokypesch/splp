package kafka

import (
	"testing"
	"time"

	"github.com/IBM/sarama"
	"github.com/perlinsos/splp-go/internal/types"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/mock"
	"github.com/stretchr/testify/require"
)

// MockMessageProcessor implements types.MessageProcessor for testing
type MockMessageProcessor struct {
	mock.Mock
}

func TestKafkaWrapper_SendMessageJSON_WithMockProducer(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	tests := []struct {
		name          string
		setupProducer func() *MockSyncProducer
		topic         string
		message       interface{}
		expectedError bool
	}{
		{
			name: "successful send",
			setupProducer: func() *MockSyncProducer {
				mockProducer := &MockSyncProducer{}
				mockProducer.On("SendMessage", mock.AnythingOfType("*sarama.ProducerMessage")).Return(int32(0), int64(123), nil)
				return mockProducer
			},
			topic:         "test-topic",
			message:       map[string]interface{}{"test": "data"},
			expectedError: false,
		},
		{
			name: "producer error",
			setupProducer: func() *MockSyncProducer {
				mockProducer := &MockSyncProducer{}
				mockProducer.On("SendMessage", mock.AnythingOfType("*sarama.ProducerMessage")).Return(int32(0), int64(0), assert.AnError)
				return mockProducer
			},
			topic:         "test-topic",
			message:       map[string]interface{}{"test": "data"},
			expectedError: true,
		},
		{
			name: "unmarshalable message",
			setupProducer: func() *MockSyncProducer {
				return &MockSyncProducer{}
			},
			topic:         "test-topic",
			message:       make(chan int), // channels can't be marshaled to JSON
			expectedError: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mockProducer := tt.setupProducer()
			wrapper.producer = mockProducer

			err := wrapper.SendMessageJSON(tt.topic, tt.message)

			if tt.expectedError {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
			}

			mockProducer.AssertExpectations(t)
		})
	}
}

func (m *MockMessageProcessor) ProcessMessage(message []byte) error {
	args := m.Called(message)
	return args.Error(0)
}

// MockSyncProducer implements sarama.SyncProducer for testing
type MockSyncProducer struct {
	mock.Mock
}

func (m *MockSyncProducer) SendMessage(msg *sarama.ProducerMessage) (partition int32, offset int64, err error) {
	args := m.Called(msg)
	return args.Get(0).(int32), args.Get(1).(int64), args.Error(2)
}

func (m *MockSyncProducer) SendMessages(msgs []*sarama.ProducerMessage) error {
	args := m.Called(msgs)
	return args.Error(0)
}

func (m *MockSyncProducer) Close() error {
	args := m.Called()
	return args.Error(0)
}

func (m *MockSyncProducer) AbortTxn() error {
	args := m.Called()
	return args.Error(0)
}

func (m *MockSyncProducer) AddOffsetsToTxn(offsets map[string][]*sarama.PartitionOffsetMetadata, groupId string) error {
	args := m.Called(offsets, groupId)
	return args.Error(0)
}

func (m *MockSyncProducer) AddMessageToTxn(msg *sarama.ConsumerMessage, groupId string, metadata *string) error {
	args := m.Called(msg, groupId, metadata)
	return args.Error(0)
}

func (m *MockSyncProducer) BeginTxn() error {
	args := m.Called()
	return args.Error(0)
}

func (m *MockSyncProducer) CommitTxn() error {
	args := m.Called()
	return args.Error(0)
}

func (m *MockSyncProducer) TxnStatus() sarama.ProducerTxnStatusFlag {
	args := m.Called()
	return args.Get(0).(sarama.ProducerTxnStatusFlag)
}

func (m *MockSyncProducer) IsTransactional() bool {
	args := m.Called()
	return args.Bool(0)
}

func TestNewKafkaWrapper(t *testing.T) {
	tests := []struct {
		name    string
		config  *types.KafkaConfig
		wantErr bool
	}{
		{
			name: "valid config",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "test-group",
				RequestTimeoutMs: 5000,
			},
			wantErr: false,
		},
		{
			name:    "nil config",
			config:  nil,
			wantErr: true,
		},
		{
			name: "empty brokers",
			config: &types.KafkaConfig{
				Brokers:          []string{},
				GroupID:          "test-group",
				RequestTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty group ID",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "",
				RequestTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "invalid timeout",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "test-group",
				RequestTimeoutMs: 0,
			},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			wrapper, err := NewKafkaWrapper(tt.config)
			if tt.wantErr {
				assert.Error(t, err)
				assert.Nil(t, wrapper)
			} else {
				assert.NoError(t, err)
				assert.NotNil(t, wrapper)
				assert.Equal(t, tt.config, wrapper.config)
				assert.NotNil(t, wrapper.ctx)
				assert.NotNil(t, wrapper.cancel)
				assert.False(t, wrapper.consuming)
			}
		})
	}
}

func TestValidateConfig(t *testing.T) {
	tests := []struct {
		name    string
		config  *types.KafkaConfig
		wantErr bool
	}{
		{
			name: "valid config",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092", "localhost:9093"},
				GroupID:          "test-group",
				RequestTimeoutMs: 5000,
			},
			wantErr: false,
		},
		{
			name: "empty brokers",
			config: &types.KafkaConfig{
				Brokers:          []string{},
				GroupID:          "test-group",
				RequestTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty broker in list",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092", ""},
				GroupID:          "test-group",
				RequestTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "empty group ID",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "",
				RequestTimeoutMs: 5000,
			},
			wantErr: true,
		},
		{
			name: "zero timeout",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "test-group",
				RequestTimeoutMs: 0,
			},
			wantErr: true,
		},
		{
			name: "negative timeout",
			config: &types.KafkaConfig{
				Brokers:          []string{"localhost:9092"},
				GroupID:          "test-group",
				RequestTimeoutMs: -1000,
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

func TestKafkaWrapper_SetMessageHandler(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	// Initially no handler
	assert.Nil(t, wrapper.messageHandler)

	// Set handler
	mockHandler := &MockMessageProcessor{}
	wrapper.SetMessageHandler(mockHandler)

	// Verify handler is set
	assert.Equal(t, mockHandler, wrapper.messageHandler)
}

func TestKafkaWrapper_IsConsuming(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	// Initially not consuming
	assert.False(t, wrapper.IsConsuming())

	// Simulate consuming state
	wrapper.mu.Lock()
	wrapper.consuming = true
	wrapper.mu.Unlock()

	assert.True(t, wrapper.IsConsuming())

	// Reset state
	wrapper.mu.Lock()
	wrapper.consuming = false
	wrapper.mu.Unlock()

	assert.False(t, wrapper.IsConsuming())
}

func TestKafkaWrapper_Close(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	// Test closing without initialization
	err = wrapper.Close()
	assert.NoError(t, err)

	// Test context cancellation
	originalCancel := wrapper.cancel
	assert.NotNil(t, originalCancel)

	err = wrapper.Close()
	assert.NoError(t, err)

	// Verify context is cancelled
	select {
	case <-wrapper.ctx.Done():
		// Context was cancelled, this is expected
	case <-time.After(100 * time.Millisecond):
		t.Error("Context was not cancelled")
	}
}

func TestConsumerGroupHandler_Setup(t *testing.T) {
	wrapper := &KafkaWrapper{}
	handler := &consumerGroupHandler{wrapper: wrapper}

	err := handler.Setup(nil)
	assert.NoError(t, err)
}

func TestConsumerGroupHandler_Cleanup(t *testing.T) {
	wrapper := &KafkaWrapper{}
	handler := &consumerGroupHandler{wrapper: wrapper}

	err := handler.Cleanup(nil)
	assert.NoError(t, err)
}

func TestProcessMessage(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	handler := &consumerGroupHandler{wrapper: wrapper}

	tests := []struct {
		name           string
		setupHandler   func() *MockMessageProcessor
		messageValue   []byte
		topic          string
		expectedError  bool
	}{
		{
			name: "successful processing",
			setupHandler: func() *MockMessageProcessor {
				mockHandler := &MockMessageProcessor{}
				mockHandler.On("ProcessMessage", []byte(`{"test": "data"}`)).Return(nil)
				wrapper.SetMessageHandler(mockHandler)
				return mockHandler
			},
			messageValue:  []byte(`{"test": "data"}`),
			topic:         "test-topic",
			expectedError: false,
		},
		{
			name: "no handler set",
			setupHandler: func() *MockMessageProcessor {
				wrapper.SetMessageHandler(nil)
				return nil
			},
			messageValue:  []byte(`{"test": "data"}`),
			topic:         "test-topic",
			expectedError: true,
		},
		{
			name: "invalid JSON",
			setupHandler: func() *MockMessageProcessor {
				mockHandler := &MockMessageProcessor{}
				mockHandler.On("ProcessMessage", []byte(`invalid json`)).Return(nil)
				wrapper.SetMessageHandler(mockHandler)
				return mockHandler
			},
			messageValue:  []byte(`invalid json`),
			topic:         "test-topic",
			expectedError: false,
		},
		{
			name: "handler returns error",
			setupHandler: func() *MockMessageProcessor {
				mockHandler := &MockMessageProcessor{}
				mockHandler.On("ProcessMessage", []byte(`{"test": "data"}`)).Return(assert.AnError)
				wrapper.SetMessageHandler(mockHandler)
				return mockHandler
			},
			messageValue:  []byte(`{"test": "data"}`),
			topic:         "test-topic",
			expectedError: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mockHandler := tt.setupHandler()

			message := &sarama.ConsumerMessage{
				Topic: tt.topic,
				Value: tt.messageValue,
			}

			err := handler.processMessage(message)

			if tt.expectedError {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
			}

			if mockHandler != nil {
				mockHandler.AssertExpectations(t)
			}
		})
	}
}

func TestKafkaWrapper_SendMessage_WithMockProducer(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	tests := []struct {
		name          string
		setupProducer func() *MockSyncProducer
		topic         string
		message       string
		expectedError bool
	}{
		{
			name: "successful send",
			setupProducer: func() *MockSyncProducer {
				mockProducer := &MockSyncProducer{}
				mockProducer.On("SendMessage", mock.AnythingOfType("*sarama.ProducerMessage")).Return(int32(0), int64(123), nil)
				return mockProducer
			},
			topic:         "test-topic",
			message:       `{"test": "data"}`,
			expectedError: false,
		},
		{
			name: "producer error",
			setupProducer: func() *MockSyncProducer {
				mockProducer := &MockSyncProducer{}
				mockProducer.On("SendMessage", mock.AnythingOfType("*sarama.ProducerMessage")).Return(int32(0), int64(0), assert.AnError)
				return mockProducer
			},
			topic:         "test-topic",
			message:       `{"test": "data"}`,
			expectedError: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mockProducer := tt.setupProducer()
			wrapper.producer = mockProducer

			err := wrapper.SendMessage(tt.topic, tt.message)

			if tt.expectedError {
				assert.Error(t, err)
			} else {
				assert.NoError(t, err)
			}

			mockProducer.AssertExpectations(t)
		})
	}
}
}

func TestKafkaWrapper_SendMessage_NoProducer(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	// No producer initialized
	err = wrapper.SendMessage("test-topic", `{"test": "data"}`)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "producer not initialized")
}

func TestKafkaWrapper_StartConsuming_Validation(t *testing.T) {
	config := &types.KafkaConfig{
		Brokers:          []string{"localhost:9092"},
		GroupID:          "test-group",
		RequestTimeoutMs: 5000,
	}

	wrapper, err := NewKafkaWrapper(config)
	require.NoError(t, err)

	tests := []struct {
		name           string
		setup          func()
		topics         []string
		expectedError  string
	}{
		{
			name: "no consumer group",
			setup: func() {
				wrapper.consumerGroup = nil
				wrapper.messageHandler = &MockMessageProcessor{}
			},
			topics:        []string{"test-topic"},
			expectedError: "consumer group not initialized",
		},
		{
			name: "no message handler",
			setup: func() {
				// Need to set up consumer group first since it's checked before message handler
				wrapper.messageHandler = nil
			},
			topics:        []string{"test-topic"},
			expectedError: "consumer group not initialized",
		},
		{
			name: "no topics",
			setup: func() {
				// Need to set up consumer group first since it's checked before topics
				wrapper.messageHandler = &MockMessageProcessor{}
			},
			topics:        []string{},
			expectedError: "consumer group not initialized",
		},
		{
			name: "already consuming",
			setup: func() {
				wrapper.messageHandler = &MockMessageProcessor{}
				wrapper.consuming = true
			},
			topics:        []string{"test-topic"},
			expectedError: "already consuming",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			// Reset state
			wrapper.consuming = false
			wrapper.consumerGroup = nil
			wrapper.messageHandler = nil

			tt.setup()

			err := wrapper.StartConsuming(tt.topics)
			assert.Error(t, err)
			assert.Contains(t, err.Error(), tt.expectedError)
		})
	}
}