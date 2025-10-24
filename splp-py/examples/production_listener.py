"""Production Listener Example for KafkaPy Tools - Similar to splp-php production-listener.php"""

import asyncio
import os
from datetime import datetime
from kafkapy_tools import (
    MessagingClient,
    MessagingConfig,
    CassandraConfig,
    EncryptionConfig,
    generate_encryption_key,
)
from kafkapy_tools.types import KafkaConfig


def get_config() -> MessagingConfig:
    """
    Get configuration from environment variables.
    Equivalent to the PHP production-listener.php config.
    """
    # Parse brokers from comma-separated string
    brokers = os.getenv('KAFKA_BOOTSTRAP_SERVERS', '10.70.1.23:9092').split(',')
    
    # Parse Cassandra contact points from comma-separated string
    contact_points = os.getenv('CASSANDRA_CONTACT_POINTS', 'localhost').split(',')
    
    return MessagingConfig(
        kafka=KafkaConfig(
            brokers=brokers,
            client_id=os.getenv('KAFKA_CLIENT_ID', 'dukcapil-service'),
            group_id=os.getenv('KAFKA_GROUP_ID', 'service-1-group'),
            security_protocol=os.getenv('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
            sasl_mechanism=os.getenv('KAFKA_SASL_MECHANISM'),
            sasl_username=os.getenv('KAFKA_SASL_USERNAME'),
            sasl_password=os.getenv('KAFKA_SASL_PASSWORD'),
        ),
        cassandra=CassandraConfig(
            contact_points=contact_points,
            local_data_center=os.getenv('CASSANDRA_LOCAL_DATA_CENTER', 'datacenter1'),
            keyspace=os.getenv('CASSANDRA_KEYSPACE', 'service_1_keyspace'),
        ),
        encryption=EncryptionConfig(
            encryption_key=os.getenv('ENCRYPTION_KEY', 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d'),
        ),
        worker_name=os.getenv('SERVICE_WORKER_NAME', 'service-1-publisher'),
    )


def get_service_config() -> dict:
    """Get service configuration."""
    return {
        'name': os.getenv('SERVICE_NAME', 'Dukcapil Service'),
        'version': os.getenv('SERVICE_VERSION', '1.0.0'),
        'worker_name': os.getenv('SERVICE_WORKER_NAME', 'service-1-publisher'),
    }


async def handle_dukcapil_request(request_id: str, payload: dict) -> dict:
    """
    Handle Dukcapil service request.
    Equivalent to the PHP Service1MessageProcessor logic.
    Handles BansosCitizenRequest format from example-bun.
    """
    print("─" * 60)
    print("📥 [DUKCAPIL] Menerima data dari Command Center")
    print(f"  Request ID: {request_id}")
    
    # Handle BansosCitizenRequest format from example-bun
    if 'registrationId' in payload and 'nik' in payload:
        # Format from example-bun publisher
        registration_id = payload.get('registrationId', '')
        nik = payload.get('nik', '')
        full_name = payload.get('fullName', '')
        date_of_birth = payload.get('dateOfBirth', '')
        address = payload.get('address', '')
        assistance_type = payload.get('assistanceType', '')
        requested_amount = payload.get('requestedAmount', 0)
        
        print(f"  Registration ID: {registration_id}")
        print(f"  NIK: {nik}")
        print(f"  Nama: {full_name}")
        print(f"  Tanggal Lahir: {date_of_birth}")
        print(f"  Alamat: {address}")
        print(f"  Jenis Bantuan: {assistance_type}")
        print(f"  Nominal: Rp {requested_amount:,}")
        print()
        
        # Simulate verification process
        print("🔄 Memverifikasi data kependudukan...")
        await asyncio.sleep(0.5)  # Simulate verification delay
        
        # Verify population data
        verification_result = await verify_population_data(payload, request_id)
        
        print("✓ Hasil verifikasi terkirim ke Command Center")
        return verification_result
    
    # Handle legacy format (type/data structure)
    elif 'type' in payload:
        request_type = payload.get('type', 'unknown')
        request_data = payload.get('data', {})
        
        print(f"Processing legacy request {request_id}: {request_type}")
        
        if request_type == 'get_nik':
            nik = request_data.get('nik', '')
            if not nik:
                raise ValueError("NIK is required")
            
            await asyncio.sleep(0.1)
            
            return {
                'status': 'success',
                'data': {
                    'nik': nik,
                    'name': f'Citizen {nik}',
                    'address': 'Sample Address',
                    'birth_date': '1990-01-01',
                    'processed_at': datetime.now().isoformat(),
                }
            }
        
        elif request_type == 'update_data':
            await asyncio.sleep(0.2)
            
            return {
                'status': 'success',
                'message': 'Data updated successfully',
                'updated_at': datetime.now().isoformat(),
            }
        
        else:
            raise ValueError(f"Unknown request type: {request_type}")
    
    else:
        raise ValueError(f"Unknown payload format: {payload}")


async def verify_population_data(payload: dict, request_id: str) -> dict:
    """
    Verify population data similar to PHP Service1MessageProcessor.
    """
    nik = payload.get('nik', '')
    full_name = payload.get('fullName', '')
    
    # Simulate database verification
    verification_data = {
        'nik': nik,
        'full_name': full_name,
        'verification_status': 'verified',
        'population_data': {
            'name': full_name,
            'nik': nik,
            'address': payload.get('address', ''),
            'birth_date': payload.get('dateOfBirth', ''),
            'citizenship_status': 'active',
            'verification_timestamp': datetime.now().isoformat(),
        },
        'assistance_eligibility': {
            'eligible': True,
            'reason': 'Data kependudukan valid',
            'verification_score': 95,
        },
        'processed_at': datetime.now().isoformat(),
        'request_id': request_id,
    }
    
    return verification_data




async def main():
    """Main production listener function."""
    print("═══════════════════════════════════════════════════════════")
    print("🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil")
    print("    Population Data Verification Service (Python)")
    print("═══════════════════════════════════════════════════════════")
    print()
    
    # Get configuration
    config = get_config()
    service_config = get_service_config()
    
    # Print configuration (similar to PHP)
    print("📋 Configuration:")
    print(f"   Kafka Brokers: {', '.join(config.kafka.brokers)}")
    print(f"   Client ID: {config.kafka.client_id}")
    print(f"   Group ID: {config.kafka.group_id}")
    print(f"   Consumer Topic: service-1-topic")
    print(f"   Producer Topic: command-center-inbox")
    print(f"   Cassandra Keyspace: {config.cassandra.keyspace}")
    print(f"   Service Name: {service_config['name']}")
    print(f"   Service Version: {service_config['version']}")
    print(f"   Worker Name: {service_config['worker_name']}")
    print()
    
    try:
        # Initialize messaging client
        client = MessagingClient(config)
        await client.initialize()
        
        # Register handlers for different topics
        client.register_handler(
            topic='service-1-topic',  # Consumer topic from config
            handler=handle_dukcapil_request
        )
        
        print("✓ Dukcapil terhubung ke Kafka")
        print(f"✓ Listening on topic: service-1-topic (group: {config.kafka.group_id})")
        print("✓ Producer topic: command-center-inbox")
        print("✓ Siap memverifikasi data kependudukan dan mengirim hasil ke Command Center")
        print("✓ Menunggu pesan dari Command Center...")
        print()
        
        # Start consuming from topics
        topics = ['service-1-topic']
        await client.start_consuming(topics)
        
        print(f"Service 1 ({service_config['name']}) is running and waiting for messages...")
        print("Press Ctrl+C to exit")
        print()
        
        # Keep the listener running
        while True:
            await asyncio.sleep(0.1)  # Similar to PHP usleep(100000)
            
    except KeyboardInterrupt:
        print("\n🔄 Shutting down production listener...")
        await client.close()
        print("✅ Production listener stopped")
    except Exception as e:
        print(f"❌ Fatal error: {e}")
        raise


if __name__ == "__main__":
    # Run the production listener
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n⏹️  Production listener interrupted by user")
    except Exception as e:
        print(f"❌ Production listener error: {e}")
        exit(1)
