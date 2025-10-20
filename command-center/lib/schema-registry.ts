import { Client } from 'cassandra-driver';
import type { RouteConfig, ServiceInfo, SchemaRegistry } from '../types/index.js';

/**
 * Schema Registry
 * Manages route configurations and service information
 * Persists to Cassandra for durability
 */
export class SchemaRegistryManager {
  private routes: Map<string, RouteConfig> = new Map();
  private services: Map<string, ServiceInfo> = new Map();
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
   * Initialize Cassandra connection and create schema tables
   */
  async initialize(): Promise<void> {
    if (this.isInitialized) {
      return;
    }

    try {
      await this.client.connect();
      await this.createKeyspaceIfNotExists();
      await this.createTablesIfNotExists();
      await this.loadFromCassandra();
      this.isInitialized = true;
      console.log('Schema Registry initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Schema Registry:', error);
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
    // Routes table
    const routesTable = `
      CREATE TABLE IF NOT EXISTS ${this.keyspace}.routes (
        route_id text PRIMARY KEY,
        source_publisher text,
        target_topic text,
        service_name text,
        enabled boolean,
        created_at timestamp,
        updated_at timestamp
      )
    `;
    await this.client.execute(routesTable);

    // Index for querying by source_publisher
    const publisherIndex = `
      CREATE INDEX IF NOT EXISTS ON ${this.keyspace}.routes (source_publisher)
    `;
    await this.client.execute(publisherIndex);

    // Services table
    const servicesTable = `
      CREATE TABLE IF NOT EXISTS ${this.keyspace}.services (
        service_name text PRIMARY KEY,
        version text,
        description text,
        endpoint text,
        tags set<text>,
        created_at timestamp,
        updated_at timestamp
      )
    `;
    await this.client.execute(servicesTable);
  }

  /**
   * Load routes and services from Cassandra on startup
   */
  private async loadFromCassandra(): Promise<void> {
    // Load routes
    const routesQuery = `SELECT * FROM ${this.keyspace}.routes`;
    const routesResult = await this.client.execute(routesQuery);

    for (const row of routesResult.rows) {
      const route: RouteConfig = {
        routeId: row.route_id,
        sourcePublisher: row.source_publisher,
        targetTopic: row.target_topic,
        serviceInfo: await this.getService(row.service_name),
        enabled: row.enabled,
        createdAt: row.created_at,
        updatedAt: row.updated_at,
      };
      this.routes.set(row.source_publisher, route);
    }

    // Load services
    const servicesQuery = `SELECT * FROM ${this.keyspace}.services`;
    const servicesResult = await this.client.execute(servicesQuery);

    for (const row of servicesResult.rows) {
      const service: ServiceInfo = {
        serviceName: row.service_name,
        version: row.version,
        description: row.description,
        endpoint: row.endpoint,
        tags: row.tags ? Array.from(row.tags) : [],
        createdAt: row.created_at,
        updatedAt: row.updated_at,
      };
      this.services.set(row.service_name, service);
    }

    console.log(`Loaded ${this.routes.size} routes and ${this.services.size} services`);
  }

  /**
   * Register a new service
   */
  async registerService(service: ServiceInfo): Promise<void> {
    const query = `
      INSERT INTO ${this.keyspace}.services (
        service_name, version, description, endpoint, tags, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    `;

    const params = [
      service.serviceName,
      service.version,
      service.description || null,
      service.endpoint || null,
      service.tags || [],
      service.createdAt,
      service.updatedAt,
    ];

    await this.client.execute(query, params, { prepare: true });
    this.services.set(service.serviceName, service);

    console.log(`Service registered: ${service.serviceName}`);
  }

  /**
   * Register a new route
   */
  async registerRoute(route: RouteConfig): Promise<void> {
    // First ensure service exists
    if (!this.services.has(route.serviceInfo.serviceName)) {
      await this.registerService(route.serviceInfo);
    }

    const query = `
      INSERT INTO ${this.keyspace}.routes (
        route_id, source_publisher, target_topic, service_name, enabled, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    `;

    const params = [
      route.routeId,
      route.sourcePublisher,
      route.targetTopic,
      route.serviceInfo.serviceName,
      route.enabled,
      route.createdAt,
      route.updatedAt,
    ];

    await this.client.execute(query, params, { prepare: true });
    this.routes.set(route.sourcePublisher, route);

    console.log(`Route registered: ${route.sourcePublisher} -> ${route.targetTopic}`);
  }

  /**
   * Update route enabled status
   */
  async updateRouteStatus(sourcePublisher: string, enabled: boolean): Promise<void> {
    const route = this.routes.get(sourcePublisher);
    if (!route) {
      throw new Error(`Route not found for publisher: ${sourcePublisher}`);
    }

    const query = `
      UPDATE ${this.keyspace}.routes
      SET enabled = ?, updated_at = ?
      WHERE route_id = ?
    `;

    const updatedAt = new Date();
    await this.client.execute(query, [enabled, updatedAt, route.routeId], { prepare: true });

    route.enabled = enabled;
    route.updatedAt = updatedAt;
    this.routes.set(sourcePublisher, route);

    console.log(`Route ${route.routeId} ${enabled ? 'enabled' : 'disabled'}`);
  }

  /**
   * Get route for a source publisher
   */
  getRoute(sourcePublisher: string): RouteConfig | undefined {
    return this.routes.get(sourcePublisher);
  }

  /**
   * Get service information
   */
  async getService(serviceName: string): Promise<ServiceInfo> {
    let service = this.services.get(serviceName);

    if (!service) {
      // Try loading from Cassandra
      const query = `SELECT * FROM ${this.keyspace}.services WHERE service_name = ?`;
      const result = await this.client.execute(query, [serviceName], { prepare: true });

      if (result.rows.length > 0) {
        const row = result.rows[0];
        service = {
          serviceName: row.service_name,
          version: row.version,
          description: row.description,
          endpoint: row.endpoint,
          tags: row.tags ? Array.from(row.tags) : [],
          createdAt: row.created_at,
          updatedAt: row.updated_at,
        };
        this.services.set(serviceName, service);
      } else {
        throw new Error(`Service not found: ${serviceName}`);
      }
    }

    return service;
  }

  /**
   * Get all routes
   */
  getAllRoutes(): RouteConfig[] {
    return Array.from(this.routes.values());
  }

  /**
   * Get all services
   */
  getAllServices(): ServiceInfo[] {
    return Array.from(this.services.values());
  }

  /**
   * Delete a route
   */
  async deleteRoute(sourcePublisher: string): Promise<void> {
    const route = this.routes.get(sourcePublisher);
    if (!route) {
      throw new Error(`Route not found: ${sourcePublisher}`);
    }

    const query = `DELETE FROM ${this.keyspace}.routes WHERE route_id = ?`;
    await this.client.execute(query, [route.routeId], { prepare: true });

    this.routes.delete(sourcePublisher);
    console.log(`Route deleted: ${sourcePublisher}`);
  }

  /**
   * Close Cassandra connection
   */
  async close(): Promise<void> {
    if (this.isInitialized) {
      await this.client.shutdown();
      this.isInitialized = false;
      console.log('Schema Registry closed');
    }
  }
}
