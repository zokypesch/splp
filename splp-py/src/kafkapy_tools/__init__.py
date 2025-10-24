"""KafkaPy Tools - Modern Python tools for Apache Kafka messaging."""

# Menekan warning urllib3/LibreSSL sebelum import lainnya
import warnings
warnings.filterwarnings('ignore', message='.*urllib3.*LibreSSL.*')
warnings.filterwarnings('ignore', message='.*NotOpenSSLWarning.*')

# Main components
from .producer import KafkaProducerService
from .consumer import KafkaConsumerService
from .messaging_client import MessagingClient
from .config import KafkaConfig as ServiceKafkaConfig
# logging_config removed - not used in production

# Optional Cassandra logger (may fail on some Python versions)
try:
    from .cassandra_logger import CassandraLogger
except Exception:
    CassandraLogger = None

# Utilities
from .utils import generate_request_id, is_valid_request_id, generate_encryption_key
from .crypto import encrypt_payload, decrypt_payload, generate_encryption_key as crypto_generate_key
# circuit_breaker and retry_manager removed - not used in production

# Types
from .types import (
    KafkaConfig,
    CassandraConfig,
    EncryptionConfig,
    MessagingConfig,
    RequestMessage,
    ResponseMessage,
    EncryptedMessage,
    LogEntry,
    RequestHandler,
    HandlerRegistry,
)

__all__ = [
    # Main components
    "KafkaProducerService",
    "KafkaConsumerService",
    "MessagingClient",
    "KafkaConfig",
    # "setup_logging",  # removed - not used in production
    "CassandraLogger",
    
    # Utilities
    "generate_request_id",
    "is_valid_request_id", 
    "generate_encryption_key",
    "encrypt_payload",
    "decrypt_payload",
    # "CircuitBreaker",  # removed - not used in production
    # "CircuitState",  # removed - not used in production
    # "CircuitBreakerOpenException",  # removed - not used in production
    # "RetryManager",  # removed - not used in production
    # "RetryConfig",  # removed - not used in production
    # "retry",  # removed - not used in production
    # "async_retry",  # removed - not used in production
    
    # Types
    "KafkaConfig",
    "CassandraConfig",
    "EncryptionConfig", 
    "MessagingConfig",
    "RequestMessage",
    "ResponseMessage",
    "EncryptedMessage",
    "LogEntry",
    "RequestHandler",
    "HandlerRegistry",
]
