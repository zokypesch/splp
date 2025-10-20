# Kafka Request-Reply Messaging Library

A comprehensive TypeScript library for implementing Request-Reply patterns over Kafka with built-in encryption, tracing, and Cassandra logging. Built for Bun runtime.

## Features

- **Single-line Kafka connection** for both producer and consumer
- **Automatic request_id generation** using UUID v4 for distributed tracing
- **Transparent encryption/decryption** using AES-256-GCM (payload encrypted, request_id visible for tracing)
- **Simple handler registration** - users only write business logic
- **Automatic Cassandra logging** for all requests and responses
- **JSON payload support** with TypeScript type safety
- **Request-reply pattern** with timeout support
- **Concurrent request handling**

## Stack

- **Kafka** - Message broker for request-reply communication
- **Cassandra** - Distributed logging and tracing storage
- **Bun + TypeScript** - Runtime and type safety

## Installation

```bash
bun install
```

## Quick Start

### 1. Generate Encryption Key

```typescript
import { generateEncryptionKey } from './src/index.js';

const key = generateEncryptionKey();
console.log('Encryption Key:', key);
// Save this key securely - all services must use the same key!
```

### 2. Create a Worker (Consumer)

```typescript
import { MessagingClient } from './src/index.js';

const config = {
  kafka: {
    brokers: ['localhost:9092'],
    clientId: 'my-worker',
    groupId: 'worker-group',
  },
  cassandra: {
    contactPoints: ['localhost'],
    localDataCenter: 'datacenter1',
    keyspace: 'messaging',
  },
  encryption: {
    encryptionKey: process.env.ENCRYPTION_KEY, // 64-char hex string
  },
};

// Initialize client - single line setup!
const client = new MessagingClient(config);
await client.initialize();

// Register handler - only write your business logic!
client.registerHandler('calculate', async (requestId, payload) => {
  console.log(`Processing request ${requestId}`);

  const result = payload.a + payload.b;

  return { result };
});

// Start consuming - encryption/decryption is automatic!
await client.startConsuming(['calculate']);
```

### 3. Send Requests (Producer/Client)

```typescript
import { MessagingClient } from './src/index.js';

const client = new MessagingClient(config);
await client.initialize();

// Send request - encryption happens automatically!
const response = await client.request(
  'calculate',
  { a: 10, b: 5 },
  30000 // timeout in ms
);

console.log('Result:', response.result); // 15
```

## Architecture

```
┌─────────────┐      Encrypted      ┌──────────┐
│   Client    │ ───────────────────> │  Kafka   │
│ (Producer)  │    Request Topic     │          │
└─────────────┘                      └──────────┘
       │                                   │
       │                                   │
       │         ┌──────────────────────┐  │
       │         │   request_id: UUID   │  │
       │         │   (not encrypted)    │  │
       │         │                      │  │
       │         │   payload: {...}     │  │
       │         │   (AES-256-GCM)      │  │
       │         └──────────────────────┘  │
       │                                   v
       │                            ┌─────────────┐
       │                            │   Worker    │
       │                            │ (Consumer)  │
       │                            └─────────────┘
       │                                   │
       │                                   │ Automatic:
       │                                   │ 1. Decrypt
       │                                   │ 2. Process
       │                                   │ 3. Encrypt
       │                                   │ 4. Log to Cassandra
       │                                   │
       v                                   v
┌─────────────┐      Encrypted      ┌──────────┐
│   Client    │ <───────────────────│  Kafka   │
│  (Receives  │    Reply Topic       │          │
│   Reply)    │                      └──────────┘
└─────────────┘
       │
       v
┌──────────────┐
│  Cassandra   │  <- All requests/responses logged
│   Tracing    │     with request_id, timestamp,
└──────────────┘     payload, duration, etc.
```

## Project Structure

```
src/
├── lib/
│   ├── kafka/
│   │   └── kafka-wrapper.ts          # Kafka connection management
│   ├── crypto/
│   │   └── encryption.ts             # AES-256-GCM encryption
│   ├── logging/
│   │   └── cassandra-logger.ts       # Cassandra logging
│   ├── request-reply/
│   │   └── messaging-client.ts       # Main client implementation
│   └── utils/
│       └── request-id.ts             # UUID generation
├── types/
│   └── index.ts                      # TypeScript definitions
├── examples/
│   ├── worker.ts                     # Example consumer
│   └── client.ts                     # Example producer
└── index.ts                          # Main exports
```

## Configuration

### MessagingConfig

```typescript
interface MessagingConfig {
  kafka: {
    brokers: string[];           // Kafka broker addresses
    clientId: string;            // Unique client identifier
    groupId?: string;            // Consumer group ID
  };
  cassandra: {
    contactPoints: string[];     // Cassandra nodes
    localDataCenter: string;     // Data center name
    keyspace: string;            // Keyspace for logs
  };
  encryption: {
    encryptionKey: string;       // 32-byte (64 hex chars) AES-256 key
  };
}
```

