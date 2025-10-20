# Command Center Service

Central routing hub for the Kafka Request-Reply ecosystem. Acts as an intelligent message router with schema registry and metadata logging.

## Overview

The Command Center sits between publishers and workers, providing:

```
Publishers → Command Center → Workers
                  ↓
          Schema Registry
                  ↓
    Metadata Logging (NO PAYLOAD)
```

## Features

- **Centralized Routing**: All publishers send to one inbox topic, Command Center routes to correct worker topics
- **Schema Registry**: Manages routes and service information with Cassandra persistence
- **Metadata Logging**: Logs routing metadata (request_id, worker_name, timestamps, routing info) **WITHOUT PAYLOAD**
- **Dynamic Route Management**: Add/remove/enable/disable routes at runtime
- **Service Discovery**: Track service information, versions, and endpoints
- **Performance Monitoring**: Track routing statistics and processing times

## Architecture

### Components

1. **Command Center**: Main routing service
   - Listens on `command-center-inbox` topic
   - Routes messages based on `worker_name`
   - Logs metadata to Cassandra

2. **Schema Registry**: Route and service management
   - Stores route configurations
   - Manages service information
   - Persists to Cassandra for durability

3. **Metadata Logger**: Routing metadata tracking
   - Logs every routing operation
   - Excludes payload for privacy/performance
   - Provides query APIs for analytics

### Data Flow

```
1. Publisher sends message to 'command-center-inbox'
   - Message includes: worker_name, request_id, encrypted payload

2. Command Center receives message
   - Looks up route by worker_name in Schema Registry
   - Validates route is enabled

3. Command Center routes message
   - Forwards to target topic
   - Preserves encryption
   - Logs metadata (NO PAYLOAD)

4. Worker receives from target topic
   - Processes normally
   - Sends reply if needed
```

## Quick Start

### 1. Start Command Center

```bash
# Set encryption key (must match workers/publishers)
export ENCRYPTION_KEY="your-64-char-hex-key"

# Start Command Center
bun run command-center/index.ts
```

### 2. Configure Routes

Routes are configured in command-center/index.ts:164-224 or via API:

```typescript
const route: RouteConfig = {
  routeId: 'route-001',
  sourcePublisher: 'my-publisher',  // Publisher identifier
  targetTopic: 'my-worker-topic',   // Target topic
  serviceInfo: {
    serviceName: 'my-service',
    version: '1.0.0',
    description: 'My awesome service',
  },
  enabled: true,
  createdAt: new Date(),
  updatedAt: new Date(),
};

await schemaRegistry.registerRoute(route);
```

### 3. Send Messages via Command Center

Publishers send to `command-center-inbox` with `worker_name`:

```typescript
const message = {
  request_id: generateRequestId(),
  worker_name: 'my-publisher',  // Identifies which route to use
  data: encrypted.data,
  iv: encrypted.iv,
  tag: encrypted.tag,
};

await kafka.sendMessage('command-center-inbox', JSON.stringify(message));
```

## Configuration

```typescript
interface CommandCenterConfig {
  kafka: {
    brokers: string[];
    clientId: string;
    groupId: string;
  };
  cassandra: {
    contactPoints: string[];
    localDataCenter: string;
    keyspace: string;
  };
  encryption: {
    encryptionKey: string;
  };
  commandCenter: {
    inboxTopic: string;          // Default: 'command-center-inbox'
    enableAutoRouting: boolean;   // Enable automatic routing
    defaultTimeout: number;       // Default timeout in ms
  };
}
```

## Schema Registry

### Route Configuration

```typescript
interface RouteConfig {
  routeId: string;              // Unique route identifier
  sourcePublisher: string;      // Publisher/worker name
  targetTopic: string;          // Kafka topic to route to
  serviceInfo: ServiceInfo;     // Associated service info
  enabled: boolean;             // Enable/disable routing
  createdAt: Date;
  updatedAt: Date;
}
```

### Service Information

```typescript
interface ServiceInfo {
  serviceName: string;
  version: string;
  description?: string;
  endpoint?: string;            // Service HTTP endpoint
  tags?: string[];              // Tags for categorization
  createdAt: Date;
  updatedAt: Date;
}
```

### Schema Registry API

```typescript
const registry = commandCenter.getSchemaRegistry();

// Register service
await registry.registerService(serviceInfo);

// Register route
await registry.registerRoute(routeConfig);

// Enable/disable route
await registry.updateRouteStatus('publisher-name', false);

// Get route
const route = registry.getRoute('publisher-name');

// Get all routes
const routes = registry.getAllRoutes();

// Delete route
await registry.deleteRoute('publisher-name');
```

## Metadata Logging

### What Gets Logged

The metadata logger captures routing information **WITHOUT PAYLOAD**:

