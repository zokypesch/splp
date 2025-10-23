# KafkaPy Tools - Quick Start Guide

## 🚀 Quick Setup

### 1. Install Dependencies

```bash
# Using Poetry (recommended)
poetry install

# Or using pip
pip install -e .
```

### 2. Setup Environment

```bash
# Copy environment template
cp env.example .env

# Edit .env file with your Kafka configuration
nano .env
```

### 3. Basic Usage

#### Producer
```python
from kafkapy_tools import KafkaConfig, KafkaProducerService

config = KafkaConfig.from_env()
with KafkaProducerService(config) as producer:
    producer.send_message({"message": "Hello Kafka!"}, key="test-key")
```

#### Consumer
```python
from kafkapy_tools import KafkaConsumerService

with KafkaConsumerService(config) as consumer:
    def handler(msg):
        print(f"Received: {msg['value']}")
    
    consumer.consume_messages(handler)
```

### 4. CLI Usage

```bash
# Send message
kafkapy-producer --message "Hello!" --topic my-topic

# Consume messages
kafkapy-consumer --topics my-topic --max-messages 10
```

### 5. Run Examples

```bash
# Basic example
python examples/basic_example.py

# Advanced example
python examples/advanced_example.py
```

### 6. Testing

```bash
# Run tests
poetry run pytest

# Run with coverage
poetry run pytest --cov=kafkapy_tools --cov-report=html
```

## 📋 Prerequisites

- Python 3.11+
- Apache Kafka cluster running
- Poetry (for development)

## 🔧 Development

```bash
# Install dev dependencies
poetry install

# Run linting
poetry run ruff check .
poetry run black .

# Run type checking
poetry run mypy src/
```

## 📚 Documentation

See `README.md` for complete documentation and examples.