## API Reference

### MessagingClient

#### `initialize(): Promise<void>`
Initialize Kafka connections and Cassandra logger. Must be called before any other operations.

#### `registerHandler<TRequest, TResponse>(topic: string, handler: RequestHandler): void`
Register a handler function for a specific topic. The handler receives:
- `requestId: string` - UUID for tracing
- `payload: TRequest` - Decrypted request payload

Returns: `Promise<TResponse>` - Response to send back

#### `startConsuming(topics: string[]): Promise<void>`
Start consuming messages from specified topics. Automatically handles:
- Message decryption
- Handler execution
- Response encryption
- Reply sending
- Cassandra logging

#### `request<TRequest, TResponse>(topic: string, payload: TRequest, timeoutMs?: number): Promise<TResponse>`
Send a request and wait for reply. Automatically handles:
- Request ID generation
- Payload encryption
- Message sending
- Reply waiting
- Response decryption
- Cassandra logging

#### `getLogger(): CassandraLogger`
Get logger instance for manual queries.

#### `close(): Promise<void>`
Disconnect all connections gracefully.

### CassandraLogger

#### `getLogsByRequestId(requestId: string): Promise<LogEntry[]>`
Query all logs for a specific request ID.

#### `getLogsByTimeRange(startTime: Date, endTime: Date): Promise<LogEntry[]>`
Query logs within a time range.

## Running Examples

### Prerequisites

1. **Kafka**: Running on `localhost:9092`
   ```bash
   docker run -d -p 9092:9092 apache/kafka
   ```

2. **Cassandra**: Running on `localhost:9042`
   ```bash
   docker run -d -p 9042:9042 cassandra:latest
   ```

3. **Generate shared encryption key**:
   ```bash
   bun run -e "import {generateEncryptionKey} from './src/index.js'; console.log(generateEncryptionKey())"
   ```
   Set this as `ENCRYPTION_KEY` environment variable for both worker and client.

### Run Worker

```bash
export ENCRYPTION_KEY="your-64-char-hex-key"
bun run src/examples/worker.ts
```

### Run Client

```bash
export ENCRYPTION_KEY="your-64-char-hex-key"
bun run src/examples/client.ts
```

## Security Features

### Encryption
- **Algorithm**: AES-256-GCM (Galois/Counter Mode)
- **Key Size**: 256 bits (32 bytes)
- **Authentication**: Built-in authentication tag
- **IV**: Random 16-byte initialization vector per message

### What's Encrypted
- Request and response payloads
- All business data

### What's NOT Encrypted (for tracing)
- `request_id` - Needed for distributed tracing and correlation

## Logging and Tracing

All requests and responses are automatically logged to Cassandra with:
- `request_id` - UUID for correlation
- `timestamp` - Message timestamp
- `type` - "request" or "response"
- `topic` - Kafka topic
- `payload` - Original payload (stored encrypted)
- `success` - Whether request succeeded
- `error` - Error message if failed
- `duration_ms` - Processing duration

### Query Examples

```typescript
const logger = client.getLogger();

// Get all logs for a request
const logs = await logger.getLogsByRequestId('uuid-here');

// Get logs from last hour
const endTime = new Date();
const startTime = new Date(endTime.getTime() - 60 * 60 * 1000);
const recentLogs = await logger.getLogsByTimeRange(startTime, endTime);
```

## Development

### Build

```bash
bun run build
```

### Watch Mode

```bash
bun run dev
```

## Type Safety

Full TypeScript support with generic types:

```typescript
interface MyRequest {
  userId: string;
  action: string;
}

interface MyResponse {
  success: boolean;
  data: any;
}

// Type-safe handler
client.registerHandler<MyRequest, MyResponse>('my-topic', async (id, req) => {
  // req is typed as MyRequest
  return { success: true, data: {} }; // Must return MyResponse
});

// Type-safe request
const response = await client.request<MyRequest, MyResponse>(
  'my-topic',
  { userId: '123', action: 'get' }
);
// response is typed as MyResponse
```

## Error Handling

### Timeout
Requests automatically timeout if no reply is received:

```typescript
try {
  const response = await client.request('topic', payload, 5000); // 5s timeout
} catch (error) {
  console.error('Request timeout or failed:', error);
}
```

### Handler Errors
Errors in handlers are automatically caught and logged:

```typescript
client.registerHandler('topic', async (id, payload) => {
  if (!payload.valid) {
    throw new Error('Invalid payload'); // Automatically logged to Cassandra
  }
  return result;
});
```

## Best Practices

1. **Use environment variables** for encryption keys
2. **Share the same encryption key** across all services
3. **Use descriptive topic names** for better tracing
4. **Implement proper error handling** in handlers
5. **Set appropriate timeouts** based on expected processing time
6. **Monitor Cassandra logs** for system health
7. **Use TypeScript generics** for type safety

## License

MIT
