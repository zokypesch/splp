/**
 * Test Utilities for SPLP
 * Provides testing helpers and mocks for the messaging system
 */

import { MessagingClient } from '../request-reply/messaging-client.js';
import { CommandCenter } from '../../command-center/lib/command-center.js';
import type { MessagingConfig, CommandCenterConfig } from '../types/index.js';

export class TestMessagingClient extends MessagingClient {
  private mockHandlers: Map<string, any> = new Map();
  private sentMessages: Array<{topic: string, message: string, key?: string}> = [];

  constructor(config: MessagingConfig) {
    super(config);
  }

  /**
   * Mock handler registration for testing
   */
  registerHandler<TRequest = any, TResponse = any>(
    topic: string,
    handler: (requestId: string, payload: TRequest) => Promise<TResponse>
  ): void {
    this.mockHandlers.set(topic, handler);
  }

  /**
   * Get all sent messages for verification
   */
  getSentMessages(): Array<{topic: string, message: string, key?: string}> {
    return [...this.sentMessages];
  }

  /**
   * Clear sent messages history
   */
  clearSentMessages(): void {
    this.sentMessages = [];
  }

  /**
   * Simulate receiving a message
   */
  async simulateMessage(topic: string, message: string): Promise<void> {
    const handler = this.mockHandlers.get(topic);
    if (handler) {
      const parsedMessage = JSON.parse(message);
      await handler(parsedMessage.request_id, parsedMessage);
    }
  }

  /**
   * Override sendMessage to capture messages
   */
  async sendMessage(topic: string, message: string, key?: string): Promise<void> {
    this.sentMessages.push({ topic, message, key });
    // In real tests, you might want to actually send or mock the sending
  }
}

export class TestCommandCenter extends CommandCenter {
  private routedMessages: Array<{sourceTopic: string, targetTopic: string, message: string}> = [];

  constructor(config: CommandCenterConfig) {
    super(config);
  }

  /**
   * Get all routed messages for verification
   */
  getRoutedMessages(): Array<{sourceTopic: string, targetTopic: string, message: string}> {
    return [...this.routedMessages];
  }

  /**
   * Clear routed messages history
   */
  clearRoutedMessages(): void {
    this.routedMessages = [];
  }

  /**
   * Override routeMessage to capture routing
   */
  protected async routeMessage(
    messageValue: string,
    sourceTopic: string,
    startTime: number
  ): Promise<void> {
    // Capture the routing for testing
    const parsedMessage = JSON.parse(messageValue);
    const route = this.getSchemaRegistry().getRoute(parsedMessage.worker_name);
    
    if (route) {
      this.routedMessages.push({
        sourceTopic,
        targetTopic: route.targetTopic,
        message: messageValue
      });
    }

    // Call parent implementation
    return super.routeMessage(messageValue, sourceTopic, startTime);
  }
}

/**
 * Test data generators
 */
export class TestDataGenerator {
  /**
   * Generate test order data
   */
  static generateOrder(overrides: Partial<any> = {}): any {
    return {
      orderId: `ORD-${Date.now()}`,
      userId: `user-${Math.random().toString(36).substr(2, 9)}`,
      amount: Math.floor(Math.random() * 1000) + 100,
      items: ['laptop', 'mouse', 'keyboard'],
      timestamp: new Date().toISOString(),
      ...overrides
    };
  }

  /**
   * Generate test user data
   */
  static generateUser(overrides: Partial<any> = {}): any {
    return {
      userId: `user-${Math.random().toString(36).substr(2, 9)}`,
      name: `Test User ${Math.floor(Math.random() * 1000)}`,
      email: `test${Math.floor(Math.random() * 1000)}@example.com`,
      phone: `+628${Math.floor(Math.random() * 100000000)}`,
      address: {
        street: 'Test Street',
        city: 'Test City',
        country: 'Indonesia'
      },
      ...overrides
    };
  }

  /**
   * Generate test verification data
   */
  static generateVerificationData(overrides: Partial<any> = {}): any {
    return {
      requestId: `req-${Date.now()}`,
      userId: `user-${Math.random().toString(36).substr(2, 9)}`,
      verificationType: 'bansos',
      status: 'pending',
      timestamp: new Date().toISOString(),
      ...overrides
    };
  }
}

/**
 * Test assertions
 */
export class TestAssertions {
  /**
   * Assert message was sent to correct topic
   */
  static assertMessageSent(
    client: TestMessagingClient,
    topic: string,
    expectedMessage?: any
  ): void {
    const messages = client.getSentMessages();
    const message = messages.find(m => m.topic === topic);
    
    if (!message) {
      throw new Error(`No message sent to topic: ${topic}`);
    }
    
    if (expectedMessage) {
      const parsedMessage = JSON.parse(message.message);
      if (JSON.stringify(parsedMessage) !== JSON.stringify(expectedMessage)) {
        throw new Error(`Message content mismatch for topic: ${topic}`);
      }
    }
  }

  /**
   * Assert message was routed correctly
   */
  static assertMessageRouted(
    commandCenter: TestCommandCenter,
    sourceTopic: string,
    targetTopic: string
  ): void {
    const routedMessages = commandCenter.getRoutedMessages();
    const routed = routedMessages.find(
      m => m.sourceTopic === sourceTopic && m.targetTopic === targetTopic
    );
    
    if (!routed) {
      throw new Error(`No message routed from ${sourceTopic} to ${targetTopic}`);
    }
  }

  /**
   * Assert handler was called with correct data
   */
  static assertHandlerCalled(
    handler: jest.Mock,
    expectedRequestId: string,
    expectedPayload: any
  ): void {
    expect(handler).toHaveBeenCalledWith(expectedRequestId, expectedPayload);
  }
}

/**
 * Test configuration helpers
 */
export class TestConfig {
  /**
   * Create test messaging config
   */
  static createMessagingConfig(overrides: Partial<MessagingConfig> = {}): MessagingConfig {
    return {
      kafka: {
        brokers: ['localhost:9092'],
        clientId: 'test-client',
        groupId: 'test-group'
      },
      cassandra: {
        contactPoints: ['localhost'],
        localDataCenter: 'datacenter1',
        keyspace: 'test_keyspace'
      },
      encryption: {
        encryptionKey: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
      },
      ...overrides
    };
  }

  /**
   * Create test command center config
   */
  static createCommandCenterConfig(overrides: Partial<CommandCenterConfig> = {}): CommandCenterConfig {
    return {
      kafka: {
        brokers: ['localhost:9092'],
        clientId: 'test-command-center',
        groupId: 'test-command-center-group'
      },
      cassandra: {
        contactPoints: ['localhost'],
        localDataCenter: 'datacenter1',
        keyspace: 'test_command_center'
      },
      encryption: {
        encryptionKey: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
      },
      commandCenter: {
        inboxTopic: 'test-command-center-inbox',
        enableAutoRouting: true,
        defaultTimeout: 30000
      },
      ...overrides
    };
  }
}
