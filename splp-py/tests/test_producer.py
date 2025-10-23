"""Tests for KafkaPy Tools producer module."""

import pytest
import json
from unittest.mock import Mock, patch, call

from kafkapy_tools.producer import KafkaProducerService
from kafkapy_tools.config import KafkaConfig


class TestKafkaProducerService:
    """Test cases for KafkaProducerService class."""
    
    def test_producer_initialization(self, sample_config, mock_producer):
        """Test producer initialization."""
        producer = KafkaProducerService(sample_config)
        
        assert producer.config == sample_config
        assert producer.topic == sample_config.producer_topic
        assert producer.producer == mock_producer
        assert producer.key_serializer is not None
        assert producer.value_serializer is not None
    
    def test_producer_with_custom_topic(self, sample_config, mock_producer):
        """Test producer with custom topic."""
        custom_topic = "custom-topic"
        producer = KafkaProducerService(sample_config, topic=custom_topic)
        
        assert producer.topic == custom_topic
    
    def test_default_key_serializer(self, sample_config, mock_producer):
        """Test default key serializer."""
        producer = KafkaProducerService(sample_config)
        
        # Test string key
        assert producer._default_key_serializer("test-key") == b"test-key"
        
        # Test bytes key
        assert producer._default_key_serializer(b"test-key") == b"test-key"
        
        # Test None key
        assert producer._default_key_serializer(None) is None
        
        # Test other types
        assert producer._default_key_serializer(123) == b"123"
    
    def test_default_value_serializer(self, sample_config, mock_producer):
        """Test default value serializer."""
        producer = KafkaProducerService(sample_config)
        
        # Test string value
        assert producer._default_value_serializer("test-value") == b"test-value"
        
        # Test bytes value
        assert producer._default_value_serializer(b"test-value") == b"test-value"
        
        # Test dict value
        test_dict = {"key": "value"}
        expected = json.dumps(test_dict).encode('utf-8')
        assert producer._default_value_serializer(test_dict) == expected
        
        # Test None value
        assert producer._default_value_serializer(None) is None
    
    def test_send_message(self, sample_config, mock_producer):
        """Test sending a single message."""
        producer = KafkaProducerService(sample_config)
        
        test_message = {"message": "Hello, Kafka!"}
        test_key = "test-key"
        
        producer.send_message(test_message, key=test_key)
        
        # Verify producer.produce was called
        mock_producer.produce.assert_called_once()
        call_args = mock_producer.produce.call_args
        
        assert call_args[1]["topic"] == sample_config.producer_topic
        assert call_args[1]["key"] == b"test-key"
        assert call_args[1]["value"] == json.dumps(test_message).encode('utf-8')
    
    def test_send_message_with_custom_topic(self, sample_config, mock_producer):
        """Test sending message to custom topic."""
        producer = KafkaProducerService(sample_config)
        
        custom_topic = "custom-topic"
        test_message = "test message"
        
        producer.send_message(test_message, topic=custom_topic)
        
        call_args = mock_producer.produce.call_args
        assert call_args[1]["topic"] == custom_topic
    
    def test_send_message_with_headers(self, sample_config, mock_producer):
        """Test sending message with headers."""
        producer = KafkaProducerService(sample_config)
        
        test_message = "test message"
        headers = {"header1": "value1", "header2": "value2"}
        
        producer.send_message(test_message, headers=headers)
        
        call_args = mock_producer.produce.call_args
        assert call_args[1]["headers"] == headers
    
    def test_send_message_with_partition(self, sample_config, mock_producer):
        """Test sending message to specific partition."""
        producer = KafkaProducerService(sample_config)
        
        test_message = "test message"
        partition = 2
        
        producer.send_message(test_message, partition=partition)
        
        call_args = mock_producer.produce.call_args
        assert call_args[1]["partition"] == partition
    
    def test_send_batch(self, sample_config, mock_producer):
        """Test sending batch of messages."""
        producer = KafkaProducerService(sample_config)
        
        messages = [
            ("message1", "key1"),
            ("message2", "key2"),
            ("message3", "key3"),
        ]
        
        producer.send_batch(messages)
        
        # Verify producer.produce was called for each message
        assert mock_producer.produce.call_count == 3
        
        # Verify flush was called
        mock_producer.flush.assert_called_once()
    
    def test_send_batch_with_custom_topic(self, sample_config, mock_producer):
        """Test sending batch to custom topic."""
        producer = KafkaProducerService(sample_config)
        
        custom_topic = "custom-topic"
        messages = [("message1", "key1")]
        
        producer.send_batch(messages, topic=custom_topic)
        
        call_args = mock_producer.produce.call_args
        assert call_args[1]["topic"] == custom_topic
    
    def test_flush(self, sample_config, mock_producer):
        """Test producer flush."""
        producer = KafkaProducerService(sample_config)
        
        # Mock flush to return 0 (all messages delivered)
        mock_producer.flush.return_value = 0
        
        producer.flush()
        
        mock_producer.flush.assert_called_once_with(10.0)
    
    def test_flush_with_remaining_messages(self, sample_config, mock_producer):
        """Test producer flush with remaining messages."""
        producer = KafkaProducerService(sample_config)
        
        # Mock flush to return 5 (5 messages not delivered)
        mock_producer.flush.return_value = 5
        
        producer.flush()
        
        mock_producer.flush.assert_called_once_with(10.0)
    
    def test_close(self, sample_config, mock_producer):
        """Test producer close."""
        producer = KafkaProducerService(sample_config)
        
        producer.close()
        
        # Verify flush was called
        mock_producer.flush.assert_called_once()
        # Verify producer is set to None
        assert producer.producer is None
    
    def test_context_manager(self, sample_config, mock_producer):
        """Test producer as context manager."""
        with KafkaProducerService(sample_config) as producer:
            assert producer.config == sample_config
        
        # Verify flush was called when exiting context
        mock_producer.flush.assert_called_once()
    
    @patch('kafkapy_tools.producer.SchemaRegistryClient')
    @patch('kafkapy_tools.producer.AvroSerializer')
    def test_avro_serializer_setup(self, mock_avro_serializer, mock_schema_registry, sample_config, mock_producer):
        """Test Avro serializer setup."""
        schema_registry_url = "http://localhost:8081"
        avro_schema = '{"type": "record", "name": "Test", "fields": [{"name": "field1", "type": "string"}]}'
        
        producer = KafkaProducerService(
            sample_config,
            schema_registry_url=schema_registry_url,
            avro_schema=avro_schema
        )
        
        # Verify SchemaRegistryClient was created
        mock_schema_registry.assert_called_once_with({"url": schema_registry_url})
        
        # Verify AvroSerializer was created
        mock_avro_serializer.assert_called_once()
    
    def test_custom_serializers(self, sample_config, mock_producer):
        """Test custom key and value serializers."""
        def custom_key_serializer(key):
            return f"custom-key-{key}".encode()
        
        def custom_value_serializer(value):
            return f"custom-value-{value}".encode()
        
        producer = KafkaProducerService(
            sample_config,
            key_serializer=custom_key_serializer,
            value_serializer=custom_value_serializer
        )
        
        assert producer.key_serializer == custom_key_serializer
        assert producer.value_serializer == custom_value_serializer
