"""Tests for KafkaPy Tools consumer module."""

import pytest
import json
from unittest.mock import Mock, patch, call

from kafkapy_tools.consumer import KafkaConsumerService
from kafkapy_tools.config import KafkaConfig


class TestKafkaConsumerService:
    """Test cases for KafkaConsumerService class."""
    
    def test_consumer_initialization(self, sample_config, mock_consumer):
        """Test consumer initialization."""
        consumer = KafkaConsumerService(sample_config)
        
        assert consumer.config == sample_config
        assert consumer.topics == [sample_config.consumer_topic]
        assert consumer.consumer == mock_consumer
        assert consumer.key_deserializer is not None
        assert consumer.value_deserializer is not None
        
        # Verify subscribe was called
        mock_consumer.subscribe.assert_called_once_with([sample_config.consumer_topic])
    
    def test_consumer_with_custom_topics(self, sample_config, mock_consumer):
        """Test consumer with custom topics."""
        custom_topics = ["topic1", "topic2", "topic3"]
        consumer = KafkaConsumerService(sample_config, topics=custom_topics)
        
        assert consumer.topics == custom_topics
        mock_consumer.subscribe.assert_called_once_with(custom_topics)
    
    def test_default_key_deserializer(self, sample_config, mock_consumer):
        """Test default key deserializer."""
        consumer = KafkaConsumerService(sample_config)
        
        # Test UTF-8 string
        assert consumer._default_key_deserializer(b"test-key") == "test-key"
        
        # Test None key
        assert consumer._default_key_deserializer(None) is None
        
        # Test bytes that can't be decoded as UTF-8
        binary_data = b'\x00\x01\x02\x03'
        result = consumer._default_key_deserializer(binary_data)
        assert isinstance(result, bytes)
    
    def test_default_value_deserializer(self, sample_config, mock_consumer):
        """Test default value deserializer."""
        consumer = KafkaConsumerService(sample_config)
        
        # Test JSON value
        test_dict = {"key": "value"}
        json_bytes = json.dumps(test_dict).encode('utf-8')
        assert consumer._default_value_deserializer(json_bytes) == test_dict
        
        # Test string value
        assert consumer._default_value_deserializer(b"test-value") == "test-value"
        
        # Test None value
        assert consumer._default_value_deserializer(None) is None
        
        # Test invalid JSON
        invalid_json = b"invalid json"
        result = consumer._default_value_deserializer(invalid_json)
        assert result == "invalid json"
    
    def test_poll_no_message(self, sample_config, mock_consumer):
        """Test poll when no message is available."""
        consumer = KafkaConsumerService(sample_config)
        
        # Mock poll to return None
        mock_consumer.poll.return_value = None
        
        result = consumer.poll()
        
        assert result is None
        mock_consumer.poll.assert_called_once_with(1.0)
    
    def test_poll_with_message(self, sample_config, mock_consumer):
        """Test poll with a valid message."""
        consumer = KafkaConsumerService(sample_config)
        
        # Create mock message
        mock_message = Mock()
        mock_message.error.return_value = None
        mock_message.topic.return_value = "test-topic"
        mock_message.partition.return_value = 0
        mock_message.offset.return_value = 123
        mock_message.timestamp.return_value = (1, 1234567890000)
        mock_message.key.return_value = b"test-key"
        mock_message.value.return_value = b'{"message": "test"}'
        mock_message.headers.return_value = [("header1", b"value1")]
        
        mock_consumer.poll.return_value = mock_message
        
        result = consumer.poll()
        
        assert result is not None
        assert result["topic"] == "test-topic"
        assert result["partition"] == 0
        assert result["offset"] == 123
        assert result["key"] == "test-key"
        assert result["value"] == {"message": "test"}
        assert result["headers"] == {"header1": "value1"}
    
    def test_poll_with_error(self, sample_config, mock_consumer):
        """Test poll with Kafka error."""
        consumer = KafkaConsumerService(sample_config)
        
        # Create mock message with error
        mock_message = Mock()
        mock_error = Mock()
        mock_error.code.return_value = 1  # Not PARTITION_EOF
        mock_message.error.return_value = mock_error
        
        mock_consumer.poll.return_value = mock_message
        
        result = consumer.poll()
        
        assert result is None
    
    def test_poll_with_partition_eof(self, sample_config, mock_consumer):
        """Test poll with partition EOF."""
        consumer = KafkaConsumerService(sample_config)
        
        # Create mock message with PARTITION_EOF
        mock_message = Mock()
        mock_error = Mock()
        mock_error.code.return_value = -191  # PARTITION_EOF
        mock_message.error.return_value = mock_error
        mock_message.topic.return_value = "test-topic"
        mock_message.partition.return_value = 0
        
        mock_consumer.poll.return_value = mock_message
        
        result = consumer.poll()
        
        assert result is None
    
    def test_consume_messages(self, sample_config, mock_consumer):
        """Test continuous message consumption."""
        consumer = KafkaConsumerService(sample_config)
        
        # Mock poll to return a message first, then None
        mock_message = Mock()
        mock_message.error.return_value = None
        mock_message.topic.return_value = "test-topic"
        mock_message.partition.return_value = 0
        mock_message.offset.return_value = 123
        mock_message.timestamp.return_value = (1, 1234567890000)
        mock_message.key.return_value = b"test-key"
        mock_message.value.return_value = b'{"message": "test"}'
        mock_message.headers.return_value = []
        
        mock_consumer.poll.side_effect = [mock_message, None]
        
        messages_received = []
        
        def message_handler(message):
            messages_received.append(message)
        
        # Test with max_messages=1 to avoid infinite loop
        consumer.consume_messages(
            message_handler=message_handler,
            max_messages=1,
            timeout=0.1
        )
        
        assert len(messages_received) == 1
        assert messages_received[0]["topic"] == "test-topic"
    
    def test_consume_batch(self, sample_config, mock_consumer):
        """Test batch message consumption."""
        consumer = KafkaConsumerService(sample_config)
        
        # Mock poll to return 3 messages, then None
        mock_messages = []
        for i in range(3):
            mock_message = Mock()
            mock_message.error.return_value = None
            mock_message.topic.return_value = "test-topic"
            mock_message.partition.return_value = 0
            mock_message.offset.return_value = i
            mock_message.timestamp.return_value = (1, 1234567890000)
            mock_message.key.return_value = f"key-{i}".encode()
            mock_message.value.return_value = f'{{"message": "test-{i}"}}'.encode()
            mock_message.headers.return_value = []
            mock_messages.append(mock_message)
        
        mock_consumer.poll.side_effect = mock_messages + [None]
        
        messages = consumer.consume_batch(batch_size=5, timeout=0.1)
        
        assert len(messages) == 3
        assert messages[0]["offset"] == 0
        assert messages[1]["offset"] == 1
        assert messages[2]["offset"] == 2
    
    def test_commit(self, sample_config, mock_consumer):
        """Test manual commit."""
        consumer = KafkaConsumerService(sample_config)
        
        consumer.commit()
        
        mock_consumer.commit.assert_called_once()
    
    def test_seek_to_beginning(self, sample_config, mock_consumer):
        """Test seek to beginning."""
        consumer = KafkaConsumerService(sample_config)
        
        consumer.seek_to_beginning()
        
        mock_consumer.seek_to_beginning.assert_called_once()
    
    def test_seek_to_end(self, sample_config, mock_consumer):
        """Test seek to end."""
        consumer = KafkaConsumerService(sample_config)
        
        consumer.seek_to_end()
        
        mock_consumer.seek_to_end.assert_called_once()
    
    def test_get_watermark_offsets(self, sample_config, mock_consumer):
        """Test get watermark offsets."""
        consumer = KafkaConsumerService(sample_config)
        
        # Mock partition and watermark offsets
        mock_partition = Mock()
        mock_consumer.get_partition.return_value = mock_partition
        mock_consumer.get_watermark_offsets.return_value = (100, 200)
        
        low, high = consumer.get_watermark_offsets("test-topic", 0)
        
        assert low == 100
        assert high == 200
        mock_consumer.get_partition.assert_called_once_with("test-topic", 0)
        mock_consumer.get_watermark_offsets.assert_called_once_with(mock_partition)
    
    def test_close(self, sample_config, mock_consumer):
        """Test consumer close."""
        consumer = KafkaConsumerService(sample_config)
        
        consumer.close()
        
        mock_consumer.close.assert_called_once()
    
    def test_context_manager(self, sample_config, mock_consumer):
        """Test consumer as context manager."""
        with KafkaConsumerService(sample_config) as consumer:
            assert consumer.config == sample_config
        
        # Verify close was called when exiting context
        mock_consumer.close.assert_called_once()
    
    @patch('kafkapy_tools.consumer.SchemaRegistryClient')
    @patch('kafkapy_tools.consumer.AvroDeserializer')
    def test_avro_deserializer_setup(self, mock_avro_deserializer, mock_schema_registry, sample_config, mock_consumer):
        """Test Avro deserializer setup."""
        schema_registry_url = "http://localhost:8081"
        avro_schema = '{"type": "record", "name": "Test", "fields": [{"name": "field1", "type": "string"}]}'
        
        consumer = KafkaConsumerService(
            sample_config,
            schema_registry_url=schema_registry_url,
            avro_schema=avro_schema
        )
        
        # Verify SchemaRegistryClient was created
        mock_schema_registry.assert_called_once_with({"url": schema_registry_url})
        
        # Verify AvroDeserializer was created
        mock_avro_deserializer.assert_called_once()
    
    def test_custom_deserializers(self, sample_config, mock_consumer):
        """Test custom key and value deserializers."""
        def custom_key_deserializer(key):
            return f"custom-key-{key.decode()}"
        
        def custom_value_deserializer(value):
            return f"custom-value-{value.decode()}"
        
        consumer = KafkaConsumerService(
            sample_config,
            key_deserializer=custom_key_deserializer,
            value_deserializer=custom_value_deserializer
        )
        
        assert consumer.key_deserializer == custom_key_deserializer
        assert consumer.value_deserializer == custom_value_deserializer
