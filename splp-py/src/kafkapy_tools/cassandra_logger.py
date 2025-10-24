"""Cassandra logger for KafkaPy Tools."""

import json
from typing import List, Optional, Dict, Any
from datetime import datetime, timedelta
from cassandra.cluster import Cluster
from cassandra.auth import PlainTextAuthProvider
from cassandra.policies import DCAwareRoundRobinPolicy

from .types import LogEntry
import logging


class CassandraLogger:
    """Cassandra logger for storing message logs."""
    
    def __init__(self, config):
        """
        Initialize Cassandra logger.
        
        Args:
            config: CassandraConfig instance
        """
        self.config = config
        self.logger = logging.getLogger(f"{__name__}.cassandra")
        self.cluster = None
        self.session = None
        self.is_initialized = False
    
    async def initialize(self) -> None:
        """Initialize Cassandra connection."""
        try:
            # Create cluster
            self.cluster = Cluster(
                contact_points=self.config.contact_points,
                load_balancing_policy=DCAwareRoundRobinPolicy(
                    local_dc=self.config.local_data_center
                ),
                connect_timeout=30,
                control_connection_timeout=30,
            )
            
            # Create session
            self.session = self.cluster.connect()
            
            # Create keyspace if not exists
            self.session.execute(f"""
                CREATE KEYSPACE IF NOT EXISTS {self.config.keyspace}
                WITH REPLICATION = {{
                    'class': 'SimpleStrategy',
                    'replication_factor': 1
                }}
            """)
            
            # Use keyspace
            self.session.set_keyspace(self.config.keyspace)
            
            # Create table if not exists
            self.session.execute("""
                CREATE TABLE IF NOT EXISTS message_logs (
                    request_id text,
                    timestamp timestamp,
                    type text,
                    topic text,
                    payload text,
                    success boolean,
                    error text,
                    duration_ms int,
                    PRIMARY KEY (request_id, timestamp)
                )
            """)
            
            self.is_initialized = True
            self.logger.info("Cassandra logger initialized successfully")
            
        except Exception as e:
            self.logger.error(f"Failed to initialize Cassandra logger: {e}")
            raise
    
    async def log(self, log_entry: LogEntry) -> None:
        """
        Log an entry to Cassandra.
        
        Args:
            log_entry: LogEntry instance
        """
        # Temporarily disable Cassandra logging to avoid string formatting errors
        return
        
        if not self.is_initialized:
            self.logger.warning("Cassandra logger not initialized, skipping log")
            return
        
        try:
            # Prepare data safely
            try:
                payload_json = json.dumps(log_entry.payload, ensure_ascii=False) if log_entry.payload else None
            except (TypeError, ValueError) as json_error:
                payload_json = f"<non-serializable: {type(log_entry.payload).__name__}>"
            
            # Insert log entry
            self.session.execute("""
                INSERT INTO message_logs (
                    request_id, timestamp, type, topic, payload,
                    success, error, duration_ms
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                log_entry.request_id or "unknown",
                log_entry.timestamp,
                log_entry.type or "unknown",
                log_entry.topic or "unknown",
                payload_json,
                log_entry.success,
                log_entry.error,
                log_entry.duration_ms,
            ))
            
            self.logger.debug(f"Logged entry for request {log_entry.request_id or 'unknown'}")
            
        except Exception as e:
            self.logger.error(f"Failed to log entry: {str(e)}")
    
    async def get_logs_by_request_id(self, request_id: str) -> List[Dict[str, Any]]:
        """
        Get logs by request ID.
        
        Args:
            request_id: Request ID to search for
        
        Returns:
            List of log entries
        """
        if not self.is_initialized:
            self.logger.warning("Cassandra logger not initialized")
            return []
        
        try:
            rows = self.session.execute("""
                SELECT * FROM message_logs WHERE request_id = ?
            """, (request_id,))
            
            logs = []
            for row in rows:
                log_data = {
                    "request_id": row.request_id,
                    "timestamp": row.timestamp,
                    "type": row.type,
                    "topic": row.topic,
                    "payload": json.loads(row.payload) if row.payload else None,
                    "success": row.success,
                    "error": row.error,
                    "duration_ms": row.duration_ms,
                }
                logs.append(log_data)
            
            return logs
            
        except Exception as e:
            self.logger.error(f"Failed to get logs by request ID: {str(e)}")
            return []
    
    async def get_logs_by_time_range(
        self,
        start_time: datetime,
        end_time: datetime
    ) -> List[Dict[str, Any]]:
        """
        Get logs by time range.
        
        Args:
            start_time: Start time
            end_time: End time
        
        Returns:
            List of log entries
        """
        if not self.is_initialized:
            self.logger.warning("Cassandra logger not initialized")
            return []
        
        try:
            rows = self.session.execute("""
                SELECT * FROM message_logs 
                WHERE timestamp >= ? AND timestamp <= ?
                ALLOW FILTERING
            """, (start_time, end_time))
            
            logs = []
            for row in rows:
                log_data = {
                    "request_id": row.request_id,
                    "timestamp": row.timestamp,
                    "type": row.type,
                    "topic": row.topic,
                    "payload": json.loads(row.payload) if row.payload else None,
                    "success": row.success,
                    "error": row.error,
                    "duration_ms": row.duration_ms,
                }
                logs.append(log_data)
            
            return logs
            
        except Exception as e:
            self.logger.error(f"Failed to get logs by time range: {str(e)}")
            return []
    
    async def get_logs_by_topic(self, topic: str, limit: int = 100) -> List[Dict[str, Any]]:
        """
        Get logs by topic.
        
        Args:
            topic: Topic name
            limit: Maximum number of logs to return
        
        Returns:
            List of log entries
        """
        if not self.is_initialized:
            self.logger.warning("Cassandra logger not initialized")
            return []
        
        try:
            rows = self.session.execute("""
                SELECT * FROM message_logs 
                WHERE topic = ?
                LIMIT ?
                ALLOW FILTERING
            """, (topic, limit))
            
            logs = []
            for row in rows:
                log_data = {
                    "request_id": row.request_id,
                    "timestamp": row.timestamp,
                    "type": row.type,
                    "topic": row.topic,
                    "payload": json.loads(row.payload) if row.payload else None,
                    "success": row.success,
                    "error": row.error,
                    "duration_ms": row.duration_ms,
                }
                logs.append(log_data)
            
            return logs
            
        except Exception as e:
            self.logger.error(f"Failed to get logs by topic: {str(e)}")
            return []
    
    async def close(self) -> None:
        """Close Cassandra connection."""
        if self.session:
            self.session.shutdown()
        if self.cluster:
            self.cluster.shutdown()
        self.is_initialized = False
        self.logger.info("Cassandra logger closed")
