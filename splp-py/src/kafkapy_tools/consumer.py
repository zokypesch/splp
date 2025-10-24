"""Kafka Consumer Service implementation."""

import json
import time
from typing import Any, Dict, Optional, Callable, List
from confluent_kafka import Consumer, KafkaError, KafkaException
from confluent_kafka.serialization import SerializationContext, MessageField
from confluent_kafka.schema_registry import SchemaRegistryClient
from confluent_kafka.schema_registry.avro import AvroDeserializer

from .config import KafkaConfig
import logging


class KafkaConsumerService:
    """Kafka Consumer Service with advanced features."""
    
    def __init__(
        self,
        config: KafkaConfig,
        topics: Optional[List[str]] = None,
        key_deserializer: Optional[Callable] = None,
        value_deserializer: Optional[Callable] = None,
        schema_registry_url: Optional[str] = None,
        avro_schema: Optional[str] = None,
    ):
        """
        Initialize Kafka Consumer Service.
        
        Args:
            config: Kafka configuration
            topics: List of topics to subscribe to
            key_deserializer: Custom key deserializer function
            value_deserializer: Custom value deserializer function
            schema_registry_url: Schema registry URL for Avro
            avro_schema: Avro schema string
        """
        self.config = config
        self.topics = topics or [config.consumer_topic]
        self.logger = logging.getLogger(f"{__name__}.consumer")
        
        # Setup deserializers
        self.key_deserializer = key_deserializer or self._default_key_deserializer
        self.value_deserializer = value_deserializer or self._default_value_deserializer
        
        # Setup Avro deserializer if schema registry is provided
        self.avro_deserializer = None
        if schema_registry_url and avro_schema:
            self._setup_avro_deserializer(schema_registry_url, avro_schema)
        
        # Create consumer
        consumer_config = config.get_consumer_config()
        self.consumer = Consumer(consumer_config)
        
        # Subscribe to topics (similar to example-bun pattern)
        try:
            self.consumer.subscribe(self.topics)
            self.logger.info(f"Subscribed to topics: {self.topics}")
        except Exception as e:
            self.logger.error(f"Failed to subscribe to topics {self.topics}: {e}")
            raise
        
        self.logger.info(
            "Kafka Consumer initialized",
            extra={
                "extra_fields": {
                    "topics": self.topics,
                    "group_id": config.consumer_group_id,
                    "bootstrap_servers": config.bootstrap_servers,
                    "has_avro": self.avro_deserializer is not None,
                }
            }
        )
    
    def _setup_avro_deserializer(self, schema_registry_url: str, avro_schema: str) -> None:
        """Setup Avro deserializer."""
        try:
            schema_registry_client = SchemaRegistryClient({
                "url": schema_registry_url
            })
            
            self.avro_deserializer = AvroDeserializer(
                schema_registry_client,
                avro_schema,
                self._default_value_deserializer
            )
            
            self.logger.info("Avro deserializer configured", extra={
                "extra_fields": {"schema_registry_url": schema_registry_url}
            })
        except Exception as e:
            self.logger.error(f"Failed to setup Avro deserializer: {e}")
            raise
    
    def _default_key_deserializer(self, key: bytes) -> Any:
        """Default key deserializer."""
        if key is None:
            return None
        try:
            return key.decode('utf-8')
        except UnicodeDecodeError:
            return key
    
    def _default_value_deserializer(self, value: bytes) -> Any:
        """Default value deserializer."""
        if value is None:
            return None
        try:
            return json.loads(value.decode('utf-8'))
        except (json.JSONDecodeError, UnicodeDecodeError):
            return value.decode('utf-8', errors='ignore')
    
    def poll(self, timeout: float = 1.0) -> Optional[Dict[str, Any]]:
        """
        Poll for messages.
        
        Args:
            timeout: Poll timeout in seconds
        
        Returns:
            Message dictionary or None
        """
        try:
            msg = self.consumer.poll(timeout)
            
            if msg is None:
                return None
            
            if msg.error():
                if msg.error().code() == KafkaError._PARTITION_EOF:
                    self.logger.debug(f"Reached end of partition {msg.topic()} [{msg.partition()}]")
                    return None
                else:
                    self.logger.error(f"Consumer error: {msg.error()}")
                    return None
            
            # Deserialize message
            key = self.key_deserializer(msg.key())
            value = self.value_deserializer(msg.value())
            
            message_data = {
                "topic": msg.topic(),
                "partition": msg.partition(),
                "offset": msg.offset(),
                "timestamp": msg.timestamp(),
                "key": key,
                "value": value,
                "headers": dict(msg.headers()) if msg.headers() else {},
            }
            
            self.logger.debug(
                f"Message received from {msg.topic()} [{msg.partition()}] at offset {msg.offset()}",
                extra={
                    "extra_fields": {
                        "topic": msg.topic(),
                        "partition": msg.partition(),
                        "offset": msg.offset(),
                        "key": str(key) if key else None,
                    }
                }
            )
            
            return message_data
            
        except KafkaException as e:
            self.logger.error(f"Kafka exception: {e}")
            raise
        except Exception as e:
            self.logger.error(f"Unexpected error: {e}")
            raise
    
    def consume_messages(
        self,
        message_handler: Callable[[Dict[str, Any]], None],
        max_messages: Optional[int] = None,
        timeout: float = 1.0,
        auto_commit: bool = True,
    ) -> None:
        """
        Consume messages continuously.
        
        Args:
            message_handler: Function to handle received messages
            max_messages: Maximum number of messages to consume
            timeout: Poll timeout in seconds
            auto_commit: Whether to auto-commit offsets
        """
        message_count = 0
        
        self.logger.info(
            "Starting message consumption",
            extra={
                "extra_fields": {
                    "topics": self.topics,
                    "max_messages": max_messages,
                    "auto_commit": auto_commit,
                }
            }
        )
        
        try:
            while True:
                if max_messages and message_count >= max_messages:
                    self.logger.info(f"Reached maximum message limit: {max_messages}")
                    break
                
                message = self.poll(timeout)
                
                if message is None:
                    continue
                
                try:
                    message_handler(message)
                    message_count += 1
                    
                    if not auto_commit:
                        self.commit()
                    
                except Exception as e:
                    self.logger.error(f"Error handling message: {e}")
                    # Continue processing other messages
                    continue
                
        except KeyboardInterrupt:
            self.logger.info("Consumer interrupted by user")
        except Exception as e:
            self.logger.error(f"Consumer error: {e}")
            raise
        finally:
            self.logger.info(
                f"Consumed {message_count} messages",
                extra={"extra_fields": {"total_messages": message_count}}
            )
    
    def consume_batch(
        self,
        batch_size: int = 100,
        timeout: float = 1.0,
    ) -> List[Dict[str, Any]]:
        """
        Consume a batch of messages.
        
        Args:
            batch_size: Maximum number of messages in batch
            timeout: Poll timeout in seconds
        
        Returns:
            List of message dictionaries
        """
        messages = []
        
        for _ in range(batch_size):
            message = self.poll(timeout)
            if message is None:
                break
            messages.append(message)
        
        if messages:
            self.logger.info(
                f"Consumed batch of {len(messages)} messages",
                extra={"extra_fields": {"batch_size": len(messages)}}
            )
        
        return messages
    
    def commit(self) -> None:
        """Manually commit offsets."""
        try:
            self.consumer.commit()
            self.logger.debug("Offsets committed successfully")
        except Exception as e:
            self.logger.error(f"Failed to commit offsets: {e}")
            raise
    
    def seek_to_beginning(self) -> None:
        """Seek to beginning of all partitions."""
        try:
            self.consumer.seek_to_beginning()
            self.logger.info("Seeked to beginning of all partitions")
        except Exception as e:
            self.logger.error(f"Failed to seek to beginning: {e}")
            raise
    
    def seek_to_end(self) -> None:
        """Seek to end of all partitions."""
        try:
            self.consumer.seek_to_end()
            self.logger.info("Seeked to end of all partitions")
        except Exception as e:
            self.logger.error(f"Failed to seek to end: {e}")
            raise
    
    def get_watermark_offsets(self, topic: str, partition: int) -> tuple[int, int]:
        """
        Get watermark offsets for a partition.
        
        Args:
            topic: Topic name
            partition: Partition number
        
        Returns:
            Tuple of (low, high) offsets
        """
        try:
            low, high = self.consumer.get_watermark_offsets(
                self.consumer.get_partition(topic, partition)
            )
            return low, high
        except Exception as e:
            self.logger.error(f"Failed to get watermark offsets: {e}")
            raise
    
    def close(self) -> None:
        """Close consumer."""
        self.logger.info("Closing Kafka consumer")
        self.consumer.close()
    
    def __enter__(self):
        """Context manager entry."""
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """Context manager exit."""
        self.close()
