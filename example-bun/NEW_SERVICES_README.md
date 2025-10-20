# New Services Guide: Service 1A, 1B, 1C

This guide explains the three new services added to the example chain: **Service 1A** (Inventory Check), **Service 1B** (Fraud Detection), and **Service 1C** (Pricing & Discount).

## Overview

All three services follow the same pattern as the original Service 1:
1. Receive messages from **Publisher** via **Command Center**
2. Process the request with specialized logic
3. Send results to **Service 2** via **Command Center**

## Service Descriptions

### Service 1A - Inventory Check
**Location**: `example-bun/service_1_a/`
**Topic**: `service-1a-topic`
**Publisher**: `initial-publisher-a`

**Purpose**: Checks inventory availability for order items

**Processing Logic**:
- Simulates inventory lookup for each item
- Generates random stock quantities (50-150 units)
- Determines availability status:
  - `available`: All items have sufficient stock (>10 units)
  - `limited`: Some items have low stock
  - `unavailable`: Some items out of stock

**Output**:
```typescript
{
  orderId, userId, amount, items,
  processedBy: 'service-1a',
  inventoryCheck: 'available' | 'limited' | 'unavailable',
  checkedAt: string,
  availableQuantity: { [item: string]: number }
}
```

### Service 1B - Fraud Detection
**Location**: `example-bun/service_1_b/`
**Topic**: `service-1b-topic`
**Publisher**: `initial-publisher-b`

**Purpose**: Performs fraud detection and risk scoring

**Processing Logic**:
- Calculates risk score (0-100):
  - High transaction amounts (+30 points if >$5000)
  - Unusual number of items (+20 points if >10 items)
  - Random risk factors (0-50 points)
- Determines fraud status:
  - `pass`: Risk score < 30
  - `warning`: Risk score 30-60
  - `fail`: Risk score > 60

**Output**:
```typescript
{
  orderId, userId, amount, items,
  processedBy: 'service-1b',
  fraudCheck: 'pass' | 'warning' | 'fail',
  riskScore: number,
  checkedAt: string,
  fraudReasons?: string[]
}
```

### Service 1C - Pricing & Discount
**Location**: `example-bun/service_1_c/`
**Topic**: `service-1c-topic`
**Publisher**: `initial-publisher-c`

**Purpose**: Calculates pricing, applies discounts, and adds taxes

**Processing Logic**:
- Applies amount-based discounts:
  - >$1000: 15% discount
  - >$500: 10% discount
  - >$200: 5% discount
- Adds item count bonus: +5% if ≥5 items
- Calculates 8% tax on discounted amount

**Output**:
```typescript
{
  orderId, userId, amount, items,
  processedBy: 'service-1c',
  originalAmount: number,
  discountApplied: number,
  finalAmount: number,
  taxAmount: number,
  discountReason?: string,
  checkedAt: string
}
```

## Running the Services

### Prerequisites
1. Start Kafka and Cassandra:
   ```bash
   docker-compose up -d
   ```

2. Start Command Center:
   ```bash
   cd example-bun
   bun run ../command-center-config.ts
   ```

3. Start Service 2 (receives from all Service 1 variants):
   ```bash
   cd example-bun
   bun run service_2/index.ts
   ```

### Running Each Service

**Terminal 1 - Service 1A (Inventory)**:
```bash
cd example-bun
bun run service_1_a/index.ts
```

**Terminal 2 - Service 1B (Fraud)**:
```bash
cd example-bun
bun run service_1_b/index.ts
```

**Terminal 3 - Service 1C (Pricing)**:
```bash
cd example-bun
bun run service_1_c/index.ts
```

### Testing with Publishers

**Test Service 1A - Inventory Check**:
```bash
cd example-bun
bun run publisher_a/index.ts
```
Expected flow:
- Publisher A → Command Center → Service 1A → Command Center → Service 2
- Service 1A checks inventory and reports availability status

