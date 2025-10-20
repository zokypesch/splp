# Parallel Processing Architecture

## Overview

This implementation demonstrates **parallel processing** where **multiple services receive the SAME message** and process it simultaneously with different logic.

## Key Concept: Consumer Groups

All Service 1 variants listen to the **SAME Kafka topic** (`service-1-topic`) but use **DIFFERENT consumer groups**:

| Service | Topic | Consumer Group | Purpose |
|---------|-------|----------------|---------|
| Service 1 | `service-1-topic` | `service-1-group` | Order Validation |
| Service 1A | `service-1-topic` | `service-1a-group` | Inventory Check |
| Service 1B | `service-1-topic` | `service-1b-group` | Fraud Detection |
| Service 1C | `service-1-topic` | `service-1c-group` | Pricing & Discount |

**Different consumer groups = Each service gets its own copy of every message**

## Message Flow

```
                          ┌──> Service 1  (validation)     ──┐
                          │    Group: service-1-group        │
                          │                                   │
                          ├──> Service 1A (inventory)      ──┤
Publisher                 │    Group: service-1a-group       │         Service 2
(1 message) ──> CC ───────┼──> Service 1B (fraud)          ──┼──> CC ──> (receives 4
                          │    Group: service-1b-group       │          results)
                          │                                   │
                          └──> Service 1C (pricing)        ──┘
                               Group: service-1c-group

     Topic: command-center-inbox     service-1-topic            command-center-inbox    service-2-topic
     worker_name: initial-publisher  (ALL receive same msg)     worker_name: service-1*-publisher
```

## How It Works

### 1. Publisher sends ONE message
```typescript
const message = {
  request_id: 'uuid-123',
  worker_name: 'initial-publisher',
  data: encrypted.data,
  // ...
};
```

### 2. Command Center routes to service-1-topic
Based on route configuration, Command Center forwards the message to `service-1-topic`.

### 3. ALL Service 1 variants receive the message
Because they use **different consumer groups**, Kafka delivers the same message to ALL of them:
- Service 1 (group: `service-1-group`) receives copy 1
- Service 1A (group: `service-1a-group`) receives copy 2
- Service 1B (group: `service-1b-group`) receives copy 3
- Service 1C (group: `service-1c-group`) receives copy 4

### 4. Each service processes independently
- **Service 1**: Validates order (approved/rejected)
- **Service 1A**: Checks inventory (available/limited/unavailable)
- **Service 1B**: Detects fraud (pass/warning/fail with risk score)
- **Service 1C**: Calculates pricing (applies discounts and taxes)

### 5. Each sends results to Service 2
All services forward their processed results back to Command Center with different `worker_name`:
- `worker_name: 'service-1-publisher'`
- `worker_name: 'service-1a-publisher'`
- `worker_name: 'service-1b-publisher'`
- `worker_name: 'service-1c-publisher'`

### 6. Service 2 receives 4 results
Service 2 receives **4 separate messages** for the same `request_id`, each with different processing results.

## Running the Example

### Start Infrastructure
```bash
docker-compose up -d
./create-topics.sh
```

### Start Services
```bash
cd example-bun

# Terminal 1: Command Center
./run-example.sh command-center

# Terminal 2: Service 1 (Validation)
./run-example.sh service-1

# Terminal 3: Service 1A (Inventory)
./run-example.sh service-1a

# Terminal 4: Service 1B (Fraud)
./run-example.sh service-1b

# Terminal 5: Service 1C (Pricing)
./run-example.sh service-1c

# Terminal 6: Service 2 (Final)
./run-example.sh service-2
```

### Send Test Message
```bash
# Terminal 7: Send ONE message
./run-example.sh publisher

# OR use specialized test data:
./run-example.sh publisher-a  # Test inventory check
./run-example.sh publisher-b  # Test fraud (high amount)
./run-example.sh publisher-c  # Test pricing (discount eligible)
```

## Expected Output

### Publisher sends 1 message:
```
✓ Message sent to Command Center
  → Command Center routes to service-1-topic
  → ALL Service 1 variants (1, 1A, 1B, 1C) receive the SAME message
```

### Command Center routes 1 message:
```
Routing message from initial-publisher (uuid-123)
Routed uuid-123: initial-publisher -> service-1-topic (3ms)
```

