#!/usr/bin/env python3
"""KafkaPy Tools - Complete package setup script."""

import sys
import os
import subprocess
import shutil
from pathlib import Path


def run_command(cmd, description):
    """Run a command and return success status."""
    print(f"🔄 {description}...")
    try:
        result = subprocess.run(cmd, shell=True, check=True, capture_output=True, text=True)
        print(f"✅ {description} completed")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ {description} failed: {e}")
        if e.stdout:
            print(f"   stdout: {e.stdout}")
        if e.stderr:
            print(f"   stderr: {e.stderr}")
        return False


def check_requirements():
    """Check if requirements are met."""
    print("🔍 Checking requirements...")
    
    # Check Python version
    if sys.version_info < (3, 11):
        print(f"❌ Python 3.11+ required, found {sys.version}")
        return False
    print(f"✅ Python {sys.version.split()[0]} detected")
    
    # Check Poetry
    try:
        result = subprocess.run(["poetry", "--version"], capture_output=True, text=True)
        if result.returncode == 0:
            print(f"✅ Poetry found: {result.stdout.strip()}")
        else:
            print("❌ Poetry not found. Please install Poetry first.")
            return False
    except FileNotFoundError:
        print("❌ Poetry not found. Please install Poetry first.")
        return False
    
    return True


def setup_environment():
    """Setup environment file."""
    print("🌍 Setting up environment...")
    
    env_file = Path(__file__).parent / ".env"
    env_example = Path(__file__).parent / "env.example"
    
    if not env_file.exists():
        if env_example.exists():
            shutil.copy(env_example, env_file)
            print("✅ Created .env file from env.example")
        else:
            print("❌ env.example not found")
            return False
    else:
        print("✅ .env file already exists")
    
    return True


def install_dependencies():
    """Install dependencies."""
    print("📦 Installing dependencies...")
    
    # Install dependencies
    if not run_command("poetry install", "Installing dependencies"):
        return False
    
    # Install pre-commit hooks
    if not run_command("poetry run pre-commit install", "Installing pre-commit hooks"):
        return False
    
    return True


def run_tests():
    """Run tests."""
    print("🧪 Running tests...")
    
    if not run_command("poetry run pytest", "Running tests"):
        return False
    
    return True


def run_quality_checks():
    """Run quality checks."""
    print("🔍 Running quality checks...")
    
    # Format code
    if not run_command("poetry run black .", "Formatting code"):
        return False
    
    # Lint code
    if not run_command("poetry run ruff check .", "Linting code"):
        return False
    
    # Type checking
    if not run_command("poetry run mypy src/", "Type checking"):
        return False
    
    return True


def build_package():
    """Build package."""
    print("📦 Building package...")
    
    if not run_command("poetry build", "Building package"):
        return False
    
    return True


def main():
    """Main setup function."""
    print("🚀 KafkaPy Tools - Complete Package Setup")
    print("=" * 50)
    
    steps = [
        ("Requirements Check", check_requirements),
        ("Environment Setup", setup_environment),
        ("Dependencies Installation", install_dependencies),
        ("Quality Checks", run_quality_checks),
        ("Tests", run_tests),
        ("Package Build", build_package),
    ]
    
    passed = 0
    total = len(steps)
    
    for name, step_func in steps:
        print(f"\n{name}:")
        if step_func():
            passed += 1
        else:
            print(f"❌ {name} failed")
            break
    
    print(f"\n{'='*50}")
    print(f"📊 Setup Summary: {passed}/{total} steps completed")
    
    if passed == total:
        print("🎉 Package setup completed successfully!")
        print("\nNext steps:")
        print("1. Edit .env file with your Kafka and Cassandra configuration")
        print("2. Run 'python run_examples.py' to see examples")
        print("3. Run 'python verify_package.py' to verify everything works")
        print("4. Run 'make help' to see available commands")
        return True
    else:
        print("⚠️  Setup failed. Please fix the issues above.")
        return False


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)