# Command Center Quick Start Guide

## What is Command Center?

Command Center is a central routing hub that sits between publishers and workers. Instead of publishers sending messages directly to worker topics, they send to Command Center, which routes messages based on a schema registry.

## Architecture

```
┌────────────┐
│ Publisher  │
│  (calc)    │────┐
└────────────┘    │
                  │    ┌─────────────────┐     ┌──────────┐
┌────────────┐    │    │ Command Center  │────>│  Worker  │
│ Publisher  │────┼───>│                 │     │ (calc)   │
│  (user)    │    │    │ Schema Registry │     └──────────┘
└────────────┘    │    │                 │
                  │    │ Metadata Logger │     ┌──────────┐
┌────────────┐    │    └─────────────────┘────>│  Worker  │
│ Publisher  │────┘                             │ (user)   │
│  (other)   │                                  └──────────┘
└────────────┘
```

## Key Features

1. **Centralized Routing**: Publishers send to one topic, Command Center routes to correct workers
2. **Schema Registry**: Stores route configurations (publisher → worker topic mappings)
3. **Metadata Logging**: Logs routing info WITHOUT payload (privacy + performance)
4. **Service Discovery**: Track service versions, endpoints, and metadata

## Quick Setup

### Step 1: Start Infrastructure

```bash
# Start Kafka and Cassandra
docker-compose up -d

# Generate encryption key (ONE TIME - share across all services)
bun run -e "import {generateEncryptionKey} from './src/index.js'; console.log(generateEncryptionKey())"

# Export key
export ENCRYPTION_KEY="your-generated-key-here"
```

### Step 2: Start Command Center

```bash
# Terminal 1: Start Command Center
bun run command-center
```

This will:
- Connect to Kafka and Cassandra
- Initialize Schema Registry
- Register example routes (calc-publisher → calculate, user-publisher → get-user)
- Start listening on `command-center-inbox`

### Step 3: Start Workers

```bash
# Terminal 2: Start existing worker
bun run example:worker
```

Workers listen on their specific topics (calculate, get-user, etc.) just like before.

### Step 4: Send Messages via Command Center

```bash
# Terminal 3: Send messages through Command Center
bun run example:publisher-cc
```

## How It Works

### Before (Direct)
```
Publisher → calculate topic → Worker
```

### After (With Command Center)
```
Publisher → command-center-inbox → Command Center → calculate topic → Worker
                                         ↓
                                   Metadata Logged
```

## Message Format

Publishers send to `command-center-inbox` with this format:

```typescript
{
  request_id: "uuid",
  worker_name: "calc-publisher",  // <-- This determines routing
  data: "encrypted...",
  iv: "...",
  tag: "..."
}
```

Command Center:
1. Looks up route by `worker_name` in schema registry
2. Routes to target topic (e.g., "calculate")
3. Logs metadata (request_id, worker_name, timestamps, etc.) - NO PAYLOAD

## Schema Registry

Routes are defined in command-center/index.ts:164-224:

```typescript
{
  routeId: 'route-calc-001',
  sourcePublisher: 'calc-publisher',  // Publisher identifier
  targetTopic: 'calculate',           // Target worker topic
  serviceInfo: {
    serviceName: 'calculator-service',
    version: '1.0.0',
    description: 'Mathematical calculation service',
  },
  enabled: true,
}
```

## Metadata Logging

Every routing operation logs to Cassandra `routing_metadata` table:

```sql
- request_id: UUID
- worker_name: "calc-publisher"
- timestamp: 2025-10-17T15:30:00
- source_topic: "command-center-inbox"
- target_topic: "calculate"
- route_id: "route-calc-001"
- message_type: "request"
- success: true
- processing_time_ms: 3
```

**Note**: Payload is NOT logged for privacy and performance.

## Scripts

```bash
# Start Command Center
bun run command-center

# Start worker (receives from target topics)
bun run example:worker

# Send via Command Center
bun run example:publisher-cc

# Build TypeScript
bun run build
```

## Testing the System

### Complete Flow Test

```bash
# 1. Start infrastructure
docker-compose up -d

# 2. Set encryption key (same for all)
export ENCRYPTION_KEY="your-key"

# 3. Start Command Center (Terminal 1)
bun run command-center

# 4. Start Worker (Terminal 2)
bun run example:worker

# 5. Send messages (Terminal 3)
bun run example:publisher-cc
```

You should see:
- Terminal 3: Publisher sends to command-center-inbox
- Terminal 1: Command Center routes calc-publisher → calculate
- Terminal 2: Worker processes and responds
- Cassandra: Metadata logged

## Querying Metadata

```typescript
import { CommandCenter } from './command-center/index.js';

const cc = new CommandCenter(config);
await cc.initialize();

const logger = cc.getMetadataLogger();

// Get logs for specific request
const logs = await logger.getByRequestId('uuid');

// Get logs for publisher
const publisherLogs = await logger.getByWorkerName('calc-publisher');

// Get route statistics
const stats = await logger.getRouteStats('route-calc-001');
console.log(stats); // { totalMessages, successCount, failureCount, avgProcessingTime }
```

## Benefits

1. **Decoupling**: Publishers don't need to know worker topic names
2. **Flexibility**: Change routing without modifying publisher code
3. **Observability**: Full audit trail of all routing operations
4. **Service Discovery**: Track all services and their metadata
5. **Privacy**: Payload never logged, only routing metadata

## File Structure

```
command-center/
├── types/
│   └── index.ts              # TypeScript definitions
├── lib/
│   ├── schema-registry.ts    # Route management
│   ├── metadata-logger.ts    # Cassandra logging
│   └── command-center.ts     # Main routing service
├── examples/
│   └── publisher-with-command-center.ts
├── index.ts                  # Main entry point
└── README.md                 # Detailed documentation
```

## Next Steps

1. Review command-center/README.md for detailed API documentation
2. Customize routes in command-center/index.ts
3. Add your own publishers with unique worker_name values
4. Query metadata for monitoring and analytics
5. Scale horizontally by running multiple Command Center instances

## Troubleshooting

**Route not found**: Check schema registry has route for worker_name

**Messages not routing**: Verify Command Center is running and connected

**Worker not receiving**: Check worker topic matches route's targetTopic

**Different encryption**: All services must use same ENCRYPTION_KEY
