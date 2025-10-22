package main

import (
	"fmt"
	"log"
	"os"
	"time"

	"github.com/perlinsos/splp-go/internal/types"
	"github.com/perlinsos/splp-go/pkg/crypto"
	"github.com/perlinsos/splp-go/pkg/messaging"
	"github.com/perlinsos/splp-go/pkg/utils"
)

func main() {
	fmt.Println("🔐 SPLP Go Encrypted Messaging Example")
	fmt.Println("=====================================")

	// Demonstrate encryption capabilities
	demonstrateEncryption()

	// Demonstrate secure messaging
	demonstrateSecureMessaging()
}

func demonstrateEncryption() {
	fmt.Println("\n🔒 Part 1: Direct Encryption/Decryption Demo")
	fmt.Println("-------------------------------------------")

	// Create encryptor with a known key
	encryptionKey := getOrGenerateKey()
	encryptor, err := crypto.NewEncryptor(encryptionKey)
	if err != nil {
		log.Fatalf("❌ Failed to create encryptor: %v", err)
	}

	// Test data - sensitive information
	sensitiveData := map[string]interface{}{
		"user_id":        "user-12345",
		"credit_card":    "4532-1234-5678-9012",
		"ssn":           "123-45-6789",
		"account_balance": 15000.50,
		"personal_info": map[string]interface{}{
			"name":    "John Doe",
			"email":   "john.doe@example.com",
			"address": "123 Main St, Anytown, USA",
		},
		"timestamp": time.Now().Unix(),
	}

	fmt.Printf("📄 Original sensitive data: %+v\n", sensitiveData)

	// Encrypt the data
	requestID := utils.GenerateRequestID()
	encrypted, err := encryptor.Encrypt(sensitiveData, requestID)
	if err != nil {
		log.Fatalf("❌ Failed to encrypt data: %v", err)
	}

	fmt.Printf("🔐 Encrypted data:\n")
	fmt.Printf("   Request ID: %s\n", requestID)
	fmt.Printf("   Encrypted Data: %x...\n", encrypted.EncryptedData[:20]) // Show first 20 bytes
	fmt.Printf("   IV: %x\n", encrypted.IV)
	fmt.Printf("   Auth Tag: %x\n", encrypted.AuthTag)

	// Decrypt the data
	decryptedRequestID, decryptedData, err := encryptor.Decrypt(encrypted)
	if err != nil {
		log.Fatalf("❌ Failed to decrypt data: %v", err)
	}

	fmt.Printf("🔓 Decrypted data:\n")
	fmt.Printf("   Request ID: %s\n", decryptedRequestID)
	fmt.Printf("   Data: %+v\n", decryptedData)

	// Verify integrity
	if requestID == decryptedRequestID {
		fmt.Println("✅ Request ID integrity verified")
	} else {
		fmt.Println("❌ Request ID mismatch!")
	}

	fmt.Println("✅ Encryption/Decryption demo completed successfully")
}

