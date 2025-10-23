"""Tests for KafkaPy Tools configuration module."""

import pytest
import os
import tempfile
from unittest.mock import patch

from kafkapy_tools.config import KafkaConfig


class TestKafkaConfig:
    """Test cases for KafkaConfig class."""
    
    def test_default_config(self):
        """Test default configuration values."""
        config = KafkaConfig()
        
        assert config.bootstrap_servers == "localhost:9092"
        assert config.security_protocol == "PLAINTEXT"
        assert config.producer_topic == "test-topic"
        assert config.consumer_topic == "test-topic"
        assert config.consumer_group_id == "test-group"
        assert config.producer_acks == "all"
        assert config.producer_retries == 3
    
    def test_custom_config(self):
        """Test custom configuration values."""
        config = KafkaConfig(
            bootstrap_servers="kafka.example.com:9092",
            producer_topic="custom-producer-topic",
            consumer_topic="custom-consumer-topic",
            consumer_group_id="custom-group",
            producer_retries=5,
        )
        
        assert config.bootstrap_servers == "kafka.example.com:9092"
        assert config.producer_topic == "custom-producer-topic"
        assert config.consumer_topic == "custom-consumer-topic"
        assert config.consumer_group_id == "custom-group"
        assert config.producer_retries == 5
    
    def test_from_env_file(self, temp_env_file):
        """Test loading configuration from .env file."""
        try:
            config = KafkaConfig.from_env(temp_env_file)
            
            assert config.bootstrap_servers == "test-server:9092"
            assert config.producer_topic == "test-producer-topic"
            assert config.consumer_topic == "test-consumer-topic"
            assert config.consumer_group_id == "test-consumer-group"
        finally:
            os.unlink(temp_env_file)
    
    @patch.dict(os.environ, {
        'KAFKA_BOOTSTRAP_SERVERS': 'env-server:9092',
        'KAFKA_PRODUCER_TOPIC': 'env-producer-topic',
        'KAFKA_CONSUMER_TOPIC': 'env-consumer-topic',
        'KAFKA_CONSUMER_GROUP_ID': 'env-consumer-group',
    })
    def test_from_env_variables(self):
        """Test loading configuration from environment variables."""
        config = KafkaConfig.from_env()
        
        assert config.bootstrap_servers == "env-server:9092"
        assert config.producer_topic == "env-producer-topic"
        assert config.consumer_topic == "env-consumer-topic"
        assert config.consumer_group_id == "env-consumer-group"
    
    def test_get_producer_config(self, sample_config):
        """Test producer configuration dictionary."""
        config_dict = sample_config.get_producer_config()
        
        assert config_dict["bootstrap.servers"] == "localhost:9092"
        assert config_dict["security.protocol"] == "PLAINTEXT"
        assert config_dict["acks"] == "all"
        assert config_dict["retries"] == 3
        assert config_dict["batch.size"] == 16384
        assert config_dict["linger.ms"] == 5
        assert config_dict["buffer.memory"] == 33554432
    
    def test_get_consumer_config(self, sample_config):
        """Test consumer configuration dictionary."""
        config_dict = sample_config.get_consumer_config()
        
        assert config_dict["bootstrap.servers"] == "localhost:9092"
        assert config_dict["security.protocol"] == "PLAINTEXT"
        assert config_dict["group.id"] == "test-group"
        assert config_dict["auto.offset.reset"] == "earliest"
        assert config_dict["enable.auto.commit"] is True
        assert config_dict["auto.commit.interval.ms"] == 1000
        assert config_dict["session.timeout.ms"] == 30000
        assert config_dict["max.poll.records"] == 500
    
    def test_sasl_config(self):
        """Test SASL configuration."""
        config = KafkaConfig(
            security_protocol="SASL_SSL",
            sasl_mechanism="PLAIN",
            sasl_username="testuser",
            sasl_password="testpass"
        )
        
        producer_config = config.get_producer_config()
        consumer_config = config.get_consumer_config()
        
        assert producer_config["security.protocol"] == "SASL_SSL"
        assert producer_config["sasl.mechanism"] == "PLAIN"
        assert producer_config["sasl.username"] == "testuser"
        assert producer_config["sasl.password"] == "testpass"
        
        assert consumer_config["security.protocol"] == "SASL_SSL"
        assert consumer_config["sasl.mechanism"] == "PLAIN"
        assert consumer_config["sasl.username"] == "testuser"
        assert consumer_config["sasl.password"] == "testpass"
    
    def test_config_validation(self):
        """Test configuration validation."""
        # Test valid configuration
        config = KafkaConfig(
            bootstrap_servers="localhost:9092",
            producer_retries=5,
            producer_batch_size=32768
        )
        assert config.producer_retries == 5
        assert config.producer_batch_size == 32768
