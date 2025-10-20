#!/bin/bash

# Run Example: Chained Service Communication
# This script helps you run the complete example

echo "=========================================="
echo "Example: Chained Service Communication"
echo "=========================================="
echo ""

# Check if ENCRYPTION_KEY is set
if [ -z "$ENCRYPTION_KEY" ]; then
    echo "⚠️  ENCRYPTION_KEY not set!"
    echo ""
    echo "Generate one with:"
    echo "  cd ../splp-bun"
    echo "  bun run -e \"import {generateEncryptionKey} from './src/index.js'; console.log(generateEncryptionKey())\""
    echo ""
    echo "Then export it:"
    echo "  export ENCRYPTION_KEY=\"your-key-here\""
    echo ""
    exit 1
fi

echo "✓ ENCRYPTION_KEY is set"
echo ""

# Check what to run
if [ "$1" == "command-center" ]; then
    echo "Starting Command Center..."
    bun run command-center-config.ts

elif [ "$1" == "service-1" ]; then
    echo "Starting Service 1..."
    cd service_1
    bun run index.ts

elif [ "$1" == "service-1a" ]; then
    echo "Starting Service 1A (Inventory Check)..."
    cd service_1_a
    bun run index.ts

elif [ "$1" == "service-1b" ]; then
    echo "Starting Service 1B (Fraud Detection)..."
    cd service_1_b
    bun run index.ts

elif [ "$1" == "service-1c" ]; then
    echo "Starting Service 1C (Pricing & Discount)..."
    cd service_1_c
    bun run index.ts

elif [ "$1" == "service-2" ]; then
    echo "Starting Service 2..."
    cd service_2
    bun run index.ts

elif [ "$1" == "publisher" ]; then
    echo "Starting Publisher..."
    cd publisher
    bun run index.ts

elif [ "$1" == "publisher-a" ]; then
    echo "Starting Publisher A (for Service 1A)..."
    cd publisher_a
    bun run index.ts

elif [ "$1" == "publisher-b" ]; then
    echo "Starting Publisher B (for Service 1B)..."
    cd publisher_b
    bun run index.ts

elif [ "$1" == "publisher-c" ]; then
    echo "Starting Publisher C (for Service 1C)..."
    cd publisher_c
    bun run index.ts

else
    echo "Usage: ./run-example.sh [service-name]"
    echo ""
    echo "Available services:"
    echo "  command-center  - Central routing hub"
    echo "  service-1       - Original validation service"
    echo "  service-1a      - Inventory check service"
    echo "  service-1b      - Fraud detection service"
    echo "  service-1c      - Pricing & discount service"
    echo "  service-2       - Final fulfillment service"
    echo "  publisher       - Original publisher"
    echo "  publisher-a     - Publisher for Service 1A"
    echo "  publisher-b     - Publisher for Service 1B"
    echo "  publisher-c     - Publisher for Service 1C"
    echo ""
    echo "Example usage (in separate terminals):"
    echo "  Terminal 1: ./run-example.sh command-center"
    echo "  Terminal 2: ./run-example.sh service-1a"
    echo "  Terminal 3: ./run-example.sh service-2"
    echo "  Terminal 4: ./run-example.sh publisher-a"
    echo ""
fi
