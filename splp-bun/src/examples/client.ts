import { MessagingClient, generateEncryptionKey } from '../index.js';
import type { MessagingConfig } from '../index.js';

/**
 * Example Client (Producer/Requester)
 * This demonstrates how to send requests and receive replies
 * with automatic encryption/decryption
 */

// Configuration (must use same encryption key as worker!)
const config: MessagingConfig = {
  kafka: {
    brokers: ['localhost:9092'],
    clientId: 'example-client',
    groupId: 'client-group',
  },
  cassandra: {
    contactPoints: ['localhost'],
    localDataCenter: 'datacenter1',
    keyspace: 'messaging',
  },
  encryption: {
    // In production, load this from environment variable or secure storage
    // MUST be the same key used by the worker!
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
  console.log('Starting Example Client...');

  // Initialize messaging client - single line setup!
  const client = new MessagingClient(config);
  await client.initialize();

  console.log('Client initialized, sending test requests...\n');

  try {
    // Example 1: Send calculate request
    console.log('--- Example 1: Calculate Request ---');
    const calculateRequest: CalculateRequest = {
      operation: 'add',
      a: 10,
      b: 5,
    };

    console.log('Sending request:', calculateRequest);

    // Send request - encryption happens automatically!
    const calculateResponse = await client.request<CalculateRequest, CalculateResponse>(
      'calculate',
      calculateRequest,
      30000 // 30 second timeout
    );

    console.log('Received response:', calculateResponse);
    console.log(`Result: ${calculateResponse.result}\n`);

    // Example 2: Send get-user request
    console.log('--- Example 2: Get User Request ---');
    const userRequest: UserRequest = {
      userId: '12345',
    };

    console.log('Sending request:', userRequest);

    const userResponse = await client.request<UserRequest, UserResponse>(
      'get-user',
      userRequest,
      30000
    );

    console.log('Received response:', userResponse);
    console.log(`User: ${userResponse.name} (${userResponse.email})\n`);

    // Example 3: Multiple concurrent requests
    console.log('--- Example 3: Multiple Concurrent Requests ---');

    const requests = [
      client.request<CalculateRequest, CalculateResponse>('calculate', {
        operation: 'multiply',
        a: 7,
        b: 8,
      }),
      client.request<CalculateRequest, CalculateResponse>('calculate', {
        operation: 'subtract',
        a: 100,
        b: 25,
      }),
      client.request<UserRequest, UserResponse>('get-user', { userId: '999' }),
    ];

    console.log('Sending 3 concurrent requests...');
    const responses = await Promise.all(requests);

    console.log('All responses received:');
    responses.forEach((response, index) => {
      console.log(`  ${index + 1}.`, response);
    });

    // Example 4: Query logs from Cassandra
    console.log('\n--- Example 4: Query Logs ---');
    const logger = client.getLogger();

    // Get logs from the last 5 minutes
    const endTime = new Date();
    const startTime = new Date(endTime.getTime() - 5 * 60 * 1000);

    const logs = await logger.getLogsByTimeRange(startTime, endTime);
    console.log(`Found ${logs.length} log entries in the last 5 minutes`);

    if (logs.length > 0) {
      console.log('Sample log entry:', logs[0]);
    }
  } catch (error) {
    console.error('Error:', error);
  } finally {
    // Clean up
    console.log('\nClosing client...');
    await client.close();
    console.log('Client closed');
  }
}

// Run the client
main().catch((error) => {
  console.error('Client error:', error);
  process.exit(1);
});
