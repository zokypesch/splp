"""Types and interfaces for KafkaPy Tools."""

from typing import Dict, Any, Optional, Callable, List, TypeVar
from pydantic import BaseModel, Field
from datetime import datetime

# Type variables for generic types
TRequest = TypeVar('TRequest')
TResponse = TypeVar('TResponse')


class KafkaConfig(BaseModel):
    """Kafka configuration for MessagingConfig."""
    brokers: List[str] = Field(..., description="Kafka broker addresses")
    client_id: str = Field(..., description="Kafka client ID")
    group_id: Optional[str] = Field(None, description="Consumer group ID")
    security_protocol: str = Field(default="PLAINTEXT", description="Security protocol")
    sasl_mechanism: Optional[str] = Field(default=None, description="SASL mechanism")
    sasl_username: Optional[str] = Field(default=None, description="SASL username")
    sasl_password: Optional[str] = Field(default=None, description="SASL password")


class CassandraConfig(BaseModel):
    """Cassandra configuration."""
    contact_points: List[str] = Field(..., description="Cassandra contact points")
    local_data_center: str = Field(..., description="Local data center")
    keyspace: str = Field(..., description="Cassandra keyspace")


class EncryptionConfig(BaseModel):
    """Encryption configuration."""
    encryption_key: str = Field(..., description="32-byte hex string for AES-256")


class MessagingConfig(BaseModel):
    """Complete messaging configuration."""
    kafka: KafkaConfig
    cassandra: CassandraConfig
    encryption: EncryptionConfig
    worker_name: Optional[str] = Field(default="service-1-publisher", description="Worker name for Command Center routing")


class RequestMessage(BaseModel):
    """Request message structure."""
    request_id: str
    payload: Any
    timestamp: int


class ResponseMessage(BaseModel):
    """Response message structure."""
    request_id: str
    payload: Any
    timestamp: int
    success: bool
    error: Optional[str] = None


class EncryptedMessage(BaseModel):
    """Encrypted message structure."""
    request_id: str  # Not encrypted
    data: str  # Encrypted payload
    iv: str  # Initialization vector for AES-GCM
    tag: str  # Authentication tag for AES-GCM


class LogEntry(BaseModel):
    """Log entry structure."""
    request_id: str
    timestamp: datetime
    type: str  # 'request' | 'response'
    topic: str
    payload: Any
    success: Optional[bool] = None
    error: Optional[str] = None
    duration_ms: Optional[int] = None


# Type aliases
RequestHandler = Callable[[str, Any], Any]
HandlerRegistry = Dict[str, RequestHandler]
