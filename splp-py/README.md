# KafkaPy Tools

Modern Python tools untuk Apache Kafka messaging menggunakan `confluent-kafka` library. Package ini menyediakan interface yang mudah digunakan untuk producer dan consumer Kafka dengan request-reply pattern, enkripsi otomatis, logging ke Cassandra, dan fitur-fitur enterprise-grade.

## 🚀 Fitur Utama

- **MessagingClient**: Request-reply pattern dengan enkripsi otomatis
- **Producer & Consumer Service**: Interface yang mudah digunakan untuk Kafka messaging
- **Encryption/Decryption**: Enkripsi otomatis menggunakan AES-GCM
- **Cassandra Logging**: Logging terstruktur ke Cassandra database
- **Circuit Breaker**: Pattern untuk resilience dan fault tolerance
- **Retry Manager**: Exponential backoff dengan jitter
- **Konfigurasi Dinamis**: Load konfigurasi dari environment variables atau file `.env`
- **CLI Tools**: Command line interface untuk testing dan debugging
- **Type Safety**: Full type hints dengan Pydantic models
- **Testing**: Comprehensive test suite dengan pytest
- **Modern Python**: Python 3.11+ dengan Poetry untuk dependency management

## 📦 Instalasi

### Menggunakan Poetry (Recommended)

```bash
# Clone repository
git clone <repository-url>
cd splp-py

# Install dependencies
poetry install

# Activate virtual environment
poetry shell
```

### Menggunakan pip

```bash
pip install kafkapy-tools
```

## ⚙️ Konfigurasi

### Environment Variables

Copy file `env.example` ke `.env` dan sesuaikan konfigurasi:

```bash
cp env.example .env
```

```env
# Kafka Configuration
KAFKA_BOOTSTRAP_SERVERS=localhost:9092
KAFKA_SECURITY_PROTOCOL=PLAINTEXT
KAFKA_SASL_MECHANISM=PLAIN
KAFKA_SASL_USERNAME=
KAFKA_SASL_PASSWORD=

# Producer Configuration
KAFKA_PRODUCER_TOPIC=test-topic
KAFKA_PRODUCER_ACKS=all
KAFKA_PRODUCER_RETRIES=3

# Consumer Configuration
KAFKA_CONSUMER_TOPIC=test-topic
KAFKA_CONSUMER_GROUP_ID=test-group
KAFKA_CONSUMER_AUTO_OFFSET_RESET=earliest

# Logging Configuration
LOG_LEVEL=INFO
LOG_FORMAT=json
LOG_FILE=kafkapy_tools.log
```

### Programmatic Configuration

```python
from kafkapy_tools import KafkaConfig, KafkaProducerService, KafkaConsumerService

# Load dari environment variables
config = KafkaConfig.from_env()

# Atau buat konfigurasi manual
config = KafkaConfig(
    bootstrap_servers="localhost:9092",
    producer_topic="my-topic",
    consumer_topic="my-topic",
    consumer_group_id="my-group"
)
```

## 🔧 Penggunaan

### MessagingClient (Request-Reply Pattern)

```python
import asyncio
from kafkapy_tools import (
    MessagingClient,
    MessagingConfig,
    KafkaConfig,
    CassandraConfig,
    EncryptionConfig,
    generate_encryption_key,
)

# Configuration
config = MessagingConfig(
    kafka=KafkaConfig(
        brokers=["localhost:9092"],
        client_id="my-client",
        group_id="my-group",
    ),
    cassandra=CassandraConfig(
        contact_points=["localhost"],
        local_data_center="datacenter1",
        keyspace="messaging",
    ),
    encryption=EncryptionConfig(
        encryption_key=generate_encryption_key(),
    ),
)

async def main():
    # Initialize client
    client = MessagingClient(config)
    await client.initialize()
    
    # Send request with automatic encryption
    response = await client.request(
        topic="calculate",
        payload={"operation": "add", "a": 10, "b": 5},
        timeout_ms=30000
    )
    
    print(f"Result: {response['result']}")
    
    await client.close()

# Run
asyncio.run(main())
```

### Worker (Message Handler)

```python
import asyncio
from kafkapy_tools import MessagingClient, MessagingConfig

async def main():
    client = MessagingClient(config)
    await client.initialize()
    
    # Register handler
    client.register_handler(
        topic="calculate",
        handler=async def calculate_handler(request_id: str, payload: dict):
            operation = payload["operation"]
            a = payload["a"]
            b = payload["b"]
            
            if operation == "add":
                result = a + b
            elif operation == "subtract":
                result = a - b
            # ... other operations
            
            return {"result": result, "operation": operation}
    )
    
    # Start consuming
    await client.start_consuming(["calculate"])
    
    # Keep running
    while True:
        await asyncio.sleep(1)

asyncio.run(main())
```

### Basic Producer/Consumer

