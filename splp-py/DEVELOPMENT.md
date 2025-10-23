# KafkaPy Tools - Development Scripts

## Setup Development Environment

```bash
# Install Poetry if not already installed
curl -sSL https://install.python-poetry.org | python3 -

# Install dependencies
poetry install

# Install pre-commit hooks
poetry run pre-commit install

# Activate virtual environment
poetry shell
```

## Development Commands

### Code Quality
```bash
# Format code
poetry run black .

# Lint code
poetry run ruff check .

# Type checking
poetry run mypy src/

# Run all quality checks
poetry run pre-commit run --all-files
```

### Testing
```bash
# Run tests
poetry run pytest

# Run with coverage
poetry run pytest --cov=kafkapy_tools --cov-report=html

# Run specific test
poetry run pytest tests/test_producer.py -v
```

### Building
```bash
# Build package
poetry build

# Check package
poetry run twine check dist/*
```

### Publishing
```bash
# Publish to PyPI (test)
poetry run twine upload --repository testpypi dist/*

# Publish to PyPI (production)
poetry run twine upload dist/*
```

## Project Structure

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
└── LICENSE                   # MIT License
```

## Environment Variables

See `env.example` for all available configuration options.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Run code quality checks
7. Submit a pull request

## Release Process

1. Update version in `src/kafkapy_tools/__version__.py`
2. Update `pyproject.toml` version
3. Update `CHANGELOG.md` (if exists)
4. Run tests and quality checks
5. Build package: `poetry build`
6. Publish: `poetry publish`
