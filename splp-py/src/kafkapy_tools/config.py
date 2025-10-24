"""Configuration management for KafkaPy Tools."""

import os
from typing import Dict, Any, Optional
from pydantic import BaseModel, Field
from dotenv import load_dotenv


class KafkaConfig(BaseModel):
    """Kafka configuration model."""
    
    # Basic connection settings
    bootstrap_servers: str = Field(default="10.70.1.23:9092", description="Kafka bootstrap servers")
    client_id: str = Field(default="kafkapy-client", description="Kafka client ID")
    security_protocol: str = Field(default="PLAINTEXT", description="Security protocol")
    sasl_mechanism: Optional[str] = Field(default=None, description="SASL mechanism")
    sasl_username: Optional[str] = Field(default=None, description="SASL username")
    sasl_password: Optional[str] = Field(default=None, description="SASL password")
    
    # Producer settings
    producer_topic: str = Field(default="test-topic", description="Default producer topic")
    producer_acks: str = Field(default="all", description="Producer acknowledgment level")
    producer_retries: int = Field(default=3, description="Number of retries")
    producer_batch_size: int = Field(default=16384, description="Batch size in bytes")
    producer_linger_ms: int = Field(default=5, description="Linger time in milliseconds")
    producer_buffer_memory: int = Field(default=33554432, description="Buffer memory in bytes")
    
    # Consumer settings
    consumer_topic: str = Field(default="test-topic", description="Default consumer topic")
    consumer_group_id: str = Field(default="service-1-group", description="Consumer group ID")
    consumer_auto_offset_reset: str = Field(default="latest", description="Auto offset reset")
    consumer_enable_auto_commit: bool = Field(default=False, description="Enable auto commit")
    consumer_auto_commit_interval_ms: int = Field(default=5000, description="Auto commit interval")
    consumer_session_timeout_ms: int = Field(default=30000, description="Session timeout")
    consumer_max_poll_records: int = Field(default=500, description="Max poll records")
    
    @classmethod
    def from_env(cls, env_file: Optional[str] = None) -> "KafkaConfig":
        """Load configuration from environment variables."""
        if env_file:
            load_dotenv(env_file)
        else:
            load_dotenv()
        
        return cls(
            bootstrap_servers=os.getenv("KAFKA_BOOTSTRAP_SERVERS", "10.70.1.23:9092"),
            security_protocol=os.getenv("KAFKA_SECURITY_PROTOCOL", "PLAINTEXT"),
            sasl_mechanism=os.getenv("KAFKA_SASL_MECHANISM"),
            sasl_username=os.getenv("KAFKA_SASL_USERNAME"),
            sasl_password=os.getenv("KAFKA_SASL_PASSWORD"),
            producer_topic=os.getenv("KAFKA_PRODUCER_TOPIC", "test-topic"),
            producer_acks=os.getenv("KAFKA_PRODUCER_ACKS", "all"),
            producer_retries=int(os.getenv("KAFKA_PRODUCER_RETRIES", "3")),
            producer_batch_size=int(os.getenv("KAFKA_PRODUCER_BATCH_SIZE", "16384")),
            producer_linger_ms=int(os.getenv("KAFKA_PRODUCER_LINGER_MS", "5")),
            producer_buffer_memory=int(os.getenv("KAFKA_PRODUCER_BUFFER_MEMORY", "33554432")),
            consumer_topic=os.getenv("KAFKA_CONSUMER_TOPIC", "test-topic"),
            consumer_group_id=os.getenv("KAFKA_CONSUMER_GROUP_ID", "test-group"),
            consumer_auto_offset_reset=os.getenv("KAFKA_CONSUMER_AUTO_OFFSET_RESET", "earliest"),
            consumer_enable_auto_commit=os.getenv("KAFKA_CONSUMER_ENABLE_AUTO_COMMIT", "true").lower() == "true",
            consumer_auto_commit_interval_ms=int(os.getenv("KAFKA_CONSUMER_AUTO_COMMIT_INTERVAL_MS", "1000")),
            consumer_session_timeout_ms=int(os.getenv("KAFKA_CONSUMER_SESSION_TIMEOUT_MS", "30000")),
            consumer_max_poll_records=int(os.getenv("KAFKA_CONSUMER_MAX_POLL_RECORDS", "500")),
        )
    
    def get_producer_config(self) -> Dict[str, Any]:
        """Get producer configuration dictionary."""
        # Konfigurasi berdasarkan splp-php (rdkafka)
        config = {
            "bootstrap.servers": self.bootstrap_servers,
            "client.id": f"{self.bootstrap_servers.split(':')[0]}-producer",
            "acks": "all",
            "retries": 5,
            "retry.backoff.ms": 100,
            "message.timeout.ms": self.producer_retries * 1000,  # Use retries as timeout
        }
        
        return config
    
    def get_consumer_config(self) -> Dict[str, Any]:
        """Get consumer configuration dictionary."""
        # Konfigurasi berdasarkan splp-php (rdkafka)
        config = {
            "bootstrap.servers": self.bootstrap_servers,
            "group.id": self.consumer_group_id,
            "client.id": f"{self.bootstrap_servers.split(':')[0]}-consumer",
            "auto.offset.reset": "earliest",
            "enable.auto.commit": True,
            "auto.commit.interval.ms": 1000,
            "session.timeout.ms": 30000,
            "heartbeat.interval.ms": 3000,
        }
        
        return config
