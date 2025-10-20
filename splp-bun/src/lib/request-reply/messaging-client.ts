import { KafkaWrapper } from '../kafka/kafka-wrapper.js';
import { CassandraLogger } from '../logging/cassandra-logger.js';
import { encryptPayload, decryptPayload } from '../crypto/encryption.js';
import { generateRequestId } from '../utils/request-id.js';
import type {
  MessagingConfig,
  RequestHandler,
  HandlerRegistry,
  RequestMessage,
  ResponseMessage,
  EncryptedMessage,
} from '../../types/index.js';

export class MessagingClient {
  private kafka: KafkaWrapper;
  private logger: CassandraLogger;
  private encryptionKey: string;
  private handlers: HandlerRegistry = {};
  private pendingRequests: Map<
    string,
    {
      resolve: (value: any) => void;
      reject: (error: Error) => void;
      startTime: number;
    }
  > = new Map();

  constructor(private config: MessagingConfig) {
    this.kafka = new KafkaWrapper(config.kafka);
    this.logger = new CassandraLogger(config.cassandra);
    this.encryptionKey = config.encryption.encryptionKey;
  }

  /**
   * Initialize the messaging client - single line setup for users
   */
  async initialize(): Promise<void> {
    await this.kafka.connectProducer();
    await this.kafka.connectConsumer();
    await this.logger.initialize();
    console.log('Messaging client initialized successfully');
  }

  /**
   * Register a handler for a specific topic
   * Users only need to register their handler function
   */
  registerHandler<TRequest = any, TResponse = any>(
    topic: string,
    handler: RequestHandler<TRequest, TResponse>
  ): void {
    this.handlers[topic] = handler;
    console.log(`Handler registered for topic: ${topic}`);
  }

  /**
   * Start consuming messages and processing with registered handlers
   */
  async startConsuming(topics: string[]): Promise<void> {
    // Subscribe to request topics
    const requestTopics = topics;

    // Also subscribe to reply topics for request-reply pattern
    const replyTopic = `${this.config.kafka.clientId}.replies`;
    const allTopics = [...requestTopics, replyTopic];

    await this.kafka.subscribe(allTopics, async ({ topic, partition, message }) => {
      try {
        if (topic === replyTopic) {
          // Handle reply message
          await this.handleReply(message.value?.toString() || '');
        } else {
          // Handle request message
          await this.handleRequest(topic, message.value?.toString() || '');
        }
      } catch (error) {
        console.error('Error processing message:', error);
      }
    });

    console.log(`Started consuming from topics: ${allTopics.join(', ')}`);
  }

  /**
   * Send a request with automatic encryption and wait for reply
   * Returns the decrypted response
   */
  async request<TRequest = any, TResponse = any>(
    topic: string,
    payload: TRequest,
    timeoutMs: number = 30000
  ): Promise<TResponse> {
    const requestId = generateRequestId();
    const startTime = Date.now();

    // Create promise for reply
    const replyPromise = new Promise<TResponse>((resolve, reject) => {
      this.pendingRequests.set(requestId, {
        resolve,
        reject,
        startTime,
      });

      // Set timeout
      setTimeout(() => {
        if (this.pendingRequests.has(requestId)) {
          this.pendingRequests.delete(requestId);
          reject(new Error(`Request timeout after ${timeoutMs}ms`));
        }
      }, timeoutMs);
    });

    // Encrypt payload
    const encryptedMessage = encryptPayload(payload, this.encryptionKey, requestId);

    // Log request
    await this.logger.log({
      request_id: requestId,
      timestamp: new Date(),
      type: 'request',
      topic,
      payload,
    });

    // Send to Kafka
    const replyTopic = `${this.config.kafka.clientId}.replies`;
    const messageWithReplyTopic = {
      ...encryptedMessage,
      replyTopic,
    };

    await this.kafka.sendMessage(
      topic,
      JSON.stringify(messageWithReplyTopic),
      requestId
    );

    console.log(`Request sent: ${requestId} to topic: ${topic}`);

    return replyPromise;
  }

  /**
   * Handle incoming request - decrypt, process with handler, encrypt and send reply
   */
  private async handleRequest(topic: string, messageValue: string): Promise<void> {
    const startTime = Date.now();
    let requestId = '';

    try {
      // Parse message
      const encryptedMessage: EncryptedMessage & { replyTopic?: string } = JSON.parse(messageValue);
      requestId = encryptedMessage.request_id;

      // Decrypt payload
      const { payload } = decryptPayload(encryptedMessage, this.encryptionKey);

      console.log(`Request received: ${requestId} from topic: ${topic}`);

      // Find handler
      const handler = this.handlers[topic];
      if (!handler) {
        throw new Error(`No handler registered for topic: ${topic}`);
      }

      // Process with handler
      const responsePayload = await handler(requestId, payload);

      // Create response
      const response: ResponseMessage = {
        request_id: requestId,
        payload: responsePayload,
        timestamp: Date.now(),
        success: true,
      };

      // Encrypt response
      const encryptedResponse = encryptPayload(response, this.encryptionKey, requestId);

      // Send reply to reply topic
      const replyTopic = encryptedMessage.replyTopic || `${topic}.replies`;
      await this.kafka.sendMessage(
        replyTopic,
        JSON.stringify(encryptedResponse),
        requestId
      );

      // Log response
      const duration = Date.now() - startTime;
      await this.logger.log({
        request_id: requestId,
        timestamp: new Date(),
        type: 'response',
        topic,
        payload: responsePayload,
        success: true,
        duration_ms: duration,
      });

      console.log(`Response sent: ${requestId} (${duration}ms)`);
    } catch (error) {
      // Log error
      const duration = Date.now() - startTime;
      await this.logger.log({
        request_id: requestId,
        timestamp: new Date(),
        type: 'response',
        topic,
        payload: null,
        success: false,
        error: error instanceof Error ? error.message : String(error),
        duration_ms: duration,
      });

      console.error(`Error handling request ${requestId}:`, error);
    }
  }

  /**
   * Handle incoming reply - decrypt and resolve pending request
   */
  private async handleReply(messageValue: string): Promise<void> {
    try {
      // Parse encrypted message
      const encryptedMessage: EncryptedMessage = JSON.parse(messageValue);
      const requestId = encryptedMessage.request_id;

      // Decrypt response
      const { payload: response } = decryptPayload<ResponseMessage>(
        encryptedMessage,
        this.encryptionKey
      );

      console.log(`Reply received: ${requestId}`);

      // Find pending request
      const pendingRequest = this.pendingRequests.get(requestId);
      if (pendingRequest) {
        this.pendingRequests.delete(requestId);

        // Log response
        const duration = Date.now() - pendingRequest.startTime;
        await this.logger.log({
          request_id: requestId,
          timestamp: new Date(),
          type: 'response',
          topic: 'reply',
          payload: response.payload,
          success: response.success,
          error: response.error,
          duration_ms: duration,
        });

        // Resolve or reject promise
        if (response.success) {
          pendingRequest.resolve(response.payload);
        } else {
          pendingRequest.reject(new Error(response.error || 'Request failed'));
        }
      }
    } catch (error) {
      console.error('Error handling reply:', error);
    }
  }

  /**
   * Get logger instance for manual queries
   */
  getLogger(): CassandraLogger {
    return this.logger;
  }

  /**
   * Close all connections
   */
  async close(): Promise<void> {
    await this.kafka.disconnect();
    await this.logger.close();
    console.log('Messaging client closed');
  }
}
