# Create Kafka Topics for Example Chain (PowerShell)
# This script is Windows-friendly and mirrors create-topics.sh

Write-Host "=========================================="
Write-Host "Creating Kafka Topics"
Write-Host "=========================================="
Write-Host ""

# Wait for Kafka to be ready
Write-Host "Waiting for Kafka to be ready..."
Start-Sleep -Seconds 5

# Topics to create
$topics = @(
    "command-center-inbox",
    "service-1-topic",
    "service-1a-topic",
    "service-1b-topic",
    "service-1c-topic",
    "service-2-topic"
)

foreach ($topic in $topics) {
    Write-Host "Creating topic: $topic"
    docker exec kafka /opt/kafka/bin/kafka-topics.sh \
        --create \
        --topic "$topic" \
        --bootstrap-server 10.70.1.23:9092 \
        --partitions 3 \
        --replication-factor 1 \
        --if-not-exists \
        --config retention.ms=604800000

    if ($?) {
        Write-Host "✓ Topic '$topic' created successfully"
    } else {
        Write-Host "⚠ Topic '$topic' may already exist or creation failed"
    }
    Write-Host ""
}

# List all topics
Write-Host "=========================================="
Write-Host "Current Topics:"
Write-Host "=========================================="
docker exec kafka /opt/kafka/bin/kafka-topics.sh \
    --list \
    --bootstrap-server 10.70.1.23:9092

Write-Host ""
Write-Host "✓ All topics ready!"