# Example: Chained Service Communication

Demonstrates a complete request-reply chain using Command Center routing:

```
Publisher → Command Center → Service 1 → Command Center → Service 2
```

## Architecture

### Message Flow

1. **Publisher** creates an order and sends to **Command Center**
   - worker_name: `initial-publisher`
   - Command Center routes to: `service-1-topic`

2. **Service 1** receives, validates, and sends back to **Command Center**
   - Processes order (validation, enrichment)
   - worker_name: `service-1-publisher`
   - Command Center routes to: `service-2-topic`

3. **Service 2** receives and completes processing
   - Final fulfillment
   - Saves to database
   - Sends confirmations

### Key Features

- **Chained Routing**: Services communicate through Command Center
- **Encrypted Messages**: All payloads encrypted end-to-end
- **Metadata Logging**: Every hop logged to Cassandra (NO PAYLOAD)
- **Automatic Routing**: Command Center handles all routing based on worker_name
- **1 Week TTL**: All Kafka and Cassandra data expires after 1 week

## Project Structure

```
example-bun/
├── publisher/
│   └── index.ts              # Initial publisher
├── service_1/
│   └── index.ts              # Validation service (receives & re-publishes)
├── service_2/
│   └── index.ts              # Fulfillment service (final receiver)
├── command-center-config.ts  # Command Center with route configuration
└── README.md
```

## Quick Start

### Prerequisites

1. Kafka running on `localhost:9092`
2. Cassandra running on `localhost:9042`
3. Same ENCRYPTION_KEY for all services

### Step 1: Start Infrastructure

```bash
# From project root
docker-compose up -d

# Wait for services to be healthy (30 seconds)
sleep 30

# Create Kafka topics
./create-topics.sh
```

### Step 2: Generate Encryption Key

```bash
# Generate once and use everywhere
cd /mnt/d/project/splp/splp-bun
bun run -e "import {generateEncryptionKey} from './src/index.js'; console.log(generateEncryptionKey())"

# Export the key
export ENCRYPTION_KEY="your-generated-key-here"
```

### Step 3: Start Command Center

```bash
# Terminal 1
cd /mnt/d/project/splp/example-bun
bun run command-center-config.ts
```

You should see:
```
✓ Route 1: initial-publisher → service-1-topic
✓ Route 2: service-1-publisher → service-2-topic
```

### Step 4: Start Service 1

```bash
# Terminal 2
cd /mnt/d/project/splp/example-bun/service_1
bun run index.ts
```

### Step 5: Start Service 2

```bash
# Terminal 3
cd /mnt/d/project/splp/example-bun/service_2
bun run index.ts
```

### Step 6: Run Publisher

```bash
# Terminal 4
cd /mnt/d/project/splp/example-bun/publisher
bun run index.ts
```

## Expected Output

### Publisher Output
```
Publisher Service Starting
✓ Publisher connected to Kafka

Creating new order request:
  Request ID: <uuid>
  Order: {
    "orderId": "ORD-1234567890",
    "userId": "user-12345",
    "amount": 299.99,
    "items": ["laptop", "mouse", "keyboard"]
  }

✓ Message sent to Command Center
  → Command Center will route to service_1
```

### Service 1 Output
```
📥 Received message from Command Center
  Request ID: <uuid>
  Order: ORD-1234567890
  User: user-12345
  Amount: $299.99

🔄 Processing order...
  Status: APPROVED
  Processed by: service-1

📤 Sending to Command Center...
✓ Sent to Command Center for routing to service_2
```

### Service 2 Output
```
📬 FINAL MESSAGE RECEIVED
📋 Order Details:
  Order ID: ORD-1234567890
  User ID: user-12345
  Amount: $299.99

✅ Processing History:
  Processed by: service-1
  Validation: APPROVED

✅ ORDER APPROVED - Saving to database
✅ Sending confirmation email to user
✅ Updating inventory

🎉 PROCESSING CHAIN COMPLETED!
   Publisher → Service 1 → Service 2
```

## Message Details

