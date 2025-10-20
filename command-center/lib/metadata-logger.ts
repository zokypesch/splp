
import { Client } from 'cassandra-driver';
import type { RouteMetadata } from '../types/index.js';

/**
 * Metadata Logger
 * Logs routing metadata to Cassandra (EXCLUDES payload for privacy/performance)
 * Tracks: request_id, worker_name, timestamps, routing info, success/failure
 */
export class MetadataLogger {
  private client: Client;
  private keyspace: string;
  private isInitialized = false;

  constructor(contactPoints: string[], localDataCenter: string, keyspace: string) {
    this.keyspace = keyspace;
    this.client = new Client({
      contactPoints,
      localDataCenter,
    });
  }

  /**
   * Initialize Cassandra connection and create tables
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
      console.log('Metadata Logger initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Metadata Logger:', error);
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
      CREATE TABLE IF NOT EXISTS ${this.keyspace}.routing_metadata (
        request_id uuid,
        worker_name text,
        timestamp timestamp,
        source_topic text,
        target_topic text,
        route_id text,
        message_type text,
        success boolean,
        error text,
        processing_time_ms int,
        PRIMARY KEY (request_id, timestamp)
      ) WITH CLUSTERING ORDER BY (timestamp DESC)
        AND default_time_to_live = 604800
    `;
    await this.client.execute(query);

    // Index for querying by worker_name
    const workerIndex = `
      CREATE INDEX IF NOT EXISTS ON ${this.keyspace}.routing_metadata (worker_name)
    `;
    await this.client.execute(workerIndex);

    // Index for querying by timestamp
    const timestampIndex = `
      CREATE INDEX IF NOT EXISTS ON ${this.keyspace}.routing_metadata (timestamp)
    `;
    await this.client.execute(timestampIndex);

    // Index for querying by route_id
    const routeIndex = `
      CREATE INDEX IF NOT EXISTS ON ${this.keyspace}.routing_metadata (route_id)
    `;
    await this.client.execute(routeIndex);
  }

  /**
   * Log routing metadata (NO PAYLOAD)
   */
  async log(metadata: RouteMetadata): Promise<void> {
    if (!this.isInitialized) {
      console.warn('Metadata Logger not initialized, skipping log');
      return;
    }

    try {
      const query = `
        INSERT INTO ${this.keyspace}.routing_metadata (
          request_id, worker_name, timestamp, source_topic, target_topic,
          route_id, message_type, success, error, processing_time_ms
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      `;

      const params = [
        metadata.request_id,
        metadata.worker_name,
        metadata.timestamp,
        metadata.source_topic,
        metadata.target_topic,
        metadata.route_id,
        metadata.message_type,
        metadata.success,
        metadata.error || null,
        metadata.processing_time_ms || null,
      ];

      await this.client.execute(query, params, { prepare: true });
    } catch (error) {
      console.error('Failed to log metadata:', error);
      // Don't throw - logging failure shouldn't break routing
    }
  }

  /**
   * Get metadata by request_id
   */
  async getByRequestId(requestId: string): Promise<RouteMetadata[]> {
    if (!this.isInitialized) {
      throw new Error('Metadata Logger not initialized');
    }

    const query = `
      SELECT * FROM ${this.keyspace}.routing_metadata
      WHERE request_id = ?
      ORDER BY timestamp DESC
    `;

    const result = await this.client.execute(query, [requestId], { prepare: true });

    return result.rows.map((row) => ({
      request_id: row.request_id.toString(),
      worker_name: row.worker_name,
      timestamp: row.timestamp,
      source_topic: row.source_topic,
      target_topic: row.target_topic,
      route_id: row.route_id,
      message_type: row.message_type,
      success: row.success,
      error: row.error,
      processing_time_ms: row.processing_time_ms,
    }));
  }

  /**
   * Get metadata by worker_name
   */
  async getByWorkerName(
    workerName: string,
    limit: number = 100
  ): Promise<RouteMetadata[]> {
    if (!this.isInitialized) {
      throw new Error('Metadata Logger not initialized');
    }

    const query = `
      SELECT * FROM ${this.keyspace}.routing_metadata
      WHERE worker_name = ?
      LIMIT ?
      ALLOW FILTERING
    `;

    const result = await this.client.execute(query, [workerName, limit], { prepare: true });

    return result.rows.map((row) => ({
      request_id: row.request_id.toString(),
      worker_name: row.worker_name,
      timestamp: row.timestamp,
      source_topic: row.source_topic,
      target_topic: row.target_topic,
      route_id: row.route_id,
      message_type: row.message_type,
      success: row.success,
      error: row.error,
      processing_time_ms: row.processing_time_ms,
    }));
  }

  /**
   * Get metadata by time range
   */
  async getByTimeRange(
    startTime: Date,
    endTime: Date,
    limit: number = 1000
  ): Promise<RouteMetadata[]> {
    if (!this.isInitialized) {
      throw new Error('Metadata Logger not initialized');
    }

    const query = `
      SELECT * FROM ${this.keyspace}.routing_metadata
      WHERE timestamp >= ? AND timestamp <= ?
      LIMIT ?
      ALLOW FILTERING
    `;

    const result = await this.client.execute(
      query,
      [startTime, endTime, limit],
      { prepare: true }
    );

    return result.rows.map((row) => ({
      request_id: row.request_id.toString(),
      worker_name: row.worker_name,
      timestamp: row.timestamp,
      source_topic: row.source_topic,
      target_topic: row.target_topic,
      route_id: row.route_id,
      message_type: row.message_type,
      success: row.success,
      error: row.error,
      processing_time_ms: row.processing_time_ms,
    }));
  }

  /**
   * Get statistics for a route
   */
  async getRouteStats(routeId: string): Promise<{
    totalMessages: number;
    successCount: number;
    failureCount: number;
    avgProcessingTime: number;
  }> {
    if (!this.isInitialized) {
      throw new Error('Metadata Logger not initialized');
    }

    const query = `
      SELECT COUNT(*) as total,
             SUM(CASE WHEN success = true THEN 1 ELSE 0 END) as successes,
             AVG(processing_time_ms) as avg_time
      FROM ${this.keyspace}.routing_metadata
      WHERE route_id = ?
      ALLOW FILTERING
    `;

    const result = await this.client.execute(query, [routeId], { prepare: true });
    const row = result.rows[0];

    const totalMessages = parseInt(row.total?.toString() || '0');
    const successCount = parseInt(row.successes?.toString() || '0');

    return {
      totalMessages,
      successCount,
      failureCount: totalMessages - successCount,
      avgProcessingTime: parseFloat(row.avg_time?.toString() || '0'),
    };
  }

  /**
   * Close Cassandra connection
   */
  async close(): Promise<void> {
    if (this.isInitialized) {
      await this.client.shutdown();
      this.isInitialized = false;
      console.log('Metadata Logger closed');
    }
  }
}
