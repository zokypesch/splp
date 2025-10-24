"""Advanced example with Avro support and error handling."""

import json
import time
import logging
from typing import Dict, Any, Optional
from kafkapy_tools import (
    KafkaConfig,
    KafkaProducerService,
    KafkaConsumerService,
    setup_logging
)


class AdvancedKafkaExample:
    """Advanced Kafka example with error handling and monitoring."""
    
    def __init__(self):
        self.logger = setup_logging(level="INFO")
        self.config = KafkaConfig.from_env()
        self.message_count = 0
        self.error_count = 0
    
    def send_with_retry(
        self,
        producer: KafkaProducerService,
        message: Dict[str, Any],
        key: str,
        max_retries: int = 3
    ) -> bool:
        """Send message with retry logic."""
        for attempt in range(max_retries):
            try:
                producer.send_message(message, key=key)
                self.message_count += 1
                self.logger.info(f"Message sent successfully (attempt {attempt + 1})")
                return True
            except Exception as e:
                self.logger.warning(f"Send attempt {attempt + 1} failed: {e}")
                if attempt == max_retries - 1:
                    self.error_count += 1
                    self.logger.error(f"Failed to send message after {max_retries} attempts")
                    return False
                time.sleep(2 ** attempt)  # Exponential backoff
        return False
    
    def producer_with_monitoring(self):
        """Producer with monitoring and error handling."""
        self.logger.info("🚀 Starting Advanced Producer")
        
        with KafkaProducerService(self.config) as producer:
            # Send various types of messages
            messages = [
                {
                    "type": "user_action",
                    "user_id": "user-123",
                    "action": "login",
                    "timestamp": time.time(),
                    "metadata": {"ip": "192.168.1.1", "user_agent": "Mozilla/5.0"}
                },
                {
                    "type": "system_event",
                    "event": "server_startup",
                    "timestamp": time.time(),
                    "metadata": {"version": "1.0.0", "environment": "production"}
                },
                {
                    "type": "business_metric",
                    "metric_name": "page_views",
                    "value": 1500,
                    "timestamp": time.time(),
                    "metadata": {"page": "/dashboard", "session_id": "sess-456"}
                }
            ]
            
            for i, message in enumerate(messages):
                key = f"{message['type']}-{i}"
                success = self.send_with_retry(producer, message, key)
                
                if success:
                    self.logger.info(f"✅ Message {i+1} sent successfully")
                else:
                    self.logger.error(f"❌ Failed to send message {i+1}")
                
                time.sleep(0.5)  # Small delay between messages
            
            # Flush to ensure all messages are sent
            producer.flush()
            
            self.logger.info(f"📊 Producer Summary: {self.message_count} sent, {self.error_count} failed")
    
    def consumer_with_error_handling(self):
        """Consumer with comprehensive error handling."""
        self.logger.info("📥 Starting Advanced Consumer")
        
        def safe_message_handler(message: Dict[str, Any]) -> None:
            """Safe message handler with error handling."""
            try:
                self.logger.info(f"📨 Processing message from {message['topic']}")
                
                # Validate message structure
                if not self.validate_message(message):
                    self.logger.warning("⚠️  Invalid message structure, skipping")
                    return
                
                # Process based on message type
                message_type = message['value'].get('type', 'unknown')
                
                if message_type == 'user_action':
                    self.process_user_action(message)
                elif message_type == 'system_event':
                    self.process_system_event(message)
                elif message_type == 'business_metric':
                    self.process_business_metric(message)
                else:
                    self.logger.warning(f"⚠️  Unknown message type: {message_type}")
                
                self.message_count += 1
                
            except Exception as e:
                self.error_count += 1
                self.logger.error(f"❌ Error processing message: {e}")
                # Continue processing other messages
    
        with KafkaConsumerService(self.config) as consumer:
            try:
                consumer.consume_messages(
                    message_handler=safe_message_handler,
                    max_messages=10,
                    timeout=1.0
                )
            except KeyboardInterrupt:
                self.logger.info("⏹️  Consumer stopped by user")
            except Exception as e:
                self.logger.error(f"❌ Consumer error: {e}")
            
            self.logger.info(f"📊 Consumer Summary: {self.message_count} processed, {self.error_count} errors")
    
    def validate_message(self, message: Dict[str, Any]) -> bool:
        """Validate message structure."""
        required_fields = ['topic', 'partition', 'offset', 'value']
        
        for field in required_fields:
            if field not in message:
                self.logger.warning(f"Missing required field: {field}")
                return False
        
        if not isinstance(message['value'], dict):
            self.logger.warning("Message value is not a dictionary")
            return False
        
        return True
    
    def process_user_action(self, message: Dict[str, Any]) -> None:
        """Process user action message."""
        data = message['value']
        self.logger.info(f"👤 User Action: {data['user_id']} - {data['action']}")
        
        # Simulate processing
        time.sleep(0.1)
    
    def process_system_event(self, message: Dict[str, Any]) -> None:
        """Process system event message."""
        data = message['value']
        self.logger.info(f"⚙️  System Event: {data['event']}")
        
        # Simulate processing
        time.sleep(0.1)
    
    def process_business_metric(self, message: Dict[str, Any]) -> None:
        """Process business metric message."""
        data = message['value']
        self.logger.info(f"📈 Business Metric: {data['metric_name']} = {data['value']}")
        
        # Simulate processing
        time.sleep(0.1)
    
    def run_advanced_example(self):
        """Run the complete advanced example."""
        self.logger.info("🎯 Starting Advanced KafkaPy Tools Example")
        self.logger.info("=" * 60)
        
        try:
            # Reset counters
            self.message_count = 0
            self.error_count = 0
            
            # Run producer
            self.producer_with_monitoring()
            
            self.logger.info("\n" + "=" * 60)
            
            # Reset counters for consumer
            self.message_count = 0
            self.error_count = 0
            
            # Run consumer
            self.consumer_with_error_handling()
            
        except Exception as e:
            self.logger.error(f"❌ Advanced example failed: {e}")
        
        self.logger.info("\n🎉 Advanced example completed!")