```python
from kafkapy_tools import KafkaProducerService, KafkaConsumerService, KafkaConfig

# Load konfigurasi
config = KafkaConfig.from_env()

# Producer
with KafkaProducerService(config) as producer:
    producer.send_message(
        message={"id": "123", "data": "Hello Kafka!"},
        key="message-key"
    )

# Consumer
with KafkaConsumerService(config) as consumer:
    def message_handler(message):
        print(f"Received: {message['value']}")
    
    consumer.consume_messages(message_handler=message_handler)
```

### Batch Consumption

```python
# Consume dalam batch
messages = consumer.consume_batch(batch_size=10)
for message in messages:
    process_message(message)
```

### Custom Serializers

```python
def custom_key_serializer(key):
    return f"prefix-{key}".encode()

def custom_value_serializer(value):
    return json.dumps(value, ensure_ascii=False).encode()

producer = KafkaProducerService(
    config,
    key_serializer=custom_key_serializer,
    value_serializer=custom_value_serializer
)
```

### Avro Support

```python
# Producer dengan Avro
producer = KafkaProducerService(
    config,
    schema_registry_url="http://localhost:8081",
    avro_schema='{"type": "record", "name": "User", "fields": [{"name": "name", "type": "string"}]}'
)

# Consumer dengan Avro
consumer = KafkaConsumerService(
    config,
    schema_registry_url="http://localhost:8081",
    avro_schema='{"type": "record", "name": "User", "fields": [{"name": "name", "type": "string"}]}'
)
```

## 🖥️ CLI Tools

### Producer CLI

```bash
# Kirim single message
kafkapy-producer --message "Hello Kafka!" --topic my-topic

# Kirim dengan key dan headers
kafkapy-producer \
  --message '{"id": "123", "data": "test"}' \
  --key "message-key" \
  --headers '{"source": "cli"}' \
  --topic my-topic

# Kirim batch messages
kafkapy-producer \
  --message "Batch message" \
  --batch 10 \
  --interval 0.5 \
  --topic my-topic

# Gunakan konfigurasi custom
kafkapy-producer \
  --config custom.env \
  --message "Custom config message"
```

### Consumer CLI

```bash
# Consume dari topic default
kafkapy-consumer

# Consume dari multiple topics
kafkapy-consumer --topics topic1 topic2 topic3

# Consume dengan limit
kafkapy-consumer --max-messages 100

# Consume dalam batch
kafkapy-consumer --batch-size 10

# Output format
kafkapy-consumer --output-format json
kafkapy-consumer --output-format compact

# Seek operations
kafkapy-consumer --seek-beginning
kafkapy-consumer --seek-end
```

## 📊 Logging

Package ini menggunakan structured JSON logging:

```json
{
  "timestamp": "2024-01-15T10:30:00.000Z",
  "level": "INFO",
  "logger": "kafkapy_tools.producer",
  "message": "Message sent to topic my-topic",
  "module": "producer",
  "function": "send_message",
  "line": 123,
  "extra_fields": {
    "topic": "my-topic",
    "key": "message-key",
    "partition": 0
  }
}
```

### Setup Logging

```python
from kafkapy_tools import setup_logging

# Setup dengan konfigurasi default
logger = setup_logging()

# Setup dengan konfigurasi custom
logger = setup_logging(
    level="DEBUG",
    format_type="json",
    log_file="custom.log"
)
```

## 🧪 Testing

```bash
# Run semua tests
poetry run pytest

# Run dengan coverage
poetry run pytest --cov=kafkapy_tools --cov-report=html

# Run tests dengan verbose output
poetry run pytest -v

# Run specific test file
poetry run pytest tests/test_producer.py
```

## 🔧 Development

### Setup Development Environment

```bash
# Install dependencies termasuk dev dependencies
poetry install

# Install pre-commit hooks
poetry run pre-commit install

# Run linting
poetry run ruff check .
poetry run black .

# Run type checking
poetry run mypy src/
```

### Project Structure

```
splp-py/
├── src/
│   └── kafkapy_tools/
│       ├── __init__.py
│       ├── __version__.py
│       ├── config.py          # Konfigurasi management
│       ├── producer.py        # Producer service
│       ├── consumer.py        # Consumer service
│       ├── logging_config.py # Logging setup
│       └── cli.py            # CLI tools
├── tests/
│   ├── __init__.py
│   ├── test_config.py
│   ├── test_producer.py
│   └── test_consumer.py
├── pyproject.toml            # Poetry configuration
├── env.example               # Environment template
└── README.md
```

## 📋 Requirements

- Python 3.11+
- Apache Kafka cluster
- confluent-kafka library
- python-dotenv untuk environment variables
- pydantic untuk data validation

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

Distributed under the MIT License. See `LICENSE` for more information.

## 🆘 Support

Jika Anda mengalami masalah atau memiliki pertanyaan:

1. Check [Issues](https://github.com/your-repo/issues) untuk solusi yang sudah ada
2. Buat issue baru dengan detail yang lengkap
3. Untuk pertanyaan umum, gunakan [Discussions](https://github.com/your-repo/discussions)

## 🔄 Changelog

### v0.1.0 (2024-01-15)
- Initial release
- Producer dan Consumer services
- CLI tools
- JSON logging
- Avro support
- Comprehensive test suite
