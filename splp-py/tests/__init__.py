"""Test configuration and fixtures for KafkaPy Tools."""

import pytest
import json
import tempfile
import os
from typing import Dict, Any
from unittest.mock import Mock, patch

from kafkapy_tools.config import KafkaConfig
from kafkapy_tools.producer import KafkaProducerService
from kafkapy_tools.consumer import KafkaConsumerService


@pytest.fixture
def sample_config() -> KafkaConfig:
    """Sample Kafka configuration for testing."""
    return KafkaConfig(
        bootstrap_servers="localhost:9092",
        producer_topic="test-topic",
        consumer_topic="test-topic",
        consumer_group_id="test-group",
    )


@pytest.fixture
def sample_message() -> Dict[str, Any]:
    """Sample message for testing."""
    return {
        "id": "test-123",
        "message": "Hello, Kafka!",
        "timestamp": 1234567890,
        "data": {"key": "value"}
    }


@pytest.fixture
def temp_env_file() -> str:
    """Create temporary .env file for testing."""
    env_content = """
KAFKA_BOOTSTRAP_SERVERS=test-server:9092
KAFKA_PRODUCER_TOPIC=test-producer-topic
KAFKA_CONSUMER_TOPIC=test-consumer-topic
KAFKA_CONSUMER_GROUP_ID=test-consumer-group
LOG_LEVEL=DEBUG
"""
    
    with tempfile.NamedTemporaryFile(mode='w', suffix='.env', delete=False) as f:
        f.write(env_content)
        return f.name


@pytest.fixture
def mock_producer():
    """Mock Kafka producer."""
    with patch('kafkapy_tools.producer.Producer') as mock:
        producer_instance = Mock()
        mock.return_value = producer_instance
        yield producer_instance


@pytest.fixture
def mock_consumer():
    """Mock Kafka consumer."""
    with patch('kafkapy_tools.consumer.Consumer') as mock:
        consumer_instance = Mock()
        mock.return_value = consumer_instance
        yield consumer_instance


@pytest.fixture
def mock_schema_registry():
    """Mock Schema Registry client."""
    with patch('kafkapy_tools.producer.SchemaRegistryClient') as mock:
        registry_instance = Mock()
        mock.return_value = registry_instance
        yield registry_instance
