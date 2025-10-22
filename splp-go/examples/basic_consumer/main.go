package main

import (
	"fmt"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/perlinsos/splp-go/pkg/crypto"
	"github.com/perlinsos/splp-go/pkg/messaging"
)

func main() {
	fmt.Println("🎯 SPLP Go Basic Consumer Example")
	fmt.Println("=================================")

	// Configuration
	config := &types.MessagingConfig{
		Kafka: types.KafkaConfig{
			Brokers:          []string{"10.70.1.23:9092"},
			ClientID:         "basic-consumer-client",
			GroupID:          "consumer-group",
			RequestTimeoutMs: 30000, // 30 seconds timeout
		},
		Encryption: types.EncryptionConfig{
			Key: getEncryptionKey(),
		},
		// Cassandra for logging (optional)
		// Cassandra: &types.CassandraConfig{
		// 	ContactPoints:   []string{"localhost"},
		// 	LocalDataCenter: "datacenter1",
		// 	Keyspace:        "messaging",
		// },
	}

	// Create messaging client
	client, err := messaging.NewMessagingClient(config)
	if err != nil {
		log.Fatalf("❌ Failed to create messaging client: %v", err)
	}

	// Initialize the client
	fmt.Println("🔧 Initializing messaging client...")
	if err := client.Initialize(); err != nil {
		log.Fatalf("❌ Failed to initialize client: %v", err)
	}
	fmt.Println("✅ Client initialized successfully")

	// Register handlers for different topics
	fmt.Println("📝 Registering message handlers...")

	// Handler for calculation requests
	err = client.RegisterHandler("command-center-inbox", func(request *types.RequestMessage) (interface{}, error) {
		fmt.Printf("🧮 Processing calculation request: %s\n", request.RequestID)

		payload, ok := request.Payload.(map[string]interface{})
		if !ok {
			return nil, fmt.Errorf("invalid payload format")
		}

		// Extract operation and operands
		operation, _ := payload["operation"].(string)
		a, aOk := payload["a"].(float64)
		b, bOk := payload["b"].(float64)

		if !aOk || !bOk {
			return map[string]interface{}{
				"error": "Invalid operands - expected numbers",
			}, nil
		}

		var result float64
		switch operation {
		case "add":
			result = a + b
		case "subtract":
			result = a - b
		case "multiply":
			result = a * b
		case "divide":
			if b == 0 {
				return map[string]interface{}{
					"error": "Division by zero",
				}, nil
			}
			result = a / b
		default:
			result = a + b // Default to addition
		}

		response := map[string]interface{}{
			"result":       result,
			"operation":    operation,
			"operands":     []float64{a, b},
			"processed_at": time.Now().Unix(),
			"processed_by": "basic-consumer",
		}

		fmt.Printf("✅ Calculation completed: %.2f %s %.2f = %.2f\n", a, operation, b, result)
		return response, nil
	})
	if err != nil {
		log.Fatalf("❌ Failed to register calculate handler: %v", err)
	}

	// Handler for general processing requests
	err = client.RegisterHandler("process", func(request *types.RequestMessage) (interface{}, error) {
		fmt.Printf("⚙️  Processing general request: %s\n", request.RequestID)

		payload, ok := request.Payload.(map[string]interface{})
		if !ok {
			return nil, fmt.Errorf("invalid payload format")
		}

		// Simulate some processing time
		time.Sleep(500 * time.Millisecond)

		response := map[string]interface{}{
			"status":       "processed",
			"original":     payload,
			"processed_at": time.Now().Unix(),
			"processed_by": "basic-consumer",
			"message":      "Request processed successfully",
		}

		fmt.Printf("✅ General processing completed for request: %s\n", request.RequestID)
		return response, nil
	})
	if err != nil {
		log.Fatalf("❌ Failed to register process handler: %v", err)
	}

	// Handler for greeting messages
	err = client.RegisterHandler("greet", func(request *types.RequestMessage) (interface{}, error) {
		fmt.Printf("👋 Processing greeting request: %s\n", request.RequestID)

		payload, ok := request.Payload.(map[string]interface{})
		if !ok {
			return nil, fmt.Errorf("invalid payload format")
		}

		name, _ := payload["name"].(string)
		if name == "" {
			name = "Anonymous"
		}

		response := map[string]interface{}{
			"greeting":     fmt.Sprintf("Hello, %s! Greetings from SPLP Go Consumer!", name),
			"timestamp":    time.Now().Unix(),
			"processed_by": "basic-consumer",
		}

		fmt.Printf("✅ Greeting sent to: %s\n", name)
		return response, nil
	})
	if err != nil {
		log.Fatalf("❌ Failed to register greet handler: %v", err)
	}

	// Start consuming messages
	fmt.Println("🎧 Starting to consume messages...")
	if err := client.StartConsuming(); err != nil {
		log.Fatalf("❌ Failed to start consuming: %v", err)
	}

	fmt.Println("✅ Consumer is running and ready to process messages!")
	fmt.Println("📋 Registered handlers for topics: calculate, process, greet")
	fmt.Println("🔄 Waiting for messages... (Press Ctrl+C to stop)")

	// Set up graceful shutdown
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)

	// Wait for shutdown signal
	<-sigChan
	fmt.Println("\n🛑 Shutdown signal received, stopping consumer...")

	// Close the client
	if err := client.Close(); err != nil {
		fmt.Printf("⚠️  Error during shutdown: %v\n", err)
	}

	fmt.Println("👋 Consumer stopped gracefully")
}

// getEncryptionKey returns the encryption key from environment or generates one
func getEncryptionKey() string {
	key := os.Getenv("ENCRYPTION_KEY")
	if key == "" {
		// Generate a key for demo purposes
		// In production, always use a secure key from environment or config
		generatedKey, err := crypto.GenerateEncryptionKey()
		if err != nil {
			log.Fatalf("❌ Failed to generate encryption key: %v", err)
		}
		key = generatedKey
		fmt.Printf("⚠️  Generated encryption key: %s\n", key)
		fmt.Println("💡 Set ENCRYPTION_KEY environment variable to use a consistent key")
		fmt.Println("🔑 Make sure publisher and consumer use the same key!")
	}
	return key
}
