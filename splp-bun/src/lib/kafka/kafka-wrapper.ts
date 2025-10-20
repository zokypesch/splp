import { Kafka, Producer, Consumer, Admin, EachMessagePayload, Partitioners } from 'kafkajs';
import type { KafkaConfig } from '../../types/index.js';

export class KafkaWrapper {
  private kafka: Kafka;
  private producer: Producer | null = null;
  private consumer: Consumer | null = null;
  private admin: Admin;
  private config: KafkaConfig;
  private isConsuming: boolean = false;
  private static instanceCounter = 0;
  private instanceId: number;

  constructor(config: KafkaConfig) {
    this.instanceId = ++KafkaWrapper.instanceCounter;
    console.log(`[KafkaWrapper#${this.instanceId}] NEW INSTANCE created for clientId: ${config.clientId}`);

    this.config = config;
    this.kafka = new Kafka({
      clientId: config.clientId,
      brokers: config.brokers,
      requestTimeout: 30000,
      retry: {
        retries: 8,
        initialRetryTime: 300,
        maxRetryTime: 30000,
      },
    });
    this.admin = this.kafka.admin();
  }

  /**
   * Initialize and connect producer (single line of code for users)
   */
  async connectProducer(): Promise<Producer> {
    if (this.producer) {
      return this.producer;
    }

    this.producer = this.kafka.producer({
      createPartitioner: Partitioners.LegacyPartitioner,
    });
    await this.producer.connect();
    console.log('Kafka producer connected');
    return this.producer;
  }

  /**
   * Initialize and connect consumer (single line of code for users)
   */
  async connectConsumer(groupId?: string): Promise<Consumer> {
    if (this.consumer) {
      return this.consumer;
    }

    const consumerGroupId = groupId || this.config.groupId || this.config.clientId;

    this.consumer = this.kafka.consumer({
      groupId: consumerGroupId,
    });

    await this.consumer.connect();
    console.log(`Kafka consumer connected with group: ${consumerGroupId}`);
    return this.consumer;
  }

  /**
   * Get producer instance (throws if not connected)
   */
  getProducer(): Producer {
    if (!this.producer) {
      throw new Error('Producer not connected. Call connectProducer() first.');
    }
    return this.producer;
  }

  /**
   * Get consumer instance (throws if not connected)
   */
  getConsumer(): Consumer {
    if (!this.consumer) {
      throw new Error('Consumer not connected. Call connectConsumer() first.');
    }
    return this.consumer;
  }

  /**
   * Send a message to a topic
   */
  async sendMessage(topic: string, message: string, key?: string): Promise<void> {
    const producer = this.getProducer();

    await producer.send({
      topic,
      messages: [
        {
          key: key || null,
          value: message,
        },
      ],
    });
  }

  /**
   * Subscribe to topics and process messages
   */
  async subscribe(
    topics: string[],
    messageHandler: (payload: EachMessagePayload) => Promise<void>
  ): Promise<void> {
    const consumer = this.getConsumer();

    // Prevent calling consumer.run() multiple times which would register duplicate handlers
    if (this.isConsuming) {
      console.warn(`[KafkaWrapper#${this.instanceId}] Consumer is already running. Ignoring duplicate subscribe() call.`);
      return;
    }

    // Set flag BEFORE consumer.run() to prevent race condition
    this.isConsuming = true;

    // Subscribe to topics
    for (const topic of topics) {
      await consumer.subscribe({ topic, fromBeginning: false });
    }

    // Start consuming
    await consumer.run({
      eachMessage: async (payload) => {
        await messageHandler(payload);
      },
    });

    console.log(`[KafkaWrapper#${this.instanceId}] Subscribed to topics: ${topics.join(', ')}`);
  }

  /**
   * Create topics if they don't exist
   */
  async createTopics(topics: string[]): Promise<void> {
    await this.admin.connect();

    const topicConfigs = topics.map((topic) => ({
      topic,
      numPartitions: 3,
      replicationFactor: 1,
    }));

    const created = await this.admin.createTopics({
      topics: topicConfigs,
      waitForLeaders: true,
    });

    if (created) {
      console.log(`Topics created: ${topics.join(', ')}`);
    } else {
      console.log('Topics already exist');
    }

    await this.admin.disconnect();
  }

  /**
   * Disconnect all connections
   */
  async disconnect(): Promise<void> {
    if (this.producer) {
      await this.producer.disconnect();
      console.log('Producer disconnected');
    }

    if (this.consumer) {
      await this.consumer.disconnect();
      console.log('Consumer disconnected');
    }
  }

  /**
   * Check if producer is connected
   */
  isProducerConnected(): boolean {
    return this.producer !== null;
  }

  /**
   * Check if consumer is connected
   */
  isConsumerConnected(): boolean {
    return this.consumer !== null;
  }
}
