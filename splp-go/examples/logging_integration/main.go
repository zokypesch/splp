package main

import (
	"fmt"
	"log"
	"os"
	"time"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/perlinsos/splp-go/pkg/logging"
	"github.com/perlinsos/splp-go/pkg/messaging"
	"github.com/perlinsos/splp-go/pkg/utils"
)

func main() {
	fmt.Println("📊 SPLP Go Logging Integration Example")
	fmt.Println("=====================================")

	// Demonstrate direct logging
	demonstrateDirectLogging()

	// Demonstrate integrated messaging with logging
	demonstrateIntegratedLogging()
}

func demonstrateDirectLogging() {
	fmt.Println("\n📝 Part 1: Direct Cassandra Logging Demo")
	fmt.Println("---------------------------------------")

	// Cassandra configuration
	cassandraConfig := &types.CassandraConfig{
		ContactPoints:   []string{"localhost"},
		LocalDataCenter: "datacenter1",
		Keyspace:        "messaging",
		TimeoutMs:       5000,
	}

	// Create logger
	logger, err := logging.NewCassandraLogger(cassandraConfig)
	if err != nil {
		log.Fatalf("❌ Failed to create logger: %v", err)
	}

	// Initialize logger
	fmt.Println("🔧 Initializing Cassandra logger...")
	if err := logger.Initialize(cassandraConfig); err != nil {
		log.Fatalf("❌ Failed to initialize logger: %v", err)
	}
	fmt.Println("✅ Logger initialized successfully")

	// Log some sample requests
	fmt.Println("📤 Logging sample requests...")

	requests := []struct {
		requestID string
		topic     string
		payload   interface{}
		encrypted bool
	}{
		{
			requestID: utils.GenerateRequestID(),
			topic:     "user_registration",
			payload: map[string]interface{}{
				"username": "john_doe",
				"email":    "john@example.com",
				"plan":     "premium",
			},
			encrypted: false,
		},
		{
			requestID: utils.GenerateRequestID(),
			topic:     "payment_processing",
			payload: map[string]interface{}{
				"amount":      99.99,
				"currency":    "USD",
				"card_last4":  "1234",
			},
			encrypted: true,
		},
		{
			requestID: utils.GenerateRequestID(),
			topic:     "data_analytics",
			payload: map[string]interface{}{
				"event_type": "page_view",
				"page":       "/dashboard",
				"user_id":    "user-12345",
			},
			encrypted: false,
		},
	}

	for i, req := range requests {
		fmt.Printf("📝 Logging request %d: %s\n", i+1, req.requestID)
		
		err := logger.LogRequest(req.requestID, req.topic, req.payload, req.encrypted)
		if err != nil {
			fmt.Printf("❌ Failed to log request %d: %v\n", i+1, err)
		} else {
			fmt.Printf("✅ Request %d logged successfully\n", i+1)
		}

		// Simulate processing time
		time.Sleep(500 * time.Millisecond)

		// Log corresponding response
		var response interface{}
		var success bool
		var errorMsg string

		switch req.topic {
		case "user_registration":
			response = map[string]interface{}{
				"user_id": "user-" + utils.GenerateRequestID()[:8],
				"status":  "active",
			}
			success = true
		case "payment_processing":
			response = map[string]interface{}{
				"transaction_id": "txn-" + utils.GenerateRequestID()[:12],
				"status":        "approved",
			}
			success = true
		case "data_analytics":
			response = map[string]interface{}{
				"recorded": true,
				"batch_id": "batch-" + utils.GenerateRequestID()[:8],
			}
			success = true
		}

		processingTime := time.Duration(500) * time.Millisecond
		err = logger.LogResponse(req.requestID, req.topic, response, success, errorMsg, processingTime, req.encrypted)
		if err != nil {
			fmt.Printf("❌ Failed to log response %d: %v\n", i+1, err)
		} else {
			fmt.Printf("✅ Response %d logged successfully\n", i+1)
		}

		fmt.Println()
	}

	// Query logs by time range
	fmt.Println("🔍 Querying logs by time range...")
	endTime := time.Now()
	startTime := endTime.Add(-5 * time.Minute)

	logs, err := logger.GetByTimeRange(startTime, endTime)
	if err != nil {
		fmt.Printf("❌ Failed to query logs: %v\n", err)
	} else {
		fmt.Printf("📊 Found %d log entries in the last 5 minutes\n", len(logs))
		for i, logEntry := range logs {
			if i < 3 { // Show first 3 entries
				fmt.Printf("   %d. %s - %s - %s\n", i+1, logEntry.RequestID, logEntry.Topic, logEntry.MessageType)
			}
		}
		if len(logs) > 3 {
			fmt.Printf("   ... and %d more entries\n", len(logs)-3)
		}
	}

	fmt.Println("✅ Direct logging demo completed")
}

