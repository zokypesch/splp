# Quick Start Guide - New Services

## Prerequisites

1. **Start Kafka & Cassandra**:
   ```bash
   docker-compose up -d
   ```

2. **Create Kafka Topics**:
   ```bash
   ./create-topics.sh
   ```

   This creates all required topics:
   - `command-center-inbox`
   - `service-1-topic`
   - `service-1a-topic` (NEW)
   - `service-1b-topic` (NEW)
   - `service-1c-topic` (NEW)
   - `service-2-topic`

3. **Set Encryption Key** (optional - will auto-generate if not set):
   ```bash
   export ENCRYPTION_KEY="your-64-char-hex-key"
   ```

## Running Services

Use the helper script in `example-bun/`:

```bash
cd example-bun

# Terminal 1: Command Center
./run-example.sh command-center

# Terminal 2: Service 1A (Inventory)
./run-example.sh service-1a

# Terminal 3: Service 2 (Final)
./run-example.sh service-2

# Terminal 4: Test with Publisher A
./run-example.sh publisher-a
```

## Available Services

### Command Center
```bash
./run-example.sh command-center
```
Central routing hub - routes all messages between services.

### Service 1 Variants

**Service 1A - Inventory Check**:
```bash
./run-example.sh service-1a
```
Checks inventory availability for order items.

**Service 1B - Fraud Detection**:
```bash
./run-example.sh service-1b
```
Performs fraud detection and risk scoring.

**Service 1C - Pricing & Discount**:
```bash
./run-example.sh service-1c
```
Calculates pricing, discounts, and taxes.

**Service 1 - Original Validation**:
```bash
./run-example.sh service-1
```
Original order validation service.

### Service 2 - Final Processing
```bash
./run-example.sh service-2
```
Receives results from all Service 1 variants and completes processing.

### Publishers

**Publisher A** (targets Service 1A):
```bash
./run-example.sh publisher-a
```
Sends order: 4 items, $459.99

**Publisher B** (targets Service 1B):
```bash
./run-example.sh publisher-b
```
Sends order: 4 items, $7599.99 (high amount - may trigger fraud warning)

**Publisher C** (targets Service 1C):
```bash
./run-example.sh publisher-c
```
Sends order: 6 items, $1299.99 (qualifies for 15% + 5% discount)

**Publisher** (targets original Service 1):
```bash
./run-example.sh publisher
```
Original publisher for Service 1.

## Complete Test Scenario

**Test all three new services simultaneously:**

```bash
# Terminal 1
cd example-bun
./run-example.sh command-center

# Terminal 2
./run-example.sh service-1a

# Terminal 3
./run-example.sh service-1b

# Terminal 4
./run-example.sh service-1c

# Terminal 5
./run-example.sh service-2

# Then send test messages:

# Terminal 6 - Test Inventory Check
./run-example.sh publisher-a

# Terminal 7 - Test Fraud Detection
./run-example.sh publisher-b

# Terminal 8 - Test Pricing & Discount
./run-example.sh publisher-c
```

## Message Flow

```
Publisher A ──┐
              │
Publisher B ──┼──> Command Center ──┬──> Service 1A (Inventory) ──┐
              │                     │                               │
Publisher C ──┘                     ├──> Service 1B (Fraud)     ────┼──> Command Center ──> Service 2
                                    │                               │
                                    └──> Service 1C (Pricing)   ────┘
```

## Verifying Topics

List all Kafka topics:
```bash
docker exec kafka /opt/kafka/bin/kafka-topics.sh \
  --list \
  --bootstrap-server localhost:9092
```

Expected output:
```
__consumer_offsets
command-center-inbox
service-1-topic
service-1a-topic
service-1b-topic
service-1c-topic
service-2-topic
```

## Monitoring

Check Cassandra for routing metadata:
```bash
docker exec -it cassandra cqlsh

# Query routing logs
USE command_center;
SELECT * FROM routing_metadata LIMIT 10;

# Query by worker name
SELECT * FROM routing_metadata WHERE worker_name = 'service-1a-publisher';
```

## Troubleshooting

**Topics not created?**
```bash
./create-topics.sh
```

**Cannot connect to Kafka?**
```bash
docker ps  # Verify kafka container is running
docker logs kafka  # Check kafka logs
```

**Command Center not routing?**
```bash
# Check if routes are registered in Cassandra
docker exec -it cassandra cqlsh
USE command_center;
SELECT * FROM routes;
```

**Service not receiving messages?**
- Verify topic exists
- Check Command Center logs for routing errors
- Ensure service is subscribed to correct topic

## Clean Up

Stop all services with `Ctrl+C`, then:

```bash
# Stop infrastructure
docker-compose down

# Remove volumes (optional - deletes all data)
docker-compose down -v
```

## Next Steps

See `example-bun/NEW_SERVICES_README.md` for detailed documentation on each service.
