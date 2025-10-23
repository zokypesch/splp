"""Kafka Producer Service implementation."""

import json
import time
from typing import Any, Dict, Optional, Callable
from confluent_kafka import Producer, KafkaError
from confluent_kafka.serialization import SerializationContext, MessageField
from confluent_kafka.schema_registry import SchemaRegistryClient
from confluent_kafka.schema_registry.avro import AvroSerializer

from .config import KafkaConfig
from .logging_config import get_logger


class KafkaProducerService:
    """Kafka Producer Service with advanced features."""
    
    def __init__(
        self,
        config: KafkaConfig,
        topic: Optional[str] = None,
        key_serializer: Optional[Callable] = None,
        value_serializer: Optional[Callable] = None,
        schema_registry_url: Optional[str] = None,
        avro_schema: Optional[str] = None,
    ):
        """
        Initialize Kafka Producer Service.
        
        Args:
            config: Kafka configuration
            topic: Topic name (overrides config default)
            key_serializer: Custom key serializer function
            value_serializer: Custom value serializer function
            schema_registry_url: Schema registry URL for Avro
            avro_schema: Avro schema string
        """
        self.config = config
        self.topic = topic or config.producer_topic
        self.logger = get_logger(f"{__name__}.producer")
        
        # Setup serializers
        self.key_serializer = key_serializer or self._default_key_serializer
        self.value_serializer = value_serializer or self._default_value_serializer
        
        # Setup Avro serializer if schema registry is provided
        self.avro_serializer = None
        if schema_registry_url and avro_schema:
            self._setup_avro_serializer(schema_registry_url, avro_schema)
        
        # Create producer
        producer_config = config.get_producer_config()
        self.producer = Producer(producer_config)
        
        self.logger.info(
            "Kafka Producer initialized",
            extra={
                "extra_fields": {
                    "topic": self.topic,
                    "bootstrap_servers": config.bootstrap_servers,
                    "has_avro": self.avro_serializer is not None,
                }
            }
        )
    
    def _setup_avro_serializer(self, schema_registry_url: str, avro_schema: str) -> None:
        """Setup Avro serializer."""
        try:
            schema_registry_client = SchemaRegistryClient({
                "url": schema_registry_url
            })
            
            self.avro_serializer = AvroSerializer(
                schema_registry_client,
                avro_schema,
                self._default_value_serializer
            )
            
            self.logger.info("Avro serializer configured", extra={
                "extra_fields": {"schema_registry_url": schema_registry_url}
            })
        except Exception as e:
            self.logger.error(f"Failed to setup Avro serializer: {e}")
            raise
    
    def _default_key_serializer(self, key: Any) -> bytes:
        """Default key serializer."""
        if key is None:
            return None
        if isinstance(key, bytes):
            return key
        if isinstance(key, str):
            return key.encode('utf-8')
        return str(key).encode('utf-8')
    
    def _default_value_serializer(self, value: Any) -> bytes:
        """Default value serializer."""
        if value is None:
            return None
        if isinstance(value, bytes):
            return value
        if isinstance(value, str):
            return value.encode('utf-8')
        return json.dumps(value).encode('utf-8')
    
    def _delivery_callback(self, err: Optional[KafkaError], msg: Any) -> None:
        """Delivery callback for message confirmation."""
        if err is not None:
            self.logger.error(f"Message delivery failed: {err}")
        else:
            self.logger.debug(
                f"Message delivered to {msg.topic()} [{msg.partition()}] at offset {msg.offset()}",
                extra={
                    "extra_fields": {
                        "topic": msg.topic(),
                        "partition": msg.partition(),
                        "offset": msg.offset(),
                    }
                }
            )
    
    def send_message(
        self,
        message: Any,
        key: Optional[Any] = None,
        topic: Optional[str] = None,
        headers: Optional[Dict[str, str]] = None,
        partition: Optional[int] = None,
        callback: Optional[Callable] = None,
    ) -> None:
        """
        Send message to Kafka topic.
        
        Args:
            message: Message to send
            key: Message key
            topic: Topic name (overrides default)
            headers: Message headers
            partition: Specific partition (optional)
            callback: Custom delivery callback
        """
        target_topic = topic or self.topic
        
        try:
            # Serialize key and value
            serialized_key = self.key_serializer(key)
            serialized_value = self.value_serializer(message)
            
            # Prepare message
            msg_data = {
                "topic": target_topic,
                "value": serialized_value,
                "key": serialized_key,
                "headers": headers or {},
            }
            
            if partition is not None:
                msg_data["partition"] = partition
            
            # Send message
            delivery_callback = callback or self._delivery_callback
            
            self.producer.produce(
                **msg_data,
                callback=delivery_callback,
                on_delivery=self._delivery_callback if callback is None else callback
            )
            
            # Trigger delivery
            self.producer.poll(0)
            
            self.logger.info(
                f"Message sent to topic {target_topic}",
                extra={
                    "extra_fields": {
                        "topic": target_topic,
                        "key": str(key) if key else None,
                        "partition": partition,
                        "has_headers": bool(headers),
                    }
                }
            )
            
        except Exception as e:
            self.logger.error(f"Failed to send message: {e}")
            raise
    
    def send_batch(
        self,
        messages: list[tuple[Any, Optional[Any]]],
        topic: Optional[str] = None,
        headers: Optional[Dict[str, str]] = None,
    ) -> None:
        """
        Send batch of messages.
        
        Args:
            messages: List of (message, key) tuples
            topic: Topic name (overrides default)
            headers: Message headers
        """
        target_topic = topic or self.topic
        
        for message, key in messages:
            self.send_message(
                message=message,
                key=key,
                topic=target_topic,
                headers=headers
            )
        
        # Ensure all messages are delivered
        self.flush()
        
        self.logger.info(
            f"Batch of {len(messages)} messages sent to topic {target_topic}",
            extra={
                "extra_fields": {
                    "topic": target_topic,
                    "batch_size": len(messages),
                }
            }
        )
    
    def flush(self, timeout: float = 10.0) -> None:
        """
        Flush producer to ensure all messages are delivered.
        
        Args:
            timeout: Flush timeout in seconds
        """
        remaining = self.producer.flush(timeout)
        if remaining > 0:
            self.logger.warning(f"{remaining} messages were not delivered")
        else:
            self.logger.debug("All messages delivered successfully")
    
    def close(self) -> None:
        """Close producer and flush remaining messages."""
        self.logger.info("Closing Kafka producer")
        self.flush()
        self.producer = None
    
    def __enter__(self):
        """Context manager entry."""
        return self
    
    def __exit__(self, exc_type, exc_val, exc_tb):
        """Context manager exit."""
        self.close()
