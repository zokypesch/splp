"""Utility functions for KafkaPy Tools."""

import uuid
import time
import random
import string
from typing import Optional


def generate_request_id() -> str:
    """Generate a unique request ID."""
    return str(uuid.uuid4())


def is_valid_request_id(request_id: str) -> bool:
    """Validate if a string is a valid request ID."""
    try:
        uuid.UUID(request_id)
        return True
    except ValueError:
        return False


def generate_encryption_key() -> str:
    """Generate a random 32-byte encryption key."""
    return ''.join(random.choices(string.ascii_letters + string.digits, k=64))


def generate_correlation_id() -> str:
    """Generate a correlation ID for message tracking."""
    timestamp = int(time.time() * 1000)
    random_part = ''.join(random.choices(string.ascii_lowercase + string.digits, k=8))
    return f"{timestamp}-{random_part}"


def format_timestamp(timestamp: Optional[float] = None) -> str:
    """Format timestamp for logging."""
    if timestamp is None:
        timestamp = time.time()
    return time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(timestamp))


def calculate_duration_ms(start_time: float, end_time: Optional[float] = None) -> int:
    """Calculate duration in milliseconds."""
    if end_time is None:
        end_time = time.time()
    return int((end_time - start_time) * 1000)
