"""Example Client for KafkaPy Tools - Request-Reply Pattern."""

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


# Sample request/response types
class CalculateRequest:
    def __init__(self, operation: str, a: float, b: float):
        self.operation = operation
        self.a = a
        self.b = b


class CalculateResponse:
    def __init__(self, result: float, operation: str):
        self.result = result
        self.operation = operation


class UserRequest:
    def __init__(self, user_id: str):
        self.user_id = user_id


class UserResponse:
    def __init__(self, user_id: str, name: str, email: str):
        self.user_id = user_id
        self.name = name
        self.email = email


async def main():
    """Main client function."""
    print("🚀 Starting Example Client...")
    
    # Configuration
    config = MessagingConfig(
        kafka=KafkaConfig(
            brokers=["10.70.1.23:9092"],
            client_id="example-client",
            group_id="client-group",
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
    
    print("✅ Client initialized, sending test requests...\n")
    
    try:
        # Example 1: Send calculate request
        print("--- Example 1: Calculate Request ---")
        calculate_request = CalculateRequest("add", 10, 5)
        
        print(f"Sending request: {calculate_request.operation}({calculate_request.a}, {calculate_request.b})")
        
        # Send request - encryption happens automatically!
        calculate_response = await client.request(
            topic="calculate",
            payload={
                "operation": calculate_request.operation,
                "a": calculate_request.a,
                "b": calculate_request.b,
            },
            timeout_ms=30000  # 30 second timeout
        )
        
        print(f"Received response: {calculate_response}")
        print(f"Result: {calculate_response['result']}\n")
        
        # Example 2: Send get-user request
        print("--- Example 2: Get User Request ---")
        user_request = UserRequest("12345")
        
        print(f"Sending request: {user_request.user_id}")
        
        user_response = await client.request(
            topic="get-user",
            payload={"userId": user_request.user_id},
            timeout_ms=30000
        )
        
        print(f"Received response: {user_response}")
        print(f"User: {user_response['name']} ({user_response['email']})\n")
        
        # Example 3: Multiple concurrent requests
        print("--- Example 3: Multiple Concurrent Requests ---")
        
        requests = [
            client.request(
                topic="calculate",
                payload={"operation": "multiply", "a": 7, "b": 8}
            ),
            client.request(
                topic="calculate", 
                payload={"operation": "subtract", "a": 100, "b": 25}
            ),
            client.request(
                topic="get-user",
                payload={"userId": "999"}
            ),
        ]
        
        print("Sending 3 concurrent requests...")
        responses = await asyncio.gather(*requests)
        
        print("All responses received:")
        for i, response in enumerate(responses):
            print(f"  {i + 1}. {response}")
        
        # Example 4: Query logs from Cassandra
        print("\n--- Example 4: Query Logs ---")
        logger = client.get_logger()
        
        # Get logs from the last 5 minutes
        from datetime import datetime, timedelta
        end_time = datetime.now()
        start_time = end_time - timedelta(minutes=5)
        
        logs = await logger.get_logs_by_time_range(start_time, end_time)
        print(f"Found {len(logs)} log entries in the last 5 minutes")
        
        if logs:
            print("Sample log entry:", logs[0])
        
    except Exception as e:
        print(f"❌ Error: {e}")
    finally:
        # Clean up
        print("\n🔄 Closing client...")
        await client.close()
        print("✅ Client closed")


if __name__ == "__main__":
    # Run the client
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n⏹️  Client interrupted by user")
    except Exception as e:
        print(f"❌ Client error: {e}")
        exit(1)