```typescript
interface RouteMetadata {
  request_id: string;           // Request UUID
  worker_name: string;          // Publisher identifier
  timestamp: Date;              // When routing occurred
  source_topic: string;         // Inbox topic
  target_topic: string;         // Routed-to topic
  route_id: string;             // Route identifier
  message_type: 'request' | 'response';
  success: boolean;             // Routing success
  error?: string;               // Error if failed
  processing_time_ms?: number;  // Routing duration
}
```

### Metadata Logger API

```typescript
const logger = commandCenter.getMetadataLogger();

// Get metadata by request_id
const metadata = await logger.getByRequestId('uuid');

// Get metadata by worker_name
const workerLogs = await logger.getByWorkerName('publisher-name', 100);

// Get metadata by time range
const logs = await logger.getByTimeRange(startTime, endTime);

// Get route statistics
const stats = await logger.getRouteStats('route-001');
// Returns: { totalMessages, successCount, failureCount, avgProcessingTime }
```

## Database Schema

### Cassandra Tables

#### routes table
```sql
CREATE TABLE command_center.routes (
  route_id text PRIMARY KEY,
  source_publisher text,
  target_topic text,
  service_name text,
  enabled boolean,
  created_at timestamp,
  updated_at timestamp
);
```

#### services table
```sql
CREATE TABLE command_center.services (
  service_name text PRIMARY KEY,
  version text,
  description text,
  endpoint text,
  tags set<text>,
  created_at timestamp,
  updated_at timestamp
);
```

#### routing_metadata table
```sql
CREATE TABLE command_center.routing_metadata (
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
) WITH CLUSTERING ORDER BY (timestamp DESC);
```

## Running Examples

### Example 1: Run Complete System

```bash
# Terminal 1: Start Kafka & Cassandra
docker-compose up -d

# Terminal 2: Start Command Center
export ENCRYPTION_KEY="your-key"
bun run command-center/index.ts

# Terminal 3: Start Worker (receives from target topics)
bun run src/examples/worker.ts

# Terminal 4: Send messages via Command Center
bun run command-center/examples/publisher-with-command-center.ts
```

### Example 2: Query Metadata

```typescript
import { CommandCenter } from './command-center/index.js';

const commandCenter = new CommandCenter(config);
await commandCenter.initialize();

const logger = commandCenter.getMetadataLogger();

// Get recent logs
const now = new Date();
const hourAgo = new Date(now.getTime() - 3600000);
const logs = await logger.getByTimeRange(hourAgo, now);

console.log(`Found ${logs.length} routing operations in last hour`);

// Get statistics
const stats = await logger.getRouteStats('route-calc-001');
console.log('Route stats:', stats);
```

## Benefits

### 1. Centralized Routing
- Publishers don't need to know worker topic names
- Easy to reconfigure routing without changing publisher code

### 2. Service Discovery
- Track all services and their information
- Version management
- Endpoint discovery

### 3. Metadata Tracking
- Full audit trail of all routing operations
- Performance monitoring
- Debugging and troubleshooting
- **Privacy-preserving** (no payload stored)

### 4. Dynamic Configuration
- Enable/disable routes without restarting
- Add new routes on the fly
- Update service information

### 5. Scalability
- Command Center can be horizontally scaled
- Cassandra provides distributed storage
- Kafka handles high throughput

## Best Practices

1. **Use meaningful worker_name**: Make it descriptive of the publisher/service
2. **Version your services**: Track service versions in ServiceInfo
3. **Monitor metadata logs**: Set up alerts for routing failures
4. **Clean up old routes**: Remove unused routes from registry
5. **Use tags**: Tag services for easy categorization and discovery

## Troubleshooting

### Route not found
```
Error: No route found for publisher: my-publisher
```
**Solution**: Register route in Schema Registry first

### Route disabled
```
Error: Route disabled for publisher: my-publisher
```
**Solution**: Enable route via `updateRouteStatus()`

### No messages being routed
- Check Command Center is running and connected
- Verify route exists and is enabled
- Check publishers are sending to 'command-center-inbox'
- Verify worker_name matches route's sourcePublisher

## Performance Considerations

- **Routing overhead**: ~1-5ms per message (logging + routing)
- **Cassandra writes**: Async, non-blocking
- **Schema Registry**: In-memory cache, fast lookups
- **Scalability**: Can handle 10k+ messages/sec per instance

## Security

- **Encryption preserved**: Payload stays encrypted through routing
- **No payload logging**: Only metadata logged to Cassandra
- **request_id visible**: Needed for distributed tracing
- **Access control**: Implement authentication for Schema Registry API

## Future Enhancements

- REST API for route management
- GraphQL API for querying metadata
- Real-time monitoring dashboard
- Circuit breaker for failed routes
- A/B testing and canary deployments
- Rate limiting per publisher
- Message transformation/enrichment

## License

MIT