func demonstrateIntegratedLogging() {
	fmt.Println("\n🔗 Part 2: Integrated Messaging with Logging")
	fmt.Println("-------------------------------------------")

	// Configuration with both Kafka and Cassandra
	config := &types.MessagingConfig{
		Kafka: types.KafkaConfig{
			Brokers:          []string{"localhost:9092"},
			ClientID:         "logging-integration-client",
			GroupID:          "logging-group",
			RequestTimeoutMs: 30000, // 30 seconds timeout
		},
		Encryption: types.EncryptionConfig{
			Key: getEncryptionKey(),
		},
		Cassandra: &types.CassandraConfig{
			ContactPoints:   []string{"localhost"},
			LocalDataCenter: "datacenter1",
			Keyspace:        "messaging",
		},
	}

	// Create messaging client with logging
	client, err := messaging.NewMessagingClient(config)
	if err != nil {
		log.Fatalf("❌ Failed to create messaging client: %v", err)
	}

	// Initialize the client
	fmt.Println("🔧 Initializing messaging client with logging...")
	if err := client.Initialize(); err != nil {
		log.Fatalf("❌ Failed to initialize client: %v", err)
	}
	fmt.Println("✅ Client with logging initialized")

	// Register handler that demonstrates logging integration
	err = client.RegisterHandler("audit_service", func(request *types.RequestMessage) (interface{}, error) {
		fmt.Printf("🔍 Processing audited request: %s\n", request.RequestID)
		
		payload, ok := request.Payload.(map[string]interface{})
		if !ok {
			return nil, fmt.Errorf("invalid payload format")
		}

		// Extract audit information
		action, _ := payload["action"].(string)
		resource, _ := payload["resource"].(string)
		userID, _ := payload["user_id"].(string)

		fmt.Printf("📋 Audit: User %s performed %s on %s\n", userID, action, resource)

		// Simulate audit processing
		time.Sleep(300 * time.Millisecond)

		// Generate audit response
		response := map[string]interface{}{
			"audit_id":     utils.GenerateRequestID(),
			"status":       "logged",
			"action":       action,
			"resource":     resource,
			"user_id":      userID,
			"timestamp":    time.Now().Unix(),
			"compliance":   "SOX_COMPLIANT",
		}

		fmt.Printf("✅ Audit logged: %s\n", response["audit_id"])
		return response, nil
	})
	if err != nil {
		log.Fatalf("❌ Failed to register audit handler: %v", err)
	}

	// Start consuming in a goroutine
	go func() {
		if err := client.StartConsuming(); err != nil {
			log.Printf("❌ Failed to start consuming: %v", err)
		}
	}()

	// Wait for consumer to start
	time.Sleep(2 * time.Second)

	// Send audit requests (these will be automatically logged)
	fmt.Println("📤 Sending audit requests (automatically logged)...")

	auditActions := []map[string]interface{}{
		{
			"action":   "CREATE",
			"resource": "user_account",
			"user_id":  "admin-001",
			"details": map[string]interface{}{
				"account_type": "premium",
				"permissions":  []string{"read", "write", "admin"},
			},
		},
		{
			"action":   "UPDATE",
			"resource": "payment_method",
			"user_id":  "user-12345",
			"details": map[string]interface{}{
				"card_type": "visa",
				"last_four": "1234",
			},
		},
		{
			"action":   "DELETE",
			"resource": "session_token",
			"user_id":  "user-67890",
			"details": map[string]interface{}{
				"reason":    "logout",
				"ip_address": "192.168.1.100",
			},
		},
	}

	for i, auditAction := range auditActions {
		fmt.Printf("\n📤 Sending audit action %d...\n", i+1)
		
		response, err := client.Request("audit_service", auditAction, 30*time.Second)
		if err != nil {
			fmt.Printf("❌ Audit action %d failed: %v\n", i+1, err)
			continue
		}

		fmt.Printf("✅ Audit action %d completed: %+v\n", i+1, response.Payload)
		
		// Small delay between actions
		time.Sleep(1 * time.Second)
	}

	fmt.Println("\n🎉 Integrated logging demo completed!")
	fmt.Println("📊 All requests and responses were automatically logged to Cassandra")
	fmt.Println("🔍 Check the messaging.logs table for complete audit trail")
	fmt.Println("🔐 Encrypted payloads are marked as encrypted in logs (payload not stored)")

	// Close the client
	if err := client.Close(); err != nil {
		fmt.Printf("⚠️  Error during shutdown: %v\n", err)
	}
}

// getEncryptionKey returns the encryption key from environment or generates one
func getEncryptionKey() string {
	key := os.Getenv("ENCRYPTION_KEY")
	if key == "" {
		// Generate a key for demo purposes
		generatedKey, err := crypto.GenerateEncryptionKey()
		if err != nil {
			log.Fatalf("❌ Failed to generate encryption key: %v", err)
		}
		key = generatedKey
		fmt.Printf("🔑 Generated encryption key: %s\n", key)
		fmt.Println("💡 Set ENCRYPTION_KEY environment variable for production use")
	}
	return key
}