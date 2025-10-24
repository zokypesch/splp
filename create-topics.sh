#!/bin/bash

# Create Kafka Topics for Example Chain
# This script creates all required topics before starting services

echo "=========================================="
echo "Creating Kafka Topics"
echo "=========================================="
echo ""

# Wait for Kafka to be ready
echo "Waiting for Kafka to be ready..."
sleep 5

# Topics to create
TOPICS=(
    "command-center-inbox"
    "service-1-topic"
    "service-1-topic-reply"
    "service-1a-topic"
    "service-1a-topic-reply"
    "service-1b-topic"
    "service-1b-topic-reply"
    "service-1c-topic"
    "service-1c-topic-reply"
    "service-2-topic"
    "service-2-topic-reply"
)

# Create each topic
for topic in "${TOPICS[@]}"
do
    echo "Creating topic: $topic"
    docker exec kafka /opt/kafka/bin/kafka-topics.sh \
        --create \
        --topic "$topic" \
        --bootstrap-server 10.70.1.23:9092 \
        --partitions 3 \
        --replication-factor 1 \
        --if-not-exists \
        --config retention.ms=604800000

    if [ $? -eq 0 ]; then
        echo "✓ Topic '$topic' created successfully"
    else
        echo "⚠ Topic '$topic' may already exist or creation failed"
    fi
    echo ""
done

# List all topics
echo "=========================================="
echo "Current Topics:"
echo "=========================================="
docker exec kafka /opt/kafka/bin/kafka-topics.sh \
    --list \
    --bootstrap-server 10.70.1.23:9092

echo ""
echo "✓ All topics ready!"
