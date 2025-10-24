# KafkaPy Tools - Quick Start Guide

## 🚀 Quick Setup

### 1. Install Dependencies

```bash
# Clone repository (jika belum)
git clone <repository-url>
cd splp-py

# Using Poetry (recommended)
# Install Poetry terlebih dahulu jika belum ada:
python3 -m pip install --user poetry
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc

# Install dependencies dengan Poetry
poetry install

# Activate virtual environment
poetry shell

# Or using pip untuk development
python3 -m pip install -e .

# Atau install dari PyPI (jika sudah published)
python3 -m pip install kafkapy-tools
```

**Note:** Jika command `pip` tidak ditemukan, gunakan `python3 -m pip` sebagai gantinya.

### 2. Setup Environment

```bash
# Copy environment template
cp env.example .env

# Edit .env file with your Kafka and Cassandra configuration
nano .env
```

**Environment Variables yang Perlu Dikonfigurasi:**

```env
# Kafka Configuration
KAFKA_BOOTSTRAP_SERVERS=localhost:9092
KAFKA_CLIENT_ID=kafkapy-tools
KAFKA_GROUP_ID=kafkapy-group
KAFKA_SECURITY_PROTOCOL=PLAINTEXT

# Producer Configuration
KAFKA_PRODUCER_TOPIC=test-topic
KAFKA_PRODUCER_ACKS=all
KAFKA_PRODUCER_RETRIES=3

# Consumer Configuration
KAFKA_CONSUMER_TOPIC=test-topic
KAFKA_CONSUMER_GROUP_ID=test-group
KAFKA_CONSUMER_AUTO_OFFSET_RESET=earliest

# Cassandra Configuration (untuk logging)
CASSANDRA_CONTACT_POINTS=localhost
CASSANDRA_LOCAL_DATA_CENTER=datacenter1
CASSANDRA_KEYSPACE=messaging

# Encryption Configuration
ENCRYPTION_KEY=your-32-byte-hex-encryption-key-here

# Logging Configuration
LOG_LEVEL=INFO
LOG_FORMAT=json
LOG_FILE=kafkapy_tools.log
```

### 3. Basic Usage

#### MessagingClient (Request-Reply Pattern)
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

# Configuration - Load dari environment variables
config = MessagingConfig.from_env()

# Atau buat konfigurasi manual
config = MessagingConfig(
    kafka=KafkaConfig(
        brokers=["localhost:9092"],
        client_id="my-client",
        group_id="my-group",
        security_protocol="PLAINTEXT",
    ),
    cassandra=CassandraConfig(
        contact_points=["localhost"],
        local_data_center="datacenter1",
        keyspace="messaging",
    ),
    encryption=EncryptionConfig(
        encryption_key=generate_encryption_key(),  # Generate key baru
        # Atau gunakan key yang sudah ada:
        # encryption_key="your-32-byte-hex-key-here"
    ),
)

async def main():
    # Initialize client
    client = MessagingClient(config)
    await client.initialize()
    
    try:
        # Send request with automatic encryption
        response = await client.request(
            topic="calculate",
            payload={"operation": "add", "a": 10, "b": 5},
            timeout_ms=30000
        )
        
        print(f"Result: {response['result']}")
        
    except Exception as e:
        print(f"Request failed: {e}")
    
    finally:
        await client.close()

# Run
asyncio.run(main())
```

#### Worker (Message Handler)
```python
import asyncio
from kafkapy_tools import MessagingClient, MessagingConfig

# Load configuration dari environment
config = MessagingConfig.from_env()

async def calculate_handler(request_id: str, payload: dict):
    """Handler untuk operasi kalkulasi."""
    operation = payload["operation"]
    a = payload["a"]
    b = payload["b"]
    
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
    
    return {"result": result, "operation": operation}

async def main():
    client = MessagingClient(config)
    await client.initialize()
    
    try:
        # Register handler
        client.register_handler(
            topic="calculate",
            handler=calculate_handler
        )
        
        # Start consuming
        await client.start_consuming(["calculate"])
        
        print("Worker started. Listening for messages...")
        
        # Keep running
        while True:
            await asyncio.sleep(1)
            
    except KeyboardInterrupt:
        print("Worker stopped by user")
    except Exception as e:
        print(f"Worker error: {e}")
    finally:
        await client.close()