func demonstrateSecureMessaging() {
	fmt.Println("\n🔐 Part 2: Secure Messaging Demo")
	fmt.Println("-------------------------------")

	// Configuration with encryption
	config := &types.MessagingConfig{
		Kafka: types.KafkaConfig{
			Brokers:          []string{"localhost:9092"},
			ClientID:         "encrypted-messaging-client",
			GroupID:          "secure-group",
			RequestTimeoutMs: 30000, // 30 seconds timeout
		},
		Encryption: types.EncryptionConfig{
			Key: getOrGenerateKey(),
		},
		Cassandra: &types.CassandraConfig{
			ContactPoints:   []string{"localhost"},
			LocalDataCenter: "datacenter1",
			Keyspace:        "messaging",
		},
	}

	// Create messaging client
	client, err := messaging.NewMessagingClient(config)
	if err != nil {
		log.Fatalf("❌ Failed to create messaging client: %v", err)
	}

	// Initialize the client
	fmt.Println("🔧 Initializing secure messaging client...")
	if err := client.Initialize(); err != nil {
		log.Fatalf("❌ Failed to initialize client: %v", err)
	}
	fmt.Println("✅ Secure client initialized")

	// Register a secure handler for financial transactions
	err = client.RegisterHandler("secure_transaction", func(request *types.RequestMessage) (interface{}, error) {
		fmt.Printf("🏦 Processing secure transaction: %s\n", request.RequestID)
		
		payload, ok := request.Payload.(map[string]interface{})
		if !ok {
			return nil, fmt.Errorf("invalid payload format")
		}

		// Extract transaction details
		amount, _ := payload["amount"].(float64)
		fromAccount, _ := payload["from_account"].(string)
		toAccount, _ := payload["to_account"].(string)
		transactionType, _ := payload["type"].(string)

		// Simulate secure processing
		fmt.Printf("💰 Processing %s: $%.2f from %s to %s\n", 
			transactionType, amount, fromAccount, toAccount)

		// Simulate validation and processing time
		time.Sleep(1 * time.Second)

		// Generate secure response
		response := map[string]interface{}{
			"transaction_id":   utils.GenerateRequestID(),
			"status":          "approved",
			"amount":          amount,
			"from_account":    fromAccount,
			"to_account":      toAccount,
			"processed_at":    time.Now().Unix(),
			"security_level":  "high",
			"encrypted":       true,
		}

		fmt.Printf("✅ Transaction approved: %s\n", response["transaction_id"])
		return response, nil
	})
	if err != nil {
		log.Fatalf("❌ Failed to register secure handler: %v", err)
	}

	// Start consuming in a goroutine
	go func() {
		if err := client.StartConsuming(); err != nil {
			log.Printf("❌ Failed to start consuming: %v", err)
		}
	}()

	// Wait a moment for consumer to start
	time.Sleep(2 * time.Second)

	// Send secure transaction requests
	fmt.Println("💳 Sending secure transaction requests...")

	transactions := []map[string]interface{}{
		{
			"type":         "transfer",
			"amount":       1500.00,
			"from_account": "ACC-001-SAVINGS",
			"to_account":   "ACC-002-CHECKING",
			"description":  "Monthly transfer",
		},
		{
			"type":         "payment",
			"amount":       89.99,
			"from_account": "ACC-001-CHECKING",
			"to_account":   "VENDOR-AMAZON",
			"description":  "Online purchase",
		},
		{
			"type":         "withdrawal",
			"amount":       200.00,
			"from_account": "ACC-001-CHECKING",
			"to_account":   "ATM-LOCATION-123",
			"description":  "Cash withdrawal",
		},
	}

	for i, transaction := range transactions {
		fmt.Printf("\n📤 Sending transaction %d...\n", i+1)
		
		response, err := client.Request("secure_transaction", transaction, 30*time.Second)
		if err != nil {
			fmt.Printf("❌ Transaction %d failed: %v\n", i+1, err)
			continue
		}

		fmt.Printf("✅ Transaction %d response: %+v\n", i+1, response.Payload)
		
		// Small delay between transactions
		time.Sleep(1 * time.Second)
	}

	fmt.Println("\n🎉 Secure messaging demo completed!")
	fmt.Println("🔐 All messages were automatically encrypted/decrypted")
	fmt.Println("📊 Check Cassandra logs for audit trail (encrypted payloads)")

	// Close the client
	if err := client.Close(); err != nil {
		fmt.Printf("⚠️  Error during shutdown: %v\n", err)
	}
}

// getOrGenerateKey returns a consistent encryption key for the demo
func getOrGenerateKey() string {
	key := os.Getenv("ENCRYPTION_KEY")
	if key == "" {
		// Use a fixed key for this demo so encryption/decryption works
		// In production, always use a secure key from environment or config
		key = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
		fmt.Printf("🔑 Using demo encryption key: %s\n", key)
		fmt.Println("💡 Set ENCRYPTION_KEY environment variable for production use")
	}
	return key
}