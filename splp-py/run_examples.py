#!/usr/bin/env python3
"""Run all KafkaPy Tools examples."""

import sys
import os
import subprocess
from pathlib import Path


def run_example(example_file: str, description: str) -> bool:
    """Run an example script."""
    print(f"\n{'='*60}")
    print(f"🎯 Running: {description}")
    print(f"📁 File: {example_file}")
    print(f"{'='*60}")
    
    try:
        result = subprocess.run(
            [sys.executable, example_file],
            cwd=Path(__file__).parent,
            capture_output=False,
            text=True,
            timeout=30
        )
        
        if result.returncode == 0:
            print(f"✅ {description} completed successfully")
            return True
        else:
            print(f"❌ {description} failed with return code {result.returncode}")
            return False
            
    except subprocess.TimeoutExpired:
        print(f"⏰ {description} timed out after 30 seconds")
        return False
    except Exception as e:
        print(f"❌ Error running {description}: {e}")
        return False


def check_environment() -> bool:
    """Check if environment is properly set up."""
    print("🔍 Checking environment setup...")
    
    # Check if .env file exists
    env_file = Path(__file__).parent / ".env"
    if not env_file.exists():
        print("⚠️  .env file not found. Please copy env.example to .env and configure it.")
        return False
    
    # Check if package is installed
    try:
        import kafkapy_tools
        print(f"✅ kafkapy_tools package found (version: {kafkapy_tools.__version__})")
    except ImportError:
        print("❌ kafkapy_tools package not found. Please install it first:")
        print("   poetry install")
        return False
    
    print("✅ Environment check passed")
    return True


def main():
    """Main function to run all examples."""
    print("🚀 KafkaPy Tools - Example Runner")
    print("=" * 60)
    
    # Check environment
    if not check_environment():
        print("\n❌ Environment check failed. Please fix the issues above.")
        sys.exit(1)
    
    # Define examples to run
    examples = [
        ("examples/basic_example.py", "Basic Usage Example"),
        ("examples/advanced_example.py", "Advanced Usage Example"),
    ]
    
    # Run examples
    success_count = 0
    total_count = len(examples)
    
    for example_file, description in examples:
        if run_example(example_file, description):
            success_count += 1
    
    # Summary
    print(f"\n{'='*60}")
    print(f"📊 Summary: {success_count}/{total_count} examples completed successfully")
    
    if success_count == total_count:
        print("🎉 All examples completed successfully!")
        sys.exit(0)
    else:
        print("⚠️  Some examples failed. Check the output above for details.")
        sys.exit(1)


if __name__ == "__main__":
    main()
