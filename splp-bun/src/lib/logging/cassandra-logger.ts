import { Client } from 'cassandra-driver';
import type { CassandraConfig, LogEntry } from '../../types/index.js';

export class CassandraLogger {
  private client: Client;
  private keyspace: string;
  private isInitialized = false;

  constructor(config: CassandraConfig) {
    this.keyspace = config.keyspace;
    this.client = new Client({
      contactPoints: config.contactPoints,
      localDataCenter: config.localDataCenter,
    });
  }

  /**
   * Initialize Cassandra connection and create tables if needed
   */
  async initialize(): Promise<void> {
    if (this.isInitialized) {
      return;
    }

    try {
      await this.client.connect();
      await this.createKeyspaceIfNotExists();
      await this.createTablesIfNotExists();
      this.isInitialized = true;
      console.log('Cassandra logger initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Cassandra logger:', error);
      throw error;
    }
  }

  private async createKeyspaceIfNotExists(): Promise<void> {
    const query = `
      CREATE KEYSPACE IF NOT EXISTS ${this.keyspace}
      WITH replication = {
        'class': 'SimpleStrategy',
        'replication_factor': 1
      }
    `;
    await this.client.execute(query);
  }

  private async createTablesIfNotExists(): Promise<void> {
    const query = `
      CREATE TABLE IF NOT EXISTS ${this.keyspace}.message_logs (
        request_id uuid,
        timestamp timestamp,
        type text,
        topic text,
        payload text,
        success boolean,
        error text,
        duration_ms int,
        PRIMARY KEY (request_id, timestamp)
      ) WITH CLUSTERING ORDER BY (timestamp DESC)
        AND default_time_to_live = 604800
    `;
    await this.client.execute(query);

    // Create index for querying by timestamp
    const indexQuery = `
      CREATE INDEX IF NOT EXISTS ON ${this.keyspace}.message_logs (timestamp)
    `;
    await this.client.execute(indexQuery);
  }

  /**
   * Log a request or response message
   */
  async log(entry: LogEntry): Promise<void> {
    if (!this.isInitialized) {
      console.warn('Cassandra logger not initialized, skipping log');
      return;
    }

    try {
      const query = `
        INSERT INTO ${this.keyspace}.message_logs (
          request_id, timestamp, type, topic, payload, success, error, duration_ms
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const params = [
        entry.request_id,
        entry.timestamp,
        entry.type,
        entry.topic,
        JSON.stringify(entry.payload),
        entry.success ?? null,
        entry.error ?? null,
        entry.duration_ms ?? null,
      ];

      await this.client.execute(query, params, { prepare: true });
    } catch (error) {
      console.error('Failed to log to Cassandra:', error);
      // Don't throw - logging failure shouldn't break the main flow
    }
  }

  /**
   * Query logs by request_id
   */
  async getLogsByRequestId(requestId: string): Promise<LogEntry[]> {
    if (!this.isInitialized) {
      throw new Error('Cassandra logger not initialized');
    }

    const query = `
      SELECT * FROM ${this.keyspace}.message_logs
      WHERE request_id = ?
      ORDER BY timestamp DESC
    `;

    const result = await this.client.execute(query, [requestId], { prepare: true });

    return result.rows.map((row) => ({
      request_id: row.request_id.toString(),
      timestamp: row.timestamp,
      type: row.type,
      topic: row.topic,
      payload: JSON.parse(row.payload),
      success: row.success,
      error: row.error,
      duration_ms: row.duration_ms,
    }));
  }

  /**
   * Query logs by time range
   */
  async getLogsByTimeRange(startTime: Date, endTime: Date): Promise<LogEntry[]> {
    if (!this.isInitialized) {
      throw new Error('Cassandra logger not initialized');
    }

    const query = `
      SELECT * FROM ${this.keyspace}.message_logs
      WHERE timestamp >= ? AND timestamp <= ?
      ALLOW FILTERING
    `;

    const result = await this.client.execute(query, [startTime, endTime], { prepare: true });

    return result.rows.map((row) => ({
      request_id: row.request_id.toString(),
      timestamp: row.timestamp,
      type: row.type,
      topic: row.topic,
      payload: JSON.parse(row.payload),
      success: row.success,
      error: row.error,
      duration_ms: row.duration_ms,
    }));
  }

  /**
   * Close Cassandra connection
   */
  async close(): Promise<void> {
    if (this.isInitialized) {
      await this.client.shutdown();
      this.isInitialized = false;
      console.log('Cassandra logger closed');
    }
  }
}
