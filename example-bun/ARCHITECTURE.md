# Architecture Diagram

## Message Flow

```
┌──────────────┐
│  Publisher   │
│              │
│ Creates      │
│ Order        │
└──────┬───────┘
       │
       │ worker_name: "initial-publisher"
       │ payload: { orderId, userId, amount, items }
       │
       ▼
┌──────────────────────────────────┐
│      Command Center              │
│                                  │
│  Schema Registry:                │
│  ✓ initial-publisher →           │
│    service-1-topic               │
└──────────────┬───────────────────┘
               │
               │ Routes to: service-1-topic
               │
               ▼
       ┌──────────────┐
       │  Service 1   │
       │              │
       │ Validates    │
       │ Order        │
       └──────┬───────┘
              │
              │ worker_name: "service-1-publisher"
              │ payload: { ...order, processedBy, validationStatus }
              │
              ▼
       ┌──────────────────────────────────┐
       │      Command Center              │
       │                                  │
       │  Schema Registry:                │
       │  ✓ service-1-publisher →         │
       │    service-2-topic               │
       └──────────────┬───────────────────┘
                      │
                      │ Routes to: service-2-topic
                      │
                      ▼
              ┌──────────────┐
              │  Service 2   │
              │              │
              │ Fulfills     │
              │ Order        │
              └──────────────┘
                      │
                      │ Saves to DB
                      │ Sends Email
                      │ Updates Inventory
                      ▼
                  [COMPLETE]
```

## Component Details

### Publisher
- **Role**: Creates initial order request
- **Sends to**: command-center-inbox
- **worker_name**: initial-publisher
- **Action**: Encrypts order data and sends

### Command Center (First Hop)
- **Receives from**: Publisher
- **Looks up**: worker_name "initial-publisher" in Schema Registry
- **Routes to**: service-1-topic
- **Logs**: Metadata to Cassandra (NO PAYLOAD)

### Service 1 (Validation)
- **Receives from**: service-1-topic
- **Processes**:
  - Decrypts order
  - Validates amount (0 < amount < 10000)
  - Enriches with processing metadata
- **Sends to**: command-center-inbox
- **worker_name**: service-1-publisher

### Command Center (Second Hop)
- **Receives from**: Service 1
- **Looks up**: worker_name "service-1-publisher" in Schema Registry
- **Routes to**: service-2-topic
- **Logs**: Metadata to Cassandra (NO PAYLOAD)

### Service 2 (Fulfillment)
- **Receives from**: service-2-topic
- **Processes**:
  - Decrypts validated order
  - Saves to database
  - Sends confirmation email
  - Updates inventory
- **Completes**: Processing chain

## Data Flow

### 1. Publisher → Command Center
```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "worker_name": "initial-publisher",
  "data": "encrypted_payload",
  "iv": "initialization_vector",
  "tag": "auth_tag"
}
```

### 2. Command Center → Service 1
```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "worker_name": "initial-publisher",
  "data": "encrypted_payload",
  "iv": "initialization_vector",
  "tag": "auth_tag"
}
```

### 3. Service 1 → Command Center
```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "worker_name": "service-1-publisher",
  "data": "encrypted_enriched_payload",
  "iv": "initialization_vector",
  "tag": "auth_tag"
}
```

### 4. Command Center → Service 2
```json
{
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "worker_name": "service-1-publisher",
  "data": "encrypted_enriched_payload",
  "iv": "initialization_vector",
  "tag": "auth_tag"
}
```

## Cassandra Metadata Logs

Each routing operation creates a log entry:

### Log 1: Publisher → Service 1
```
request_id: 550e8400-e29b-41d4-a716-446655440000
worker_name: initial-publisher
source_topic: command-center-inbox
target_topic: service-1-topic
route_id: route-publisher-to-service1
success: true
processing_time_ms: 3
```

### Log 2: Service 1 → Service 2
```
request_id: 550e8400-e29b-41d4-a716-446655440000
worker_name: service-1-publisher
source_topic: command-center-inbox
target_topic: service-2-topic
route_id: route-service1-to-service2
success: true
processing_time_ms: 2
```

## Encryption Flow

```
Publisher:
  Original → Encrypt → Send

Service 1:
  Receive → Decrypt → Process → Encrypt → Send

Service 2:
  Receive → Decrypt → Process
```

**Key Points:**
- All payloads encrypted with AES-256-GCM
- request_id stays visible for tracing
- Same ENCRYPTION_KEY used across all services
- Command Center never decrypts payload

## Routing Logic

### Route Registry
```typescript
{
  "initial-publisher": {
    targetTopic: "service-1-topic",
    enabled: true
  },
  "service-1-publisher": {
    targetTopic: "service-2-topic",
    enabled: true
  }
}
```

### Routing Algorithm
1. Receive message on command-center-inbox
2. Extract worker_name from message
3. Look up route in Schema Registry
4. Verify route is enabled
5. Forward to target_topic
6. Log metadata to Cassandra

## Performance Characteristics

- **Command Center latency**: 1-5ms per hop
- **Encryption/Decryption**: ~1ms
- **Total chain latency**: ~10-20ms (network dependent)
- **Throughput**: 10k+ messages/sec (with proper scaling)

## Scalability

### Horizontal Scaling
- Multiple Command Center instances (Kafka consumer groups)
- Multiple Service 1 instances (Kafka consumer groups)
- Multiple Service 2 instances (Kafka consumer groups)

### Kafka Partitioning
- command-center-inbox: 3 partitions
- service-1-topic: 3 partitions
- service-2-topic: 3 partitions

### Load Balancing
- Kafka automatically distributes across partitions
- Consumer groups ensure parallel processing
- Command Center routes based on worker_name (not load)

## Failure Handling

### Command Center Failure
- Messages stay in command-center-inbox
- Restart Command Center to resume
- No message loss (Kafka persistence)

### Service Failure
- Messages stay in service topics
- Restart service to resume
- Kafka consumer group handles failover

### Metadata Logging Failure
- Routing continues (logs are non-blocking)
- Metadata may be lost but messages flow
- Cassandra replica handles availability

## Monitoring Points

1. **Kafka Lag**: Monitor consumer lag per service
2. **Cassandra Logs**: Query routing_metadata for metrics
3. **Processing Time**: Track processing_time_ms per route
4. **Success Rate**: Monitor success/failure ratio
5. **TTL Cleanup**: Monitor data expiration after 1 week
