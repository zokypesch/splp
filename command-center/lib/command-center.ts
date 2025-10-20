import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { SchemaRegistryManager } from './schema-registry.js';
import { MetadataLogger } from './metadata-logger.js';
import type {
  CommandCenterConfig,
  IncomingMessage,
  RoutedMessage,
  RouteMetadata,
} from '../types/index.js';

/**
 * Command Center
 * Central routing hub that:
 * 1. Receives messages from publishers on inbox topic
 * 2. Looks up routes in schema registry
 * 3. Routes messages to target topics
 * 4. Logs metadata to Cassandra (NO PAYLOAD)
 */
export class CommandCenter {
  private kafka: KafkaWrapper;
  private schemaRegistry: SchemaRegistryManager;
  private metadataLogger: MetadataLogger;
  private config: CommandCenterConfig;

  constructor(config: CommandCenterConfig) {
    this.config = config;
    this.kafka = new KafkaWrapper(config.kafka);
    this.schemaRegistry = new SchemaRegistryManager(
      config.cassandra.contactPoints,
      config.cassandra.localDataCenter,
      config.cassandra.keyspace
    );
    this.metadataLogger = new MetadataLogger(
      config.cassandra.contactPoints,
      config.cassandra.localDataCenter,
      config.cassandra.keyspace
    );
  }

  /**
   * Initialize Command Center
   */
  async initialize(): Promise<void> {
    console.log('Initializing Command Center...');

    await this.kafka.connectProducer();
    await this.kafka.connectConsumer();
    await this.schemaRegistry.initialize();
    await this.metadataLogger.initialize();

    console.log('Command Center initialized successfully');
  }

  /**
   * Start listening for messages on inbox topic and route them
   */
  async start(): Promise<void> {
    const inboxTopic = this.config.commandCenter.inboxTopic;

    console.log(`Starting Command Center, listening on: ${inboxTopic}`);

    await this.kafka.subscribe([inboxTopic], async ({ topic, partition, message }) => {
      const startTime = Date.now();

      try {
        if (!message.value) {
          console.warn('Received empty message, skipping');
          return;
        }

        const messageValue = message.value.toString();
        await this.routeMessage(messageValue, topic, startTime);
      } catch (error) {
        console.error('Error processing message:', error);
      }
    });

    console.log('Command Center is running...');
  }

  /**
   * Route a message to its target topic based on schema registry
   */
  private async routeMessage(
    messageValue: string,
    sourceTopic: string,
    startTime: number
  ): Promise<void> {
    let metadata: Partial<RouteMetadata> = {
      timestamp: new Date(),
      source_topic: sourceTopic,
      message_type: 'request',
    };

    try {
      // Parse incoming message
      const incomingMsg: IncomingMessage = JSON.parse(messageValue);

      metadata.request_id = incomingMsg.request_id;
      metadata.worker_name = incomingMsg.worker_name;

      console.log(
        `Routing message from ${incomingMsg.worker_name} (${incomingMsg.request_id})`
      );

      // Look up route in schema registry
      const route = this.schemaRegistry.getRoute(incomingMsg.worker_name);

      if (!route) {
        throw new Error(`No route found for publisher: ${incomingMsg.worker_name}`);
      }

      if (!route.enabled) {
        throw new Error(`Route disabled for publisher: ${incomingMsg.worker_name}`);
      }

      metadata.route_id = route.routeId;
      metadata.target_topic = route.targetTopic;

      // Create routed message (preserve encryption, add routing info)
      const routedMsg: RoutedMessage = {
        request_id: incomingMsg.request_id,
        worker_name: incomingMsg.worker_name,
        source_topic: sourceTopic,
        data: incomingMsg.data,
        iv: incomingMsg.iv,
        tag: incomingMsg.tag,
      };

      // Route to target topic
      await this.kafka.sendMessage(
        route.targetTopic,
        JSON.stringify(routedMsg),
        incomingMsg.request_id
      );

      const processingTime = Date.now() - startTime;

      // Log metadata (NO PAYLOAD)
      await this.metadataLogger.log({
        request_id: incomingMsg.request_id,
        worker_name: incomingMsg.worker_name,
        timestamp: new Date(),
        source_topic: sourceTopic,
        target_topic: route.targetTopic,
        route_id: route.routeId,
        message_type: 'request',
        success: true,
        processing_time_ms: processingTime,
      });

      console.log(
        `Routed ${incomingMsg.request_id}: ${incomingMsg.worker_name} -> ${route.targetTopic} (${processingTime}ms)`
      );
    } catch (error) {
      const processingTime = Date.now() - startTime;
      const errorMessage = error instanceof Error ? error.message : String(error);

      // Log failed routing
      if (metadata.request_id && metadata.worker_name) {
        await this.metadataLogger.log({
          request_id: metadata.request_id,
          worker_name: metadata.worker_name,
          timestamp: new Date(),
          source_topic: sourceTopic,
          target_topic: metadata.target_topic || 'unknown',
          route_id: metadata.route_id || 'unknown',
          message_type: 'request',
          success: false,
          error: errorMessage,
          processing_time_ms: processingTime,
        });
      }

      console.error(`Routing failed: ${errorMessage}`);
    }
  }

  /**
   * Get schema registry for management
   */
  getSchemaRegistry(): SchemaRegistryManager {
    return this.schemaRegistry;
  }

  /**
   * Get metadata logger for queries
   */
  getMetadataLogger(): MetadataLogger {
    return this.metadataLogger;
  }

  /**
   * Shutdown Command Center
   */
  async shutdown(): Promise<void> {
    console.log('Shutting down Command Center...');

    await this.kafka.disconnect();
    await this.schemaRegistry.close();
    await this.metadataLogger.close();

    console.log('Command Center shutdown complete');
  }
}
