"""MessagingClient for KafkaPy Tools - Request-Reply Pattern."""

import json
import asyncio
from typing import Any, Dict, Optional, Callable, TypeVar
from datetime import datetime

from .types import (
    MessagingConfig,
    RequestMessage,
    ResponseMessage,
    EncryptedMessage,
    LogEntry,
    RequestHandler,
    HandlerRegistry,
    KafkaConfig as TypesKafkaConfig,
)
from .config import KafkaConfig as ServiceKafkaConfig
from .crypto import encrypt_payload, decrypt_payload
from .utils import generate_request_id, calculate_duration_ms
from .producer import KafkaProducerService
from .consumer import KafkaConsumerService
import logging

# Optional Cassandra logger
try:
    from .cassandra_logger import CassandraLogger
except Exception:
    CassandraLogger = None

TRequest = TypeVar('TRequest')
TResponse = TypeVar('TResponse')


def convert_kafka_config(messaging_config: TypesKafkaConfig) -> ServiceKafkaConfig:
    """Convert MessagingKafkaConfig to ServiceKafkaConfig."""
    return ServiceKafkaConfig(
        bootstrap_servers=','.join(messaging_config.brokers),
        client_id=messaging_config.client_id,
        security_protocol=messaging_config.security_protocol,
        sasl_mechanism=messaging_config.sasl_mechanism,
        sasl_username=messaging_config.sasl_username,
        sasl_password=messaging_config.sasl_password,
        producer_topic="",  # Will be set dynamically
        consumer_topic="",  # Will be set dynamically
        consumer_group_id=messaging_config.group_id or "default-group",
    )


