"""Example Worker for KafkaPy Tools - Request-Reply Pattern."""

import asyncio
import os
from kafkapy_tools import (
    MessagingClient,
    MessagingConfig,
    KafkaConfig,
    CassandraConfig,
    EncryptionConfig,
    generate_encryption_key,
)


async def main():
    """Main worker function."""
    print("🚀 Starting Example Worker...")
    
    # Configuration
    config = MessagingConfig(
        kafka=KafkaConfig(
            brokers=["10.70.1.23:9092"],
            client_id="example-worker",
            group_id="worker-group",
        ),
        cassandra=CassandraConfig(
            contact_points=["localhost"],
            local_data_center="datacenter1",
            keyspace="messaging",
        ),
        encryption=EncryptionConfig(
            # In production, load this from environment variable
            encryption_key=os.getenv("ENCRYPTION_KEY", generate_encryption_key()),
        ),
    )
    
    # Initialize messaging client
    client = MessagingClient(config)
    await client.initialize()
    
    # Register handler for "calculate" topic
    # Users only need to write their business logic
    client.register_handler(
        topic="calculate",
        handler=async def calculate_handler(request_id: str, payload: dict):
            print(f"Processing calculate request {request_id}: {payload}")
            
            operation = payload["operation"]
            a = payload["a"]
            b = payload["b"]
            
            result = 0
            if operation == "add":
                result = a + b
            elif operation == "subtract":
                result = a - b
            elif operation == "multiply":
                result = a * b
            elif operation == "divide":
                if b == 0:
                    raise ValueError("Division by zero")
                result = a / b
            else:
                raise ValueError(f"Unknown operation: {operation}")
            
            return {
                "result": result,
                "operation": operation,
            }
    )
    
    # Register handler for "get-user" topic
    client.register_handler(
        topic="get-user",
        handler=async def get_user_handler(request_id: str, payload: dict):
            print(f"Processing get-user request {request_id}: {payload}")
            
            user_id = payload["userId"]
            
            # Simulate database lookup
            await asyncio.sleep(0.1)
            
            return {
                "userId": user_id,
                "name": f"User {user_id}",
                "email": f"user{user_id}@example.com",
            }
    )
    
    # Start consuming from topics
    # All encryption/decryption happens automatically!
    await client.start_consuming(["calculate", "get-user"])
    
    print("✅ Worker is running and waiting for messages...")
    print("📋 Registered handlers: calculate, get-user")
    print("⏹️  Press Ctrl+C to exit")
    
    # Handle graceful shutdown
    try:
        # Keep the worker running
        while True:
            await asyncio.sleep(1)
    except KeyboardInterrupt:
        print("\n🔄 Shutting down worker...")
        await client.close()
        print("✅ Worker closed")


if __name__ == "__main__":
    # Run the worker
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n⏹️  Worker interrupted by user")
    except Exception as e:
        print(f"❌ Worker error: {e}")
        exit(1)
