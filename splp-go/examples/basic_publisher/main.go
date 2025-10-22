package main

import (
	"fmt"
	"log"
	"os"
	"time"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/perlinsos/splp-go/pkg/crypto"
	"github.com/perlinsos/splp-go/pkg/messaging"
)

func main() {
	fmt.Println("🚀 SPLP Go Basic Publisher Example")
	fmt.Println("==================================")

	// Configuration
	config := &types.MessagingConfig{
		Kafka: types.KafkaConfig{
			Brokers:          []string{"10.70.1.23:9092"},
			ClientID:         "basic-publisher-client",
			GroupID:          "publisher-group", // Required even for publishers
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

	// Example 1: Send a simple message
	fmt.Println("\n📤 Sending simple message...")
	simplePayload := map[string]interface{}{
		"message": "Hello from SPLP Go!",
		"sender":  "basic-publisher",
		"type":    "greeting",
	}

	response, err := client.Request("calculate", simplePayload, 30*time.Second)
	if err != nil {
		fmt.Printf("❌ Failed to send simple message: %v\n", err)
	} else {
		fmt.Printf("✅ Received response: %+v\n", response)
	}

	// Example 2: Send a calculation request
	fmt.Println("\n🧮 Sending calculation request...")
	calcPayload := map[string]interface{}{
		"operation": "add",
		"a":         10,
		"b":         5,
		"requestor": "basic-publisher",
	}

	response, err = client.Request("calculate", calcPayload, 30*time.Second)
	if err != nil {
		fmt.Printf("❌ Failed to send calculation request: %v\n", err)
	} else {
		fmt.Printf("✅ Calculation result: %+v\n", response)
	}

	// Example 3: Send multiple messages
	fmt.Println("\n📦 Sending multiple messages...")
	for i := 1; i <= 3; i++ {
		payload := map[string]interface{}{
			"message_id": i,
			"content":    fmt.Sprintf("Message number %d", i),
			"timestamp":  time.Now().Unix(),
		}

		response, err := client.Request("process", payload, 15*time.Second)
		if err != nil {
			fmt.Printf("❌ Message %d failed: %v\n", i, err)
		} else {
			fmt.Printf("✅ Message %d processed: %+v\n", i, response)
		}

		// Small delay between messages
		time.Sleep(1 * time.Second)
	}

	fmt.Println("\n🎉 Publisher example completed!")
	fmt.Println("💡 Make sure you have a consumer running to process these messages")
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
	}
	return key
}
