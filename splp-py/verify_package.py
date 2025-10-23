#!/usr/bin/env python3
"""KafkaPy Tools - Complete package verification script."""

import sys
import os
import subprocess
import importlib
from pathlib import Path


def check_python_version():
    """Check Python version."""
    print("🐍 Checking Python version...")
    if sys.version_info < (3, 11):
        print(f"❌ Python 3.11+ required, found {sys.version}")
        return False
    print(f"✅ Python {sys.version.split()[0]} detected")
    return True


def check_poetry():
    """Check if Poetry is installed."""
    print("📦 Checking Poetry...")
    try:
        result = subprocess.run(["poetry", "--version"], capture_output=True, text=True)
        if result.returncode == 0:
            print(f"✅ Poetry found: {result.stdout.strip()}")
            return True
        else:
            print("❌ Poetry not found")
            return False
    except FileNotFoundError:
        print("❌ Poetry not found. Please install Poetry first.")
        return False


def check_dependencies():
    """Check if dependencies are installed."""
    print("📚 Checking dependencies...")
    try:
        result = subprocess.run(["poetry", "check"], capture_output=True, text=True)
        if result.returncode == 0:
            print("✅ Dependencies are valid")
            return True
        else:
            print(f"❌ Dependency check failed: {result.stderr}")
            return False
    except Exception as e:
        print(f"❌ Error checking dependencies: {e}")
        return False


def check_package_import():
    """Check if package can be imported."""
    print("📦 Checking package import...")
    try:
        # Add src to path
        src_path = Path(__file__).parent / "src"
        sys.path.insert(0, str(src_path))
        
        import kafkapy_tools
        print(f"✅ Package imported successfully (version: {kafkapy_tools.__version__})")
        
        # Check main components
        from kafkapy_tools import KafkaConfig, KafkaProducerService, KafkaConsumerService
        print("✅ Main components imported successfully")
        
        return True
    except ImportError as e:
        print(f"❌ Package import failed: {e}")
        return False


def check_tests():
    """Check if tests can run."""
    print("🧪 Checking tests...")
    try:
        result = subprocess.run(
            ["poetry", "run", "pytest", "--collect-only", "-q"],
            capture_output=True,
            text=True
        )
        if result.returncode == 0:
            print("✅ Tests can be collected")
            return True
        else:
            print(f"❌ Test collection failed: {result.stderr}")
            return False
    except Exception as e:
        print(f"❌ Error checking tests: {e}")
        return False


def check_linting():
    """Check if linting passes."""
    print("🔍 Checking linting...")
    try:
        result = subprocess.run(
            ["poetry", "run", "ruff", "check", "src/", "--no-fix"],
            capture_output=True,
            text=True
        )
        if result.returncode == 0:
            print("✅ Linting passed")
            return True
        else:
            print(f"⚠️  Linting issues found: {result.stdout}")
            return False
    except Exception as e:
        print(f"❌ Error checking linting: {e}")
        return False


def check_type_checking():
    """Check if type checking passes."""
    print("🔍 Checking type checking...")
    try:
        result = subprocess.run(
            ["poetry", "run", "mypy", "src/"],
            capture_output=True,
            text=True
        )
        if result.returncode == 0:
            print("✅ Type checking passed")
            return True
        else:
            print(f"⚠️  Type checking issues found: {result.stdout}")
            return False
    except Exception as e:
        print(f"❌ Error checking type checking: {e}")
        return False


def check_environment():
    """Check environment setup."""
    print("🌍 Checking environment...")
    
    env_file = Path(__file__).parent / ".env"
    if not env_file.exists():
        print("⚠️  .env file not found. Please copy env.example to .env")
        return False
    
    print("✅ .env file found")
    return True


def main():
    """Main verification function."""
    print("🔍 KafkaPy Tools - Package Verification")
    print("=" * 50)
    
    checks = [
        ("Python Version", check_python_version),
        ("Poetry", check_poetry),
        ("Dependencies", check_dependencies),
        ("Package Import", check_package_import),
        ("Tests", check_tests),
        ("Linting", check_linting),
        ("Type Checking", check_type_checking),
        ("Environment", check_environment),
    ]
    
    passed = 0
    total = len(checks)
    
    for name, check_func in checks:
        print(f"\n{name}:")
        if check_func():
            passed += 1
        else:
            print(f"❌ {name} check failed")
    
    print(f"\n{'='*50}")
    print(f"📊 Verification Summary: {passed}/{total} checks passed")
    
    if passed == total:
        print("🎉 All checks passed! Package is ready to use.")
        print("\nNext steps:")
        print("1. Configure your .env file with Kafka settings")
        print("2. Run 'make test' to run tests")
        print("3. Run 'make run-examples' to see examples")
        return True
    else:
        print("⚠️  Some checks failed. Please fix the issues above.")
        return False


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