### Publisher Message Structure
```json
{
  "request_id": "uuid",
  "worker_name": "initial-publisher",
  "data": "encrypted...",
  "iv": "...",
  "tag": "..."
}
```

### Service 1 → Command Center
```json
{
  "request_id": "uuid",
  "worker_name": "service-1-publisher",
  "data": "encrypted...",
  "iv": "...",
  "tag": "..."
}
```

## Metadata Logging

Every routing operation is logged to Cassandra:

```sql
SELECT * FROM command_center.routing_metadata;
```

### What's Logged (NO PAYLOAD):
- request_id
- worker_name (publisher identifier)
- timestamp
- source_topic (command-center-inbox)
- target_topic (service-1-topic or service-2-topic)
- route_id
- message_type
- success/failure
- processing_time_ms

## Data Retention (TTL)

### Kafka
- Message retention: **1 week** (604800000 ms)
- Automatic cleanup after 7 days

### Cassandra
- Table TTL: **1 week** (604800 seconds)
- Automatic data expiration after 7 days

Both configured in `/mnt/d/project/splp/docker-compose.yml`

## Customization

### Add More Services

1. Create new service folder (e.g., `service_3/`)
2. Add route in `command-center-config.ts`:
```typescript
const route3: RouteConfig = {
  routeId: 'route-service2-to-service3',
  sourcePublisher: 'service-2-publisher',
  targetTopic: 'service-3-topic',
  serviceInfo: {...},
  enabled: true,
  ...
};
```

### Modify Processing Logic

Edit the service files:
- `service_1/index.ts` - Validation logic
- `service_2/index.ts` - Fulfillment logic

### Change TTL

Edit `docker-compose.yml`:
```yaml
# Kafka - change retention
KAFKA_LOG_RETENTION_MS: 604800000  # 1 week in ms

# Cassandra - change in table creation
AND default_time_to_live = 604800  # 1 week in seconds
```

## Monitoring

### View Kafka Topics
```bash
# Access Kafka UI
open http://localhost:8080
```

### Query Cassandra Logs
```bash
docker exec -it cassandra cqlsh

# View routing metadata
SELECT * FROM command_center.routing_metadata LIMIT 10;

# View by worker
SELECT * FROM command_center.routing_metadata
WHERE worker_name = 'initial-publisher'
ALLOW FILTERING;
```

### Check Route Stats
```typescript
const logger = commandCenter.getMetadataLogger();
const stats = await logger.getRouteStats('route-publisher-to-service1');
console.log(stats);
// { totalMessages, successCount, failureCount, avgProcessingTime }
```

## Troubleshooting

### Service not receiving messages
1. Check Command Center is running
2. Verify route exists: `registry.getRoute('worker-name')`
3. Check worker_name matches route config
4. Ensure ENCRYPTION_KEY is same across all services

### Decryption errors
- All services must use identical ENCRYPTION_KEY
- Re-export the key in all terminals

### Cassandra connection failed
```bash
# Check Cassandra health
docker-compose ps cassandra

# View logs
docker-compose logs cassandra
```

### Kafka connection failed
```bash
# Check Kafka health
docker-compose ps kafka

# View logs
docker-compose logs kafka
```

## Advanced Usage

### Request Tracking

Query full message path by request_id:
```typescript
const metadata = await logger.getByRequestId(requestId);
// Shows all hops: publisher → service1 → service2
```

### Performance Analysis

Get processing time for each hop:
```typescript
metadata.forEach(log => {
  console.log(`${log.worker_name}: ${log.processing_time_ms}ms`);
});
```

### Route Management

Enable/disable routes dynamically:
```typescript
await registry.updateRouteStatus('initial-publisher', false);  // Disable
await registry.updateRouteStatus('initial-publisher', true);   // Enable
```

## Security

- **Encryption**: AES-256-GCM for all payloads
- **Metadata Only**: Cassandra logs contain NO sensitive payload data
- **request_id visible**: Needed for distributed tracing
- **Shared Key**: All services must use same ENCRYPTION_KEY

## License

MIT