def example_avro_support():
    """Example with Avro schema support."""
    print("🔧 Starting Avro Support Example")
    
    # Avro schema for user events
    avro_schema = """
    {
        "type": "record",
        "name": "UserEvent",
        "fields": [
            {"name": "user_id", "type": "string"},
            {"name": "event_type", "type": "string"},
            {"name": "timestamp", "type": "long"},
            {"name": "properties", "type": {"type": "map", "values": "string"}}
        ]
    }
    """
    
    config = KafkaConfig.from_env()
    
    try:
        # Producer with Avro
        with KafkaProducerService(
            config,
            schema_registry_url="http://10.70.1.23:8081",
            avro_schema=avro_schema
        ) as producer:
            user_event = {
                "user_id": "user-123",
                "event_type": "page_view",
                "timestamp": int(time.time() * 1000),
                "properties": {
                    "page": "/dashboard",
                    "session_id": "sess-456",
                    "referrer": "https://example.com"
                }
            }
            
            producer.send_message(user_event, key="user-123")
            print("✅ Avro message sent")
        
        # Consumer with Avro
        with KafkaConsumerService(
            config,
            schema_registry_url="http://10.70.1.23:8081",
            avro_schema=avro_schema
        ) as consumer:
            message = consumer.poll(timeout=5.0)
            if message:
                print("✅ Avro message received:")
                print(f"   User ID: {message['value']['user_id']}")
                print(f"   Event Type: {message['value']['event_type']}")
                print(f"   Properties: {message['value']['properties']}")
    
    except Exception as e:
        print(f"⚠️  Avro example requires Schema Registry running on localhost:8081")
        print(f"   Error: {e}")


if __name__ == "__main__":
    # Run advanced example
    advanced_example = AdvancedKafkaExample()
    advanced_example.run_advanced_example()
    
    print("\n" + "=" * 60)
    
    # Run Avro example (if Schema Registry is available)
    example_avro_support()
