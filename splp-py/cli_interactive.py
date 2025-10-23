#!/usr/bin/env python3
"""KafkaPy Tools CLI - Interactive command runner."""

import argparse
import sys
import os
from pathlib import Path


def run_producer_cli():
    """Run producer CLI with interactive prompts."""
    print("🚀 KafkaPy Tools - Producer CLI")
    print("=" * 40)
    
    # Get message
    message = input("Enter message to send: ").strip()
    if not message:
        print("❌ Message cannot be empty")
        return False
    
    # Get optional parameters
    topic = input("Enter topic (press Enter for default): ").strip()
    key = input("Enter key (press Enter for no key): ").strip()
    headers = input("Enter headers as JSON (press Enter for no headers): ").strip()
    
    # Build command
    cmd = [sys.executable, "-m", "kafkapy_tools.cli", "producer", "--message", message]
    
    if topic:
        cmd.extend(["--topic", topic])
    
    if key:
        cmd.extend(["--key", key])
    
    if headers:
        cmd.extend(["--headers", headers])
    
    # Run command
    try:
        import subprocess
        result = subprocess.run(cmd, cwd=Path(__file__).parent)
        return result.returncode == 0
    except Exception as e:
        print(f"❌ Error running producer: {e}")
        return False


def run_consumer_cli():
    """Run consumer CLI with interactive prompts."""
    print("📥 KafkaPy Tools - Consumer CLI")
    print("=" * 40)
    
    # Get parameters
    topics = input("Enter topics (comma-separated, press Enter for default): ").strip()
    max_messages = input("Enter max messages (press Enter for unlimited): ").strip()
    output_format = input("Enter output format (pretty/json/compact, press Enter for pretty): ").strip()
    
    # Build command
    cmd = [sys.executable, "-m", "kafkapy_tools.cli", "consumer"]
    
    if topics:
        topic_list = [t.strip() for t in topics.split(",")]
        cmd.extend(["--topics"] + topic_list)
    
    if max_messages:
        cmd.extend(["--max-messages", max_messages])
    
    if output_format:
        cmd.extend(["--output-format", output_format])
    
    # Run command
    try:
        import subprocess
        print("🔄 Starting consumer (press Ctrl+C to stop)...")
        result = subprocess.run(cmd, cwd=Path(__file__).parent)
        return result.returncode == 0
    except KeyboardInterrupt:
        print("\n⏹️  Consumer stopped by user")
        return True
    except Exception as e:
        print(f"❌ Error running consumer: {e}")
        return False


def main():
    """Main CLI interface."""
    parser = argparse.ArgumentParser(description="KafkaPy Tools - Interactive CLI")
    parser.add_argument(
        "command",
        choices=["producer", "consumer", "interactive"],
        help="Command to run"
    )
    
    args = parser.parse_args()
    
    # Check environment
    env_file = Path(__file__).parent / ".env"
    if not env_file.exists():
        print("❌ .env file not found. Please copy env.example to .env and configure it.")
        sys.exit(1)
    
    success = False
    
    if args.command == "producer":
        success = run_producer_cli()
    elif args.command == "consumer":
        success = run_consumer_cli()
    elif args.command == "interactive":
        print("🎯 KafkaPy Tools - Interactive Mode")
        print("=" * 40)
        
        while True:
            print("\nChoose an option:")
            print("1. Send message (Producer)")
            print("2. Consume messages (Consumer)")
            print("3. Exit")
            
            choice = input("\nEnter your choice (1-3): ").strip()
            
            if choice == "1":
                success = run_producer_cli()
            elif choice == "2":
                success = run_consumer_cli()
            elif choice == "3":
                print("👋 Goodbye!")
                break
            else:
                print("❌ Invalid choice. Please enter 1, 2, or 3.")
                continue
            
            if not success:
                print("❌ Operation failed. Please check your configuration.")
    
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