### ALL Service 1 variants receive:
```
[Service 1]  📥 Received message from Command Center
             Request ID: uuid-123
             Status: APPROVED

[Service 1A] 📥 Received message from Command Center
             Request ID: uuid-123
             Inventory Status: AVAILABLE

[Service 1B] 📥 Received message from Command Center
             Request ID: uuid-123
             Fraud Check: PASS (Risk: 25)

[Service 1C] 📥 Received message from Command Center
             Request ID: uuid-123
             Final Amount: $323.99 (10% discount applied)
```

### Service 2 receives 4 messages:
```
[Service 2] 📥 Received from service-1 (uuid-123)
           Result: Order validated and approved

[Service 2] 📥 Received from service-1a (uuid-123)
           Result: Inventory available for all items

[Service 2] 📥 Received from service-1b (uuid-123)
           Result: Fraud check passed (Risk: 25/100)

[Service 2] 📥 Received from service-1c (uuid-123)
           Result: Final pricing: $323.99 after discount
```

## Route Configuration

Only **5 routes** needed (simplified):

1. **Publisher → Service 1 Topic**
   - `initial-publisher` → `service-1-topic`
   - All Service 1 variants receive this

2-5. **Service 1 Variants → Service 2**
   - `service-1-publisher` → `service-2-topic`
   - `service-1a-publisher` → `service-2-topic`
   - `service-1b-publisher` → `service-2-topic`
   - `service-1c-publisher` → `service-2-topic`

## Benefits

1. **True Parallel Processing**: All services process simultaneously
2. **Independent Scaling**: Each service can scale independently
3. **Fault Isolation**: One service failure doesn't affect others
4. **Different Processing Logic**: Each service applies specialized business logic
5. **Comprehensive Results**: Service 2 receives complete analysis from all perspectives

## Use Cases

This pattern is ideal for:
- **Risk Assessment**: Multiple checks (fraud, compliance, credit)
- **Multi-faceted Validation**: Different validation rules applied in parallel
- **Enrichment Pipeline**: Multiple services enrich data simultaneously
- **Notification Fanout**: Send to multiple channels (email, SMS, push)
- **Analytics**: Multiple analytics engines process same event

## Monitoring

### Check Consumer Group Status
```bash
docker exec kafka /opt/kafka/bin/kafka-consumer-groups.sh \
  --bootstrap-server localhost:9092 \
  --describe --group service-1-group

docker exec kafka /opt/kafka/bin/kafka-consumer-groups.sh \
  --bootstrap-server localhost:9092 \
  --describe --group service-1a-group
```

### Query Routing Metadata
```bash
docker exec -it cassandra cqlsh
USE command_center;

-- See all routes for a specific request
SELECT * FROM routing_metadata WHERE request_id = 'uuid-123';

-- Count messages by worker
SELECT worker_name, COUNT(*) FROM routing_metadata GROUP BY worker_name;
```

## Key Differences from Previous Implementation

### Before (Separate Topics):
- Publisher A → service-1a-topic → Service 1A only
- Publisher B → service-1b-topic → Service 1B only
- Publisher C → service-1c-topic → Service 1C only

**Problem**: Each publisher targets ONE service

### After (Shared Topic with Different Groups):
- Publisher → service-1-topic → ALL Service 1 variants

**Benefit**: ONE message processed by ALL services in parallel

## Performance Considerations

- **Processing Time**: Services process in parallel, so total time ≈ slowest service
- **Message Volume**: Each message multiplied by number of consumer groups
- **Network Traffic**: Higher than sequential processing but lower than separate messages
- **Storage**: Cassandra logs metadata for each routing operation

## Troubleshooting

**Only one service receiving messages?**
- Check consumer groups are different: `docker exec kafka kafka-consumer-groups.sh --list`

**Service 2 only receives from one service?**
- Verify all Service 1 variants are running
- Check Command Center routes are registered

**Message not reaching any Service 1 variant?**
- Verify topic exists: `docker exec kafka kafka-topics.sh --list`
- Check Command Center routing logs

## Next Steps

Consider adding:
- Aggregation service that waits for all 4 results before proceeding
- Timeout handling for slow services
- Correlation ID tracking across all services
- Circuit breaker for failed services
