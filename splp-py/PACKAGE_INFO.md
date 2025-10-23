# KafkaPy Tools - Package Information

## 📦 Package Details

- **Name**: kafkapy-tools
- **Version**: 0.1.0
- **Python Version**: 3.11+
- **License**: MIT
- **Author**: Your Name
- **Email**: your.email@example.com

## 🚀 Features

- Modern Python Kafka client using confluent-kafka
- Producer and Consumer services with advanced features
- Dynamic configuration from environment variables
- Structured JSON logging
- Avro schema support
- CLI tools for testing and debugging
- Comprehensive test suite
- Type safety with Pydantic models

## 📁 Project Structure

```
splp-py/
├── src/kafkapy_tools/          # Main package
│   ├── __init__.py            # Package exports
│   ├── __version__.py         # Version info
│   ├── config.py              # Configuration management
│   ├── producer.py            # Producer service
│   ├── consumer.py            # Consumer service
│   ├── logging_config.py      # Logging setup
│   └── cli.py                 # CLI tools
├── tests/                     # Test suite
│   ├── __init__.py
│   ├── test_config.py
│   ├── test_producer.py
│   └── test_consumer.py
├── examples/                  # Usage examples
│   ├── basic_example.py
│   └── advanced_example.py
├── pyproject.toml            # Poetry configuration
├── .pre-commit-config.yaml   # Pre-commit hooks
├── .gitignore                # Git ignore rules
├── env.example               # Environment template
├── README.md                 # Main documentation
├── QUICK_START.md            # Quick start guide
├── DEVELOPMENT.md            # Development guide
├── LICENSE                   # MIT License
├── Makefile                  # Development commands
├── run_examples.py           # Example runner
├── cli_interactive.py        # Interactive CLI
└── verify_package.py         # Package verification
```

## 🔧 Dependencies

### Production Dependencies
- confluent-kafka: ^2.3.0
- python-dotenv: ^1.0.0
- pydantic: ^2.5.0

### Development Dependencies
- pytest: ^7.4.0
- pytest-asyncio: ^0.21.0
- pytest-cov: ^4.1.0
- ruff: ^0.1.0
- black: ^23.0.0
- mypy: ^1.7.0

## 📋 Installation

```bash
# Using Poetry (recommended)
poetry install

# Using pip
pip install -e .
```

## 🚀 Quick Start

```bash
# Setup environment
cp env.example .env
# Edit .env with your Kafka configuration

# Run examples
python examples/basic_example.py

# Use CLI
python -m kafkapy_tools.cli producer --message "Hello Kafka!"
python -m kafkapy_tools.cli consumer --topics my-topic
```

## 🧪 Testing

```bash
# Run tests
poetry run pytest

# Run with coverage
poetry run pytest --cov=kafkapy_tools --cov-report=html
```

## 🔍 Code Quality

```bash
# Format code
poetry run black .

# Lint code
poetry run ruff check .

# Type checking
poetry run mypy src/
```

## 📦 Building

```bash
# Build package
poetry build

# Check package
poetry run twine check dist/*
```

## 🚀 Publishing

```bash
# Publish to test PyPI
poetry run twine upload --repository testpypi dist/*

# Publish to PyPI
poetry run twine upload dist/*
```

## 📚 Documentation

- [README.md](README.md) - Complete documentation
- [QUICK_START.md](QUICK_START.md) - Quick start guide
- [DEVELOPMENT.md](DEVELOPMENT.md) - Development guide
- [Examples](examples/) - Usage examples

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Run code quality checks
7. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

If you encounter any issues or have questions:

1. Check the [Issues](https://github.com/your-repo/issues) for existing solutions
2. Create a new issue with detailed information
3. For general questions, use [Discussions](https://github.com/your-repo/discussions)

## 🔄 Changelog

### v0.1.0 (2024-01-15)
- Initial release
- Producer and Consumer services
- CLI tools
- JSON logging
- Avro support
- Comprehensive test suite
- Modern Python 3.11+ support
- Poetry for dependency management
- Pre-commit hooks for code quality
- Complete documentation and examples