class MessagingClient:
    """Messaging client for request-reply pattern with automatic encryption."""
    
    def __init__(self, config: MessagingConfig):
        """
        Initialize messaging client.
        
        Args:
            config: MessagingConfig instance
        """
        self.config = config
        self.logger = logging.getLogger(f"{__name__}.messaging")
        
        # Convert TypesKafkaConfig to ServiceKafkaConfig
        service_kafka_config = convert_kafka_config(config.kafka)
        
        # Initialize components
        self.kafka_producer = KafkaProducerService(service_kafka_config)
        self.kafka_consumer = None  # Will be created in start_consuming
        
        # Initialize Cassandra logger if available
        if CassandraLogger is not None:
            self.cassandra_logger = CassandraLogger(config.cassandra)
        else:
            self.cassandra_logger = None
        
        # Request tracking
        self.handlers: HandlerRegistry = {}
        self.pending_requests: Dict[str, Dict[str, Any]] = {}
        
        self.logger.info("MessagingClient initialized")
    
    async def initialize(self) -> None:
        """Initialize all components."""
        try:
            if self.cassandra_logger is not None:
                try:
                    await self.cassandra_logger.initialize()
                    self.logger.info("Cassandra logger initialized successfully")
                except Exception as e:
                    self.logger.warning(f"Failed to initialize Cassandra logger: {e}")
                    self.cassandra_logger = None  # Disable Cassandra logging
            self.logger.info("MessagingClient initialized successfully")
        except Exception as e:
            self.logger.error(f"Failed to initialize MessagingClient: {e}")
            raise
    
    def register_handler(
        self,
        topic: str,
        handler: RequestHandler
    ) -> None:
        """
        Register a handler for a specific topic.
        
        Args:
            topic: Topic name
            handler: Handler function
        """
        self.handlers[topic] = handler
        self.logger.info(f"Handler registered for topic: {topic}")
    
    async def start_consuming(self, topics: list[str]) -> None:
        """
        Start consuming messages and processing with registered handlers.
        
        Args:
            topics: List of topics to consume from
        """
        # Only consume from main topics, not reply topics (following splp-php pattern)
        # Reply topics are only used for sending responses, not consuming
        all_topics = topics
        
        def message_handler(message: Dict[str, Any]) -> None:
            """Handle incoming message."""
            self.logger.info(f"📨 Received message from topic: {message.get('topic')}")
            # Use asyncio.run_coroutine_threadsafe to handle async function from sync context
            import asyncio
            try:
                loop = asyncio.get_event_loop()
                loop.create_task(self._handle_message(message))
            except RuntimeError:
                # If no event loop, create a new one
                asyncio.run(self._handle_message(message))
        
        # Recreate consumer with correct topics
        service_kafka_config = convert_kafka_config(self.config.kafka)
        self.kafka_consumer = KafkaConsumerService(
            config=service_kafka_config,
            topics=all_topics
        )
        
        self.logger.info(f"🔍 Starting consumer for topics: {all_topics}")
        self.logger.info(f"🔍 Consumer group: {service_kafka_config.consumer_group_id}")
        self.logger.info(f"🔍 Consumer client ID: {service_kafka_config.client_id}")
        
        # Start consuming in background
        import threading
        def consume_worker():
            try:
                self.kafka_consumer.consume_messages(
                    message_handler=message_handler,
                    timeout=1.0,
                    auto_commit=False  # Use manual commit to prevent duplicate processing
                )
            except Exception as e:
                self.logger.error(f"Consumer worker error: {e}")
        
        # Start consumer in background thread
        consumer_thread = threading.Thread(target=consume_worker, daemon=True)
        consumer_thread.start()
        
        self.logger.info(f"✅ Started consuming from topics: {all_topics}")
    
    async def request(
        self,
        topic: str,
        payload: TRequest,
        timeout_ms: int = 30000
    ) -> TResponse:
        """
        Send a request to Command Center (following splp-php pattern).
        
        Args:
            topic: Target service topic (will be routed by Command Center)
            payload: Request payload
            timeout_ms: Timeout in milliseconds (not used in splp-php pattern)
        
        Returns:
            Response payload (simulated for compatibility)
        """
        request_id = generate_request_id()
        start_time = datetime.now()
        
        try:
            # Encrypt payload
            encrypted_message = encrypt_payload(payload, self.config.encryption.encryption_key, request_id)
            
            # Log request
            if self.cassandra_logger is not None:
                await self.cassandra_logger.log(LogEntry(
                    request_id=request_id,
                    timestamp=start_time,
                    type="request",
                    topic=topic,
                    payload=payload,
                ))
            
            # Send to Command Center (following splp-php pattern)
            command_center_topic = "command-center-inbox"
            self.kafka_producer.send_message(
                message=encrypted_message,
                key=request_id,
                topic=command_center_topic
            )
            
            self.logger.info(f"Request sent: {request_id} to Command Center for routing to: {topic}")
            
            # In splp-php pattern, we don't wait for direct reply
            # The response will be handled by Command Center routing
            # For compatibility, return a success response
            return {
                "status": "sent",
                "request_id": request_id,
                "target_topic": topic,
                "command_center_topic": command_center_topic,
                "message": "Request sent to Command Center for routing"
            }
                
        except Exception as e:
            self.logger.error(f"Request failed: {e}")
            raise
    
    async def _handle_message(self, message: Dict[str, Any]) -> None:
        """Handle incoming message."""
        try:
            topic = message["topic"]
            message_value = message["value"]
            
            # All messages from main topics are requests (following splp-php pattern)
            await self._handle_request(topic, message_value)
                
        except Exception as e:
            self.logger.error(f"Error handling message: {e}")
    
    async def _handle_request(self, topic: str, message_value: Any) -> None:
        """Handle incoming request."""
        start_time = datetime.now()
        request_id = ""
        
        try:
            # Parse message - message_value might already be a dict or a JSON string
            if isinstance(message_value, str):
                message_data = json.loads(message_value)
            elif isinstance(message_value, dict):
                message_data = message_value
            else:
                raise ValueError(f"Invalid message value type: {type(message_value)}")
            
            # Handle both EncryptedMessage format and RoutedMessage format from Command Center
            if "request_id" in message_data and "worker_name" in message_data:
                # RoutedMessage format from Command Center (from example-bun)
                request_id = message_data["request_id"]
                worker_name = message_data.get("worker_name", "unknown")
                source_topic = message_data.get("source_topic", "unknown")
                
                self.logger.info(f"Received RoutedMessage from Command Center: {worker_name} -> {topic}")
                self.logger.info(f"Request ID: {request_id}, Source Topic: {source_topic}")
                
                encrypted_message = {
                    "request_id": request_id,
                    "data": message_data["data"],
                    "iv": message_data["iv"],
                    "tag": message_data["tag"],
                }
            elif "request_id" in message_data:
                # EncryptedMessage format (direct)
                request_id = message_data["request_id"]
                encrypted_message = message_data
            else:
                raise ValueError("Invalid message format: missing request_id")
            
            # Decrypt payload
            decrypted_data = decrypt_payload(encrypted_message, self.config.encryption.encryption_key)
            payload = decrypted_data["payload"]
            
            self.logger.info(f"Request received: {request_id} from topic: {topic}")
            
            # Find handler
            handler = self.handlers.get(topic)
            if not handler:
                raise ValueError(f"No handler registered for topic: {topic}")
            
            # Process with handler
            response_payload = await handler(request_id, payload)
            
            # Create response
            response = ResponseMessage(
                request_id=request_id,
                payload=response_payload,
                timestamp=int(datetime.now().timestamp() * 1000),
                success=True,
            )
            
            # Encrypt response
            encrypted_response = encrypt_payload(response.dict(), self.config.encryption.encryption_key, request_id)
            
            # Create Command Center message format (following splp-php pattern)
            # This is required for Command Center routing
            worker_name = self.config.worker_name
            command_center_message = {
                'request_id': request_id,
                'worker_name': worker_name,  # This identifies routing: service_1 -> service_2
                'data': encrypted_response['data'],
                'iv': encrypted_response['iv'],
                'tag': encrypted_response['tag']
            }
            
            # Send reply to Command Center (following splp-php pattern)
            # Reply topics are not used - replies go back to Command Center
            command_center_topic = "command-center-inbox"
            
            # Log sending details (similar to PHP)
            self.logger.info(f"📤 Mengirim hasil verifikasi ke Command Center...")
            self.logger.info(f"  🎯 Target Topic: {command_center_topic}")
            self.logger.info(f"  🆔 Request ID: {request_id}")
            self.logger.info(f"  👤 Worker Name: {worker_name}")
            self.logger.info(f"  📦 Message Size: {len(str(command_center_message))} bytes")
            
            self.kafka_producer.send_message(
                message=command_center_message,
                key=request_id,
                topic=command_center_topic
            )
            
            self.logger.info("✅ Hasil verifikasi berhasil dikirim ke Command Center")
            self.logger.info("🔄 Command Center akan meroute ke service berikutnya")
            
            # Log response
            duration_ms = calculate_duration_ms(start_time.timestamp())
            if self.cassandra_logger is not None:
                await self.cassandra_logger.log(LogEntry(
                    request_id=request_id,
                    timestamp=datetime.now(),
                    type="response",
                    topic=topic,
                    payload=response_payload,
                    success=True,
                    duration_ms=duration_ms,
                ))
            
            self.logger.info(f"Response sent: {request_id} ({duration_ms}ms)")
            
        except Exception as e:
            # Log error
            duration_ms = calculate_duration_ms(start_time.timestamp())
            if self.cassandra_logger is not None:
                await self.cassandra_logger.log(LogEntry(
                    request_id=request_id,
                    timestamp=datetime.now(),
                    type="response",
                    topic=topic,
                    payload=None,
                    success=False,
                    error=str(e),
                    duration_ms=duration_ms,
                ))
            
            self.logger.error(f"Error handling request {request_id or 'unknown'}: {e}")
    
    
    def get_logger(self) -> Optional[CassandraLogger]:
        """Get Cassandra logger instance."""
        return self.cassandra_logger
    
    async def close(self) -> None:
        """Close all connections."""
        if self.cassandra_logger is not None:
            await self.cassandra_logger.close()
        self.kafka_producer.close()
        self.kafka_consumer.close()
        self.logger.info("MessagingClient closed")
