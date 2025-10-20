import { MessagingClient, generateEncryptionKey } from '../index.js';
import type { MessagingConfig } from '../index.js';

/**
 * Example Worker (Consumer)
 * This demonstrates how to create a worker that:
 * 1. Connects to Kafka (single line)
 * 2. Registers handlers for specific topics
 * 3. Automatically receives, decrypts, processes, encrypts, and sends replies
 */

// Configuration
const config: MessagingConfig = {
  kafka: {
    brokers: ['localhost:9092'],
    clientId: 'example-worker',
    groupId: 'worker-group',
  },
  cassandra: {
    contactPoints: ['localhost'],
    localDataCenter: 'datacenter1',
    keyspace: 'messaging',
  },
  encryption: {
    // In production, load this from environment variable or secure storage
    encryptionKey: process.env.ENCRYPTION_KEY || generateEncryptionKey(),
  },
};

// Sample request/response types
interface CalculateRequest {
  operation: 'add' | 'subtract' | 'multiply' | 'divide';
  a: number;
  b: number;
}

interface CalculateResponse {
  result: number;
  operation: string;
}

interface UserRequest {
  userId: string;
}

interface UserResponse {
  userId: string;
  name: string;
  email: string;
}

async function main() {
  console.log('Starting Example Worker...');

  // Initialize messaging client - single line setup!
  const client = new MessagingClient(config);
  await client.initialize();

  // Register handler for "calculate" topic
  // Users only need to write their business logic
  client.registerHandler<CalculateRequest, CalculateResponse>(
    'calculate',
    async (requestId, payload) => {
      console.log(`Processing calculate request ${requestId}:`, payload);

      let result: number;
      switch (payload.operation) {
        case 'add':
          result = payload.a + payload.b;
          break;
        case 'subtract':
          result = payload.a - payload.b;
          break;
        case 'multiply':
          result = payload.a * payload.b;
          break;
        case 'divide':
          if (payload.b === 0) {
            throw new Error('Division by zero');
          }
          result = payload.a / payload.b;
          break;
        default:
          throw new Error(`Unknown operation: ${payload.operation}`);
      }

      return {
        result,
        operation: payload.operation,
      };
    }
  );

  // Register handler for "get-user" topic
  client.registerHandler<UserRequest, UserResponse>(
    'get-user',
    async (requestId, payload) => {
      console.log(`Processing get-user request ${requestId}:`, payload);

      // Simulate database lookup
      await new Promise((resolve) => setTimeout(resolve, 100));

      return {
        userId: payload.userId,
        name: `User ${payload.userId}`,
        email: `user${payload.userId}@example.com`,
      };
    }
  );

  // Start consuming from topics
  // All encryption/decryption happens automatically!
  await client.startConsuming(['calculate', 'get-user']);

  console.log('Worker is running and waiting for messages...');
  console.log('Registered handlers: calculate, get-user');
  console.log('Press Ctrl+C to exit');

  // Handle graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\nShutting down worker...');
    await client.close();
    process.exit(0);
  });
}

// Run the worker
main().catch((error) => {
  console.error('Worker error:', error);
  process.exit(1);
});
