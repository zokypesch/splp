# KafkaPy Tools - Makefile

.PHONY: help install dev-install test lint format type-check clean build publish run-examples

help: ## Show this help message
	@echo "KafkaPy Tools - Available commands:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install package dependencies
	poetry install

dev-install: ## Install development dependencies
	poetry install --with dev
	poetry run pre-commit install

test: ## Run tests
	poetry run pytest

test-cov: ## Run tests with coverage
	poetry run pytest --cov=kafkapy_tools --cov-report=html --cov-report=term

lint: ## Run linting
	poetry run ruff check .

format: ## Format code
	poetry run black .
	poetry run ruff check --fix .

type-check: ## Run type checking
	poetry run mypy src/

quality: lint type-check ## Run all quality checks

clean: ## Clean build artifacts
	rm -rf dist/
	rm -rf build/
	rm -rf *.egg-info/
	rm -rf .pytest_cache/
	rm -rf .coverage
	rm -rf htmlcov/
	find . -type d -name __pycache__ -exec rm -rf {} +
	find . -type f -name "*.pyc" -delete

build: clean ## Build package
	poetry build

publish-test: build ## Publish to test PyPI
	poetry run twine upload --repository testpypi dist/*

publish: build ## Publish to PyPI
	poetry run twine upload dist/*

run-examples: ## Run all examples
	python run_examples.py

setup-env: ## Setup environment file
	cp env.example .env
	@echo "Please edit .env file with your Kafka configuration"

check-env: ## Check environment setup
	@if [ ! -f .env ]; then \
		echo "❌ .env file not found. Run 'make setup-env' first."; \
		exit 1; \
	fi
	@echo "✅ .env file found"

dev-setup: setup-env dev-install ## Complete development setup
	@echo "🎉 Development environment setup complete!"
	@echo "Next steps:"
	@echo "1. Edit .env file with your Kafka configuration"
	@echo "2. Run 'make test' to verify everything works"
	@echo "3. Run 'make run-examples' to see examples"

ci: quality test ## Run CI pipeline (linting, type checking, tests)

all: dev-setup quality test build ## Run everything (setup, quality checks, tests, build)