asyncio.run(main())
```

#### Basic Producer/Consumer
```python
from kafkapy_tools import KafkaProducerService, KafkaConsumerService, KafkaConfig

# Load configuration dari environment
config = KafkaConfig.from_env()

# Producer
with KafkaProducerService(config) as producer:
    producer.send_message(
        message={"id": "123", "data": "Hello Kafka!"},
        key="message-key"
    )
    print("Message sent successfully")

# Consumer
with KafkaConsumerService(config) as consumer:
    def message_handler(message):
        print(f"Received: {message['value']}")
        print(f"Key: {message['key']}")
        print(f"Headers: {message['headers']}")
    
    consumer.consume_messages(message_handler=message_handler)
```

### 4. Available Examples

Package ini menyediakan berbagai contoh penggunaan:

```bash
# Basic example - Producer dan Consumer sederhana
python examples/basic_example.py

# Advanced example - Fitur-fitur lanjutan
python examples/advanced_example.py

# Client example - Request-Reply pattern (Client side)
python examples/client_example.py

# Worker example - Request-Reply pattern (Server side)
python examples/worker_example.py

# Production examples
python examples/production_publisher.py
python examples/production_listener.py

# Run interactive examples
python run_examples.py
```

**Production Listener:**
```bash
# Setup environment
cp env.production .env

# Run production listener
poetry run python examples/production_listener.py

# Run in background (production)
nohup poetry run python examples/production_listener.py > listener.log 2>&1 &
```
```

**Interactive Examples Runner:**
```bash
python run_examples.py
```
Script ini akan memberikan pilihan untuk menjalankan:
1. Worker (Message Handler)
2. Client (Request Sender)  
3. Both (Worker di background, lalu Client)
4. Exit

### 5. CLI Usage

Package ini menyediakan command line tools untuk testing dan debugging:

#### Producer CLI
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

#### Consumer CLI
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

### 6. Testing

```bash
# Run semua tests
poetry run pytest

# Run dengan coverage
poetry run pytest --cov=kafkapy_tools --cov-report=html

# Run tests dengan verbose output
poetry run pytest -v

# Run specific test file
poetry run pytest tests/test_messaging_client.py

# Run tests dengan asyncio support
poetry run pytest tests/test_async_features.py
```

### 7. Development Setup

```bash
# Install dependencies termasuk dev dependencies
poetry install

# Install pre-commit hooks (jika ada)
poetry run pre-commit install

# Run linting
poetry run ruff check .
poetry run black .

# Run type checking
poetry run mypy src/

# Format code
poetry run black src/ tests/
poetry run ruff check --fix src/ tests/
```

### 8. Prerequisites

- **Python 3.11+** - Versi Python yang didukung
- **Apache Kafka cluster** - Kafka broker yang berjalan
- **Cassandra database** - Untuk logging (opsional)
- **Poetry** - Untuk dependency management (recommended)
- **Git** - Untuk version control

### 9. Troubleshooting

#### Common Issues

**1. Connection Error ke Kafka:**
```bash
# Pastikan Kafka broker berjalan
docker-compose up -d kafka

# Check koneksi
telnet localhost 9092
```

**2. Environment Variables tidak terbaca:**
```bash
# Pastikan file .env ada dan format benar
ls -la .env
cat .env
```

**3. Import Error:**
```bash
# Pastikan package terinstall
poetry install
# atau
python3 -m pip install -e .
```

**4. pip command not found (macOS):**
```bash
# Gunakan python3 -m pip sebagai gantinya
python3 -m pip install package_name

# Atau buat alias
echo 'alias pip="python3 -m pip"' >> ~/.zshrc
source ~/.zshrc
```

**5. Cassandra Connection Error:**
```bash
# Pastikan Cassandra berjalan
docker-compose up -d cassandra

# Check koneksi
telnet localhost 9042
```

**6. Python Version Error:**
```bash
# Jika ada error "Python version 3.9.6 is not supported by the project (^3.11)"
# Atau warning "urllib3 v2 only supports OpenSSL 1.1.1+, currently the 'ssl' module is compiled with 'LibreSSL 2.8.3'"
# Project ini memerlukan Python 3.11 atau lebih tinggi

# Upgrade Python menggunakan Homebrew
brew install python@3.11

# Set sebagai default
echo 'export PATH="/opt/homebrew/bin:$PATH"' >> ~/.zshrc
echo 'alias python="python3.11"' >> ~/.zshrc
echo 'alias python3="python3.11"' >> ~/.zshrc
source ~/.zshrc

# Atau menggunakan pyenv
brew install pyenv
pyenv install 3.11.7
pyenv global 3.11.7

# Set Poetry untuk menggunakan Python 3.11
poetry env use python3.11

# Reinstall dependencies
poetry install

# Verifikasi
python --version
poetry run python --version
```

