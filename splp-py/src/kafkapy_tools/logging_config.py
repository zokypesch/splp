"""Logging configuration for KafkaPy Tools."""

import json
import logging
import os
from typing import Any, Dict
from datetime import datetime


class JSONFormatter(logging.Formatter):
    """Custom JSON formatter for structured logging."""
    
    def format(self, record: logging.LogRecord) -> str:
        """Format log record as JSON."""
        log_entry = {
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
            "module": record.module,
            "function": record.funcName,
            "line": record.lineno,
        }
        
        # Add exception info if present
        if record.exc_info:
            log_entry["exception"] = self.formatException(record.exc_info)
        
        # Add extra fields
        if hasattr(record, "extra_fields"):
            log_entry.update(record.extra_fields)
        
        return json.dumps(log_entry, ensure_ascii=False)


def setup_logging(
    level: str = "INFO",
    format_type: str = "json",
    log_file: str = "kafkapy_tools.log",
    logger_name: str = "kafkapy_tools"
) -> logging.Logger:
    """
    Setup logging configuration.
    
    Args:
        level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
        format_type: Log format type ('json' or 'text')
        log_file: Log file path
        logger_name: Logger name
    
    Returns:
        Configured logger instance
    """
    # Get log level from environment if not provided
    log_level = os.getenv("LOG_LEVEL", level).upper()
    log_format = os.getenv("LOG_FORMAT", format_type).lower()
    log_file_path = os.getenv("LOG_FILE", log_file)
    
    # Create logger
    logger = logging.getLogger(logger_name)
    logger.setLevel(getattr(logging, log_level, logging.INFO))
    
    # Clear existing handlers
    logger.handlers.clear()
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(getattr(logging, log_level, logging.INFO))
    
    if log_format == "json":
        console_formatter = JSONFormatter()
    else:
        console_formatter = logging.Formatter(
            "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
        )
    
    console_handler.setFormatter(console_formatter)
    logger.addHandler(console_handler)
    
    # File handler
    file_handler = logging.FileHandler(log_file_path)
    file_handler.setLevel(getattr(logging, log_level, logging.INFO))
    file_handler.setFormatter(JSONFormatter())
    logger.addHandler(file_handler)
    
    # Prevent duplicate logs
    logger.propagate = False
    
    return logger


def get_logger(name: str = "kafkapy_tools") -> logging.Logger:
    """
    Get logger instance.
    
    Args:
        name: Logger name
    
    Returns:
        Logger instance
    """
    return logging.getLogger(name)


def log_with_extra_fields(
    logger: logging.Logger,
    level: int,
    message: str,
    **extra_fields: Any
) -> None:
    """
    Log message with extra fields.
    
    Args:
        logger: Logger instance
        level: Log level
        message: Log message
        **extra_fields: Additional fields to include in log
    """
    logger.log(level, message, extra={"extra_fields": extra_fields})
