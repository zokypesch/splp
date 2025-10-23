"""Example usage of KafkaPy Tools."""

import json
import time
from kafkapy_tools import (
    KafkaConfig,
    KafkaProducerService,
    KafkaConsumerService,
    setup_logging
)


def example_producer():
    """Example producer usage."""
    print("🚀 Starting Producer Example")
    
    # Setup logging
    logger = setup_logging(level="INFO")
    
    # Load configuration
    config = KafkaConfig.from_env()
    
    # Create producer
    with KafkaProducerService(config) as producer:
        # Send single message
        message = {
            "id": "msg-001",
            "timestamp": time.time(),
            "data": "Hello from KafkaPy Tools!",
            "metadata": {
                "source": "example-producer",
                "version": "0.1.0"
            }
        }
        
        producer.send_message(
            message=message,
            key="example-key",
            headers={"example": "true", "type": "greeting"}
        )
        
        print("✅ Single message sent")
        
        # Send batch messages
        batch_messages = []
        for i in range(5):
            batch_messages.append((
                {
                    "id": f"batch-msg-{i:03d}",
                    "timestamp": time.time(),
                    "data": f"Batch message {i}",
                    "batch_number": i
                },
                f"batch-key-{i}"
            ))
        
        producer.send_batch(batch_messages)
        print("✅ Batch messages sent")


def example_consumer():
    """Example consumer usage."""
    print("📥 Starting Consumer Example")
    
    # Setup logging
    logger = setup_logging(level="INFO")
    
    # Load configuration
    config = KafkaConfig.from_env()
    
    # Create consumer
    with KafkaConsumerService(config) as consumer:
        def message_handler(message):
            """Handle received message."""
            print(f"📨 Received message:")
            print(f"   Topic: {message['topic']}")
            print(f"   Partition: {message['partition']}")
            print(f"   Offset: {message['offset']}")
            print(f"   Key: {message['key']}")
            print(f"   Value: {json.dumps(message['value'], indent=2)}")
            print(f"   Headers: {message['headers']}")
            print("-" * 50)
        
        print("🔄 Starting message consumption (press Ctrl+C to stop)")
        
        try:
            consumer.consume_messages(
                message_handler=message_handler,
                max_messages=10  # Limit untuk example
            )
        except KeyboardInterrupt:
            print("\n⏹️  Consumer stopped by user")


def example_batch_consumer():
    """Example batch consumer usage."""
    print("📦 Starting Batch Consumer Example")
    
    # Setup logging
    logger = setup_logging(level="INFO")
    
    # Load configuration
    config = KafkaConfig.from_env()
    
    # Create consumer
    with KafkaConsumerService(config) as consumer:
        print("🔄 Consuming messages in batches...")
        
        batch_count = 0
        while batch_count < 3:  # Process 3 batches
            messages = consumer.consume_batch(batch_size=3, timeout=2.0)
            
            if not messages:
                print("⏳ No messages available, waiting...")
                time.sleep(1)
                continue
            
            batch_count += 1
            print(f"📦 Processing batch {batch_count} with {len(messages)} messages")
            
            for i, message in enumerate(messages):
                print(f"   Message {i+1}: {message['value'].get('data', 'No data')}")
            
            print("-" * 50)


def example_custom_serializers():
    """Example with custom serializers."""
    print("🔧 Starting Custom Serializers Example")
    
    def custom_key_serializer(key):
        """Custom key serializer with prefix."""
        return f"custom-{key}".encode('utf-8')
    
    def custom_value_serializer(value):
        """Custom value serializer with metadata."""
        if isinstance(value, dict):
            value['serialized_at'] = time.time()
            value['serializer'] = 'custom'
        return json.dumps(value, ensure_ascii=False).encode('utf-8')
    
    def custom_key_deserializer(key_bytes):
        """Custom key deserializer."""
        if key_bytes:
            return key_bytes.decode('utf-8').replace('custom-', '')
        return None
    
    def custom_value_deserializer(value_bytes):
        """Custom value deserializer."""
        if value_bytes:
            data = json.loads(value_bytes.decode('utf-8'))
            data['deserialized_at'] = time.time()
            return data
        return None
    
    # Load configuration
    config = KafkaConfig.from_env()
    
    # Producer with custom serializers
    with KafkaProducerService(
        config,
        key_serializer=custom_key_serializer,
        value_serializer=custom_value_serializer
    ) as producer:
        message = {
            "id": "custom-001",
            "data": "Message with custom serializers"
        }
        
        producer.send_message(message, key="test-key")
        print("✅ Message sent with custom serializers")
    
    # Consumer with custom deserializers
    with KafkaConsumerService(
        config,
        key_deserializer=custom_key_deserializer,
        value_deserializer=custom_value_deserializer
    ) as consumer:
        message = consumer.poll(timeout=5.0)
        if message:
            print("✅ Message received with custom deserializers:")
            print(f"   Key: {message['key']}")
            print(f"   Value: {json.dumps(message['value'], indent=2)}")


if __name__ == "__main__":
    print("🎯 KafkaPy Tools Examples")
    print("=" * 50)
    
    # Run examples
    try:
        example_producer()
        print("\n" + "=" * 50)
        
        example_consumer()
        print("\n" + "=" * 50)
        
        example_batch_consumer()
        print("\n" + "=" * 50)
        
        example_custom_serializers()
        
    except Exception as e:
        print(f"❌ Error running examples: {e}")
    
    print("\n🎉 Examples completed!")
