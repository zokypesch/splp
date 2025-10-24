"""Production Publisher Example for KafkaPy Tools - Similar to splp-php publisher"""

import asyncio
import os
from datetime import datetime
from kafkapy_tools import (
    MessagingClient,
    MessagingConfig,
    CassandraConfig,
    EncryptionConfig,
)
from kafkapy_tools.types import KafkaConfig


def get_config() -> MessagingConfig:
    """Get configuration from environment variables."""
    brokers = os.getenv('KAFKA_BROKERS', '10.70.1.23:9092').split(',')
    contact_points = os.getenv('CASSANDRA_CONTACT_POINTS', 'localhost').split(',')
    
    return MessagingConfig(
        kafka=KafkaConfig(
            brokers=brokers,
            client_id=os.getenv('KAFKA_CLIENT_ID', 'dukcapil-service'),
            group_id=os.getenv('KAFKA_GROUP_ID', 'service-1-group'),
        ),
        cassandra=CassandraConfig(
            contact_points=contact_points,
            local_data_center=os.getenv('CASSANDRA_LOCAL_DATACENTER', 'datacenter1'),
            keyspace=os.getenv('CASSANDRA_KEYSPACE', 'service_1_keyspace'),
        ),
        encryption=EncryptionConfig(
            encryption_key=os.getenv('ENCRYPTION_KEY', 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d'),
        ),
    )


async def send_dukcapil_request(client: MessagingClient, nik: str) -> dict:
    """Send Dukcapil request to service."""
    print(f"Sending Dukcapil request for NIK: {nik}")
    
    payload = {
        'type': 'get_nik',
        'data': {
            'nik': nik,
            'requested_at': datetime.now().isoformat(),
        }
    }
    
    try:
        response = await client.request(
            topic='service-1-topic',
            payload=payload,
            timeout_ms=30000
        )
        
        print(f"✅ Dukcapil response received: {response}")
        return response
        
    except Exception as e:
        print(f"❌ Dukcapil request failed: {e}")
        raise




async def main():
    """Main publisher function."""
    print("🚀 Starting Production Publisher (KafkaPy Tools)")
    print("=" * 60)
    
    # Get configuration
    config = get_config()
    service_config = {
        'name': os.getenv('SERVICE_NAME', 'Dukcapil Service'),
        'version': os.getenv('SERVICE_VERSION', '1.0.0'),
        'worker_name': os.getenv('SERVICE_WORKER_NAME', 'service-1b-publisher'),
    }
    
    print(f"Service: {service_config['name']} v{service_config['version']}")
    print(f"Publisher: {service_config['worker_name']}")
    print(f"Kafka Brokers: {config.kafka.brokers}")
    print(f"Producer Topic: {os.getenv('KAFKA_PRODUCER_TOPIC', 'command-center-inbox')}")
    print("=" * 60)
    
    # Initialize messaging client
    client = MessagingClient(config)
    await client.initialize()
    
    try:
        # Example 1: Send Dukcapil request
        print("\n--- Example 1: Dukcapil Request ---")
        dukcapil_response = await send_dukcapil_request(client, "1234567890123456")
        
        # Example 2: Multiple concurrent requests
        print("\n--- Example 2: Multiple Concurrent Requests ---")
        requests = [
            send_dukcapil_request(client, "1111111111111111"),
            send_dukcapil_request(client, "2222222222222222"),
            send_dukcapil_request(client, "3333333333333333"),
        ]
        
        print("Sending 3 concurrent requests...")
        responses = await asyncio.gather(*requests)
        
        print("All responses received:")
        for i, response in enumerate(responses):
            print(f"  {i + 1}. {response}")
        
        # Example 3: Query logs from Cassandra
        print("\n--- Example 3: Query Logs ---")
        logger = client.get_logger()
        
        # Get logs from the last 5 minutes
        from datetime import timedelta
        end_time = datetime.now()
        start_time = end_time - timedelta(minutes=5)
        
        logs = await logger.get_logs_by_time_range(start_time, end_time)
        print(f"Found {len(logs)} log entries in the last 5 minutes")
        
        if logs:
            print("Sample log entry:", logs[0])
        
    except Exception as e:
        print(f"❌ Publisher error: {e}")
    finally:
        # Clean up
        print("\n🔄 Closing publisher...")
        await client.close()
        print("✅ Publisher closed")


if __name__ == "__main__":
    # Run the publisher
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n⏹️  Publisher interrupted by user")
    except Exception as e:
        print(f"❌ Publisher error: {e}")
        exit(1)
