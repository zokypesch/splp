# Testing Guide - Parallel Processing

## Quick Test

### 1. Start Infrastructure
```bash
cd /mnt/d/project/splp
docker-compose up -d
./create-topics.sh
```

### 2. Start Services (7 terminals)

**Terminal 1 - Command Center:**
```bash
cd example-bun
./run-example.sh command-center
```

**Terminal 2 - Service 1 (Validation):**
```bash
cd example-bun
./run-example.sh service-1
```

**Terminal 3 - Service 1A (Inventory):**
```bash
cd example-bun
./run-example.sh service-1a
```

**Terminal 4 - Service 1B (Fraud):**
```bash
cd example-bun
./run-example.sh service-1b
```

**Terminal 5 - Service 1C (Pricing):**
```bash
cd example-bun
./run-example.sh service-1c
```

**Terminal 6 - Service 2 (Final):**
```bash
cd example-bun
./run-example.sh service-2
```

**Terminal 7 - Publisher (Test):**
```bash
cd example-bun
./run-example.sh publisher
```

## Expected Results

### Publisher Output:
```
✓ Message sent to Command Center
  → Command Center routes to service-1-topic
  → ALL Service 1 variants (1, 1A, 1B, 1C) receive the SAME message
  → Each processes in parallel and sends to service_2
  → service_2 receives 4 different results
```

### Command Center Output:
```
Routing message from initial-publisher (uuid-xxx)
Routed uuid-xxx: initial-publisher -> service-1-topic (3ms)
```

### Service 1 Output (Validation):
```
📥 Received message from Command Center
  Request ID: uuid-xxx
  Order: ORD-xxx
  Status: APPROVED

📤 Sending to Command Center for routing to service_2
✓ Sent to Command Center
```

### Service 1A Output (Inventory):
```
📥 [Service 1A] Received message from Command Center
  Request ID: uuid-xxx
  Order: ORD-xxx
  Inventory Status: AVAILABLE
  Available Quantities: {"laptop":87,"mouse":125,"keyboard":93}

📤 Sending to Command Center...
✓ Sent to Command Center
```

### Service 1B Output (Fraud):
```
📥 [Service 1B] Received message from Command Center
  Request ID: uuid-xxx
  Order: ORD-xxx
  Fraud Check: PASS
  Risk Score: 22 / 100

📤 Sending to Command Center...
✓ Sent to Command Center
```

### Service 1C Output (Pricing):
```
📥 [Service 1C] Received message from Command Center
  Request ID: uuid-xxx
  Order: ORD-xxx
  Original Amount: $299.99
  Discount: 5% (-$15.00)
  Tax (8%): $22.80
  Final Amount: $307.79

📤 Sending to Command Center...
✓ Sent to Command Center
```

### Service 2 Output (4 separate messages):

**Message 1 - From Service 1:**
```
═══════════════════════════════════════════════════════════
📬 FINAL MESSAGE RECEIVED
═══════════════════════════════════════════════════════════

📋 Order Details:
  Request ID: uuid-xxx
  Order ID: ORD-xxx
  User ID: user-12345
  Amount: $299.99
  Items: laptop, mouse, keyboard

✅ Processing History:
  Processed by: service-1
  Type: ORDER VALIDATION
  Validation: APPROVED
  Validated at: 2025-10-20T...

🔄 Final processing...
✅ ORDER APPROVED - Proceeding with fulfillment

⏱️  Total processing time: 534ms

🎉 PROCESSING COMPLETED!
   Publisher → CC → service-1 → CC → Service 2
═══════════════════════════════════════════════════════════
```

**Message 2 - From Service 1A:**
```
═══════════════════════════════════════════════════════════
📬 FINAL MESSAGE RECEIVED
═══════════════════════════════════════════════════════════

📋 Order Details:
  Request ID: uuid-xxx
  Order ID: ORD-xxx
  ...

✅ Processing History:
  Processed by: service-1a
  Type: INVENTORY CHECK
  Inventory Status: AVAILABLE
  Checked at: 2025-10-20T...
  Available Quantities: {"laptop":87,"mouse":125,"keyboard":93}

🔄 Final processing...
✅ INVENTORY AVAILABLE - Can fulfill order

⏱️  Total processing time: 521ms

🎉 PROCESSING COMPLETED!
   Publisher → CC → service-1a → CC → Service 2
═══════════════════════════════════════════════════════════
```

**Message 3 - From Service 1B:**
```
═══════════════════════════════════════════════════════════
📬 FINAL MESSAGE RECEIVED
═══════════════════════════════════════════════════════════

📋 Order Details:
  Request ID: uuid-xxx
  Order ID: ORD-xxx
  ...

✅ Processing History:
  Processed by: service-1b
  Type: FRAUD DETECTION
  Fraud Check: PASS
  Risk Score: 22 / 100
  Checked at: 2025-10-20T...

🔄 Final processing...
✅ FRAUD CHECK PASSED - Safe to proceed

⏱️  Total processing time: 517ms

🎉 PROCESSING COMPLETED!
   Publisher → CC → service-1b → CC → Service 2
═══════════════════════════════════════════════════════════
```

**Message 4 - From Service 1C:**
```
═══════════════════════════════════════════════════════════
📬 FINAL MESSAGE RECEIVED
═══════════════════════════════════════════════════════════

📋 Order Details:
  Request ID: uuid-xxx
  Order ID: ORD-xxx
  ...

✅ Processing History:
  Processed by: service-1c
  Type: PRICING & DISCOUNT
  Original Amount: $299.99
  Discount Applied: $15.00
  Discount Reason: Small order discount (5%)
  Tax Amount: $22.80
  Final Amount: $307.79
  Checked at: 2025-10-20T...

🔄 Final processing...
✅ PRICING CALCULATED - Ready for payment

⏱️  Total processing time: 509ms

🎉 PROCESSING COMPLETED!
   Publisher → CC → service-1c → CC → Service 2
═══════════════════════════════════════════════════════════
```

## Key Observations

1. **Publisher sends 1 message** → All services receive it
2. **Service 2 receives 4 messages** → One from each Service 1 variant
3. **Same request_id** across all messages
4. **Different processing results** based on each service's logic
5. **Parallel processing** - all services process simultaneously

## Test Variations

### Test with High Amount (Fraud Warning)
```bash
./run-example.sh publisher-b
```
Expected: Service 1B will show fraud warning due to high amount ($7599.99)

### Test with Discount Eligible Amount
```bash
./run-example.sh publisher-c
```
Expected: Service 1C will show 15% + 5% discount on $1299.99

## Verification

### Check Consumer Groups
```bash
docker exec kafka /opt/kafka/bin/kafka-consumer-groups.sh \
  --bootstrap-server localhost:9092 \
  --list
```
Should show:
- `service-1-group`
- `service-1a-group`
- `service-1b-group`
- `service-1c-group`
- `service-2-group`

### Check Topic Messages
```bash
docker exec kafka /opt/kafka/bin/kafka-console-consumer.sh \
  --bootstrap-server localhost:9092 \
  --topic service-1-topic \
  --from-beginning \
  --max-messages 1
```

### Check Routing Metadata
```bash
docker exec -it cassandra cqlsh
USE command_center;
SELECT request_id, worker_name, target_topic, success FROM routing_metadata LIMIT 20;
```

## Troubleshooting

**Service 2 receives only 1-3 messages instead of 4:**
- Check all Service 1 variants are running
- Verify each has a different consumer group

**Service 2 shows error about undefined fields:**
- Make sure Service 2 code is updated to handle all payload types

**No messages flowing:**
- Check Command Center is running
- Verify route exists: `SELECT * FROM command_center.routes WHERE source_publisher = 'initial-publisher';`

**Consumer group not listed:**
- Service might not be running
- Check service logs for connection errors

## Success Criteria

✅ Publisher sends 1 message
✅ Command Center logs 1 routing operation
✅ All 4 Service 1 variants receive the message
✅ Each Service 1 variant processes with its own logic
✅ Service 2 receives exactly 4 messages
✅ All messages have the same request_id
✅ Service 2 handles all 4 different payload types correctly

## Clean Up

```bash
# Stop all services (Ctrl+C in each terminal)

# Stop infrastructure
cd /mnt/d/project/splp
docker-compose down
```