**Test Service 1B - Fraud Detection**:
```bash
cd example-bun
bun run publisher_b/index.ts
```
Expected flow:
- Publisher B → Command Center → Service 1B → Command Center → Service 2
- Service 1B performs fraud check and calculates risk score
- High amount ($7599.99) may trigger fraud warnings

**Test Service 1C - Pricing & Discount**:
```bash
cd example-bun
bun run publisher_c/index.ts
```
Expected flow:
- Publisher C → Command Center → Service 1C → Command Center → Service 2
- Service 1C calculates discounts (15% for >$1000 + 5% for 6 items)
- Applies 8% tax and returns final pricing

## Routing Configuration

All routes are configured in `command-center-config.ts`:

### Publisher → Service 1 Routes
| Publisher ID | Target Topic | Service |
|--------------|--------------|---------|
| initial-publisher-a | service-1a-topic | Service 1A (Inventory) |
| initial-publisher-b | service-1b-topic | Service 1B (Fraud) |
| initial-publisher-c | service-1c-topic | Service 1C (Pricing) |

### Service 1 → Service 2 Routes
| Publisher ID | Target Topic | Service |
|--------------|--------------|---------|
| service-1a-publisher | service-2-topic | Service 2 (Final) |
| service-1b-publisher | service-2-topic | Service 2 (Final) |
| service-1c-publisher | service-2-topic | Service 2 (Final) |

## Complete Test Scenario

Run all services simultaneously to test parallel processing:

1. **Start Infrastructure**:
   ```bash
   docker-compose up -d
   ```

2. **Start Command Center**:
   ```bash
   bun run command-center-config.ts
   ```

3. **Start All Service 1 Variants** (3 terminals):
   ```bash
   # Terminal 1
   bun run service_1_a/index.ts

   # Terminal 2
   bun run service_1_b/index.ts

   # Terminal 3
   bun run service_1_c/index.ts
   ```

4. **Start Service 2**:
   ```bash
   bun run service_2/index.ts
   ```

5. **Send Test Messages** (3 terminals):
   ```bash
   # Terminal 1 - Test Inventory
   bun run publisher_a/index.ts

   # Terminal 2 - Test Fraud Detection
   bun run publisher_b/index.ts

   # Terminal 3 - Test Pricing
   bun run publisher_c/index.ts
   ```

## Architecture Diagram

```
Publisher A ──┐
              ├──> Command Center ──┐
Publisher B ──┤                     ├──> Service 1A ──┐
              │                     ├──> Service 1B ──┼──> Command Center ──> Service 2
Publisher C ──┘                     └──> Service 1C ──┘
```

## Key Features

1. **Independent Processing**: Each Service 1 variant processes orders independently
2. **Specialized Logic**: Each service focuses on one aspect (inventory, fraud, pricing)
3. **Central Routing**: Command Center handles all routing decisions
4. **Encryption**: All messages remain encrypted during transit
5. **Metadata Logging**: All routing operations logged to Cassandra (no payloads)

## Monitoring

Query routing metadata to see message flow:
```typescript
const logger = commandCenter.getMetadataLogger();

// Check Service 1A activity
const service1aLogs = await logger.getByWorkerName('service-1a-publisher', 100);

// Check Service 1B activity
const service1bLogs = await logger.getByWorkerName('service-1b-publisher', 100);

// Check Service 1C activity
const service1cLogs = await logger.getByWorkerName('service-1c-publisher', 100);
```

## Tips

- **Different Processing Times**: Each service has different simulated delays (800ms, 1200ms, 900ms)
- **Test Data**: Publishers send different order characteristics to showcase each service's logic
- **Parallel Execution**: All services can run simultaneously without conflicts
- **Error Handling**: Each service handles errors independently and logs to console

## Next Steps

Consider adding:
- Aggregation service that combines results from all Service 1 variants
- API endpoints to query service status
- Real-time monitoring dashboard
- Load testing with multiple concurrent requests
