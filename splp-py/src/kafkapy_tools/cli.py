"""Command Line Interface for KafkaPy Tools."""

import argparse
import json
import sys
import time
from typing import Any, Dict, Optional

from .config import KafkaConfig
from .producer import KafkaProducerService
from .consumer import KafkaConsumerService
from .logging_config import setup_logging, get_logger


def producer_cli() -> None:
    """CLI for Kafka Producer."""
    parser = argparse.ArgumentParser(description="KafkaPy Tools - Producer CLI")
    
    parser.add_argument(
        "--config",
        type=str,
        help="Path to .env configuration file"
    )
    parser.add_argument(
        "--topic",
        type=str,
        help="Kafka topic name"
    )
    parser.add_argument(
        "--message",
        type=str,
        required=True,
        help="Message to send"
    )
    parser.add_argument(
        "--key",
        type=str,
        help="Message key"
    )
    parser.add_argument(
        "--headers",
        type=str,
        help="Message headers as JSON string"
    )
    parser.add_argument(
        "--partition",
        type=int,
        help="Specific partition"
    )
    parser.add_argument(
        "--batch",
        type=int,
        help="Send multiple messages (specify count)"
    )
    parser.add_argument(
        "--interval",
        type=float,
        default=1.0,
        help="Interval between messages in seconds (for batch mode)"
    )
    parser.add_argument(
        "--log-level",
        type=str,
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR"],
        help="Log level"
    )
    
    args = parser.parse_args()
    
    # Setup logging
    logger = setup_logging(level=args.log_level)
    
    try:
        # Load configuration
        config = KafkaConfig.from_env(args.config)
        
        # Override topic if provided
        if args.topic:
            config.producer_topic = args.topic
        
        # Parse headers
        headers = None
        if args.headers:
            try:
                headers = json.loads(args.headers)
            except json.JSONDecodeError as e:
                logger.error(f"Invalid headers JSON: {e}")
                sys.exit(1)
        
        # Create producer
        with KafkaProducerService(config) as producer:
            if args.batch:
                # Batch mode
                logger.info(f"Sending {args.batch} messages to topic {producer.topic}")
                
                for i in range(args.batch):
                    message = {
                        "message": args.message,
                        "batch_number": i + 1,
                        "timestamp": time.time(),
                    }
                    
                    producer.send_message(
                        message=message,
                        key=args.key,
                        headers=headers,
                        partition=args.partition
                    )
                    
                    if i < args.batch - 1:  # Don't sleep after last message
                        time.sleep(args.interval)
                
                logger.info(f"Successfully sent {args.batch} messages")
            else:
                # Single message mode
                logger.info(f"Sending message to topic {producer.topic}")
                
                producer.send_message(
                    message=args.message,
                    key=args.key,
                    headers=headers,
                    partition=args.partition
                )
                
                logger.info("Message sent successfully")
    
    except Exception as e:
        logger.error(f"Producer error: {e}")
        sys.exit(1)


def consumer_cli() -> None:
    """CLI for Kafka Consumer."""
    parser = argparse.ArgumentParser(description="KafkaPy Tools - Consumer CLI")
    
    parser.add_argument(
        "--config",
        type=str,
        help="Path to .env configuration file"
    )
    parser.add_argument(
        "--topics",
        type=str,
        nargs="+",
        help="Kafka topics to consume from"
    )
    parser.add_argument(
        "--group-id",
        type=str,
        help="Consumer group ID"
    )
    parser.add_argument(
        "--max-messages",
        type=int,
        help="Maximum number of messages to consume"
    )
    parser.add_argument(
        "--timeout",
        type=float,
        default=1.0,
        help="Poll timeout in seconds"
    )
    parser.add_argument(
        "--batch-size",
        type=int,
        help="Consume messages in batches"
    )
    parser.add_argument(
        "--seek-beginning",
        action="store_true",
        help="Seek to beginning of partitions"
    )
    parser.add_argument(
        "--seek-end",
        action="store_true",
        help="Seek to end of partitions"
    )
    parser.add_argument(
        "--output-format",
        type=str,
        default="pretty",
        choices=["pretty", "json", "compact"],
        help="Output format"
    )
    parser.add_argument(
        "--log-level",
        type=str,
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR"],
        help="Log level"
    )
    
    args = parser.parse_args()
    
    # Setup logging
    logger = setup_logging(level=args.log_level)
    
    try:
        # Load configuration
        config = KafkaConfig.from_env(args.config)
        
        # Override topics if provided
        if args.topics:
            config.consumer_topic = args.topics[0]  # For backward compatibility
        
        # Override group ID if provided
        if args.group_id:
            config.consumer_group_id = args.group_id
        
        # Create consumer
        with KafkaConsumerService(config, topics=args.topics) as consumer:
            # Seek operations
            if args.seek_beginning:
                consumer.seek_to_beginning()
            elif args.seek_end:
                consumer.seek_to_end()
            
            def message_handler(message: Dict[str, Any]) -> None:
                """Handle received message."""
                if args.output_format == "json":
                    print(json.dumps(message, indent=2))
                elif args.output_format == "compact":
                    print(f"{message['topic']}:{message['partition']}:{message['offset']} - {message['value']}")
                else:  # pretty
                    print(f"Topic: {message['topic']}")
                    print(f"Partition: {message['partition']}")
                    print(f"Offset: {message['offset']}")
                    print(f"Key: {message['key']}")
                    print(f"Value: {json.dumps(message['value'], indent=2)}")
                    print(f"Headers: {message['headers']}")
                    print("-" * 50)
            
            if args.batch_size:
                # Batch mode
                logger.info(f"Consuming messages in batches of {args.batch_size}")
                
                batch_count = 0
                while True:
                    if args.max_messages and batch_count * args.batch_size >= args.max_messages:
                        break
                    
                    messages = consumer.consume_batch(args.batch_size, args.timeout)
                    
                    if not messages:
                        logger.info("No more messages available")
                        break
                    
                    for message in messages:
                        message_handler(message)
                    
                    batch_count += 1
                    logger.info(f"Processed batch {batch_count} with {len(messages)} messages")
            else:
                # Continuous mode
                logger.info("Starting continuous message consumption")
                consumer.consume_messages(
                    message_handler=message_handler,
                    max_messages=args.max_messages,
                    timeout=args.timeout
                )
    
    except KeyboardInterrupt:
        logger.info("Consumer interrupted by user")
    except Exception as e:
        logger.error(f"Consumer error: {e}")
        sys.exit(1)


def main() -> None:
    """Main CLI entry point."""
    parser = argparse.ArgumentParser(description="KafkaPy Tools - Kafka messaging utilities")
    
    subparsers = parser.add_subparsers(dest="command", help="Available commands")
    
    # Producer subcommand
    producer_parser = subparsers.add_parser("producer", help="Producer commands")
    producer_parser.set_defaults(func=producer_cli)
    
    # Consumer subcommand
    consumer_parser = subparsers.add_parser("consumer", help="Consumer commands")
    consumer_parser.set_defaults(func=consumer_cli)
    
    args = parser.parse_args()
    
    if hasattr(args, 'func'):
        args.func()
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