**7. ModuleNotFoundError:**
```bash
# Jika ada error "ModuleNotFoundError: No module named 'kafkapy_tools'"
# Package belum terinstall dengan benar

# Install package dalam development mode
poetry install

# Aktifkan virtual environment
poetry shell

# Atau jalankan dengan poetry run
poetry run python examples/production_listener.py

# Test import package
poetry run python -c "import kafkapy_tools; print('✅ Package installed')"

# Jika masih error, reinstall
poetry install --no-dev
```

**8. Type Hint Error:**
```bash
# Jika ada error "TypeError: typing.Callable is not a generic class"
# Type hint RequestHandler[TRequest, TResponse] tidak valid

# Test import package
poetry run python -c "from kafkapy_tools import MessagingClient; print('✅ MessagingClient imported')"

# Test type hints
poetry run python -c "from kafkapy_tools.types import RequestHandler; print('✅ RequestHandler imported')"

# Jika masih error, reinstall
poetry install
```

**9. Kafka Configuration Error:**
```bash
# Jika ada error "buffer.memory", "max.poll.records", "fetch.max.wait.ms", atau "Failed to set subscription"
# Property ini tidak valid untuk confluent-kafka Python client
# Gunakan konfigurasi yang valid berdasarkan splp-php:

# Test konfigurasi
poetry run python -c "
from kafkapy_tools.config import KafkaConfig
config = KafkaConfig(bootstrap_servers='10.70.1.23:9092', consumer_group_id='service-1a-group')
print('Producer config:', config.get_producer_config())
print('Consumer config:', config.get_consumer_config())
"

# Property yang TIDAK VALID untuk Python client:
# - buffer.memory
# - max.poll.records  
# - fetch.max.wait.ms
# - max.partition.fetch.bytes
# - fetch.min.bytes

# Property yang VALID (berdasarkan splp-php):
# - bootstrap.servers
# - group.id
# - client.id
# - auto.offset.reset
# - enable.auto.commit
# - auto.commit.interval.ms
# - session.timeout.ms
# - heartbeat.interval.ms
# - acks
# - retries
# - retry.backoff.ms
# - message.timeout.ms

# Test koneksi ke Kafka
telnet 10.70.1.23 9092

# Test topic
kafka-topics.sh --bootstrap-server 10.70.1.23:9092 --list

# Gunakan konfigurasi berdasarkan splp-php dengan Kafka 10.70.1.23:9092
KAFKA_BOOTSTRAP_SERVERS=10.70.1.23:9092 \
KAFKA_CLIENT_ID=dukcapil-service \
KAFKA_GROUP_ID=service-1a-group \
poetry run python examples/production_listener.py

# Jika masih error, coba dengan localhost
KAFKA_BOOTSTRAP_SERVERS=localhost:9092 \
KAFKA_CLIENT_ID=test-client \
KAFKA_GROUP_ID=test-group \
poetry run python examples/production_listener.py
```

#### Debug Mode

```python
# Enable debug logging
import logging
logging.basicConfig(level=logging.DEBUG)

# Atau gunakan environment variable
# LOG_LEVEL=DEBUG
```

### 10. Next Steps

Setelah berhasil setup, Anda bisa:

1. **Explore Examples** - Jalankan berbagai contoh di folder `examples/`
2. **Read Full Documentation** - Lihat `README.md` untuk dokumentasi lengkap
3. **Customize Configuration** - Sesuaikan konfigurasi sesuai kebutuhan
4. **Build Your Application** - Gunakan MessagingClient untuk aplikasi Anda
5. **Contribute** - Fork repository dan submit pull request

## 📚 Documentation

Lihat `README.md` untuk dokumentasi lengkap dan contoh-contoh lebih detail.

## 🆘 Support

Jika mengalami masalah:
1. Check [Issues](https://github.com/your-repo/issues) untuk solusi yang sudah ada
2. Buat issue baru dengan detail yang lengkap
3. Untuk pertanyaan umum, gunakan [Discussions](https://github.com/your-repo/discussions)