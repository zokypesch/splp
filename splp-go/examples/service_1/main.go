/**
 * Dukcapil - Direktorat Jenderal Kependudukan dan Pencatatan Sipil
 * Verifies citizen identity and population data
 * Checks NIK validity, family data, and address verification
 */

package main

import (
	"encoding/json"
	"fmt"
	"log"
	"math/rand"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"github.com/perlinsos/splp-go/pkg/crypto"
	"github.com/perlinsos/splp-go/pkg/kafka"
	"github.com/perlinsos/splp-go/pkg/types"
)

// BansosCitizenRequest represents the incoming request structure
type BansosCitizenRequest struct {
	RegistrationID  string  `json:"registrationId"`
	NIK             string  `json:"nik"`
	FullName        string  `json:"fullName"`
	DateOfBirth     string  `json:"dateOfBirth"`
	Address         string  `json:"address"`
	AssistanceType  string  `json:"assistanceType"`
	RequestedAmount float64 `json:"requestedAmount"`
}

// DukcapilVerificationResult represents the processed result
type DukcapilVerificationResult struct {
	RegistrationID  string  `json:"registrationId"`
	NIK             string  `json:"nik"`
	FullName        string  `json:"fullName"`
	DateOfBirth     string  `json:"dateOfBirth"`
	Address         string  `json:"address"`
	AssistanceType  string  `json:"assistanceType"`
	RequestedAmount float64 `json:"requestedAmount"`
	ProcessedBy     string  `json:"processedBy"`
	NIKStatus       string  `json:"nikStatus"` // valid, invalid, blocked
	DataMatch       bool    `json:"dataMatch"`
	FamilyMembers   int     `json:"familyMembers"`
	AddressVerified bool    `json:"addressVerified"`
	VerifiedAt      string  `json:"verifiedAt"`
	Notes           string  `json:"notes,omitempty"`
}

// CommandCenterMessage represents the message format for Command Center
type CommandCenterMessage struct {
	RequestID  string `json:"request_id"`
	WorkerName string `json:"worker_name"`
	Data       string `json:"data"`
	IV         string `json:"iv"`
	Tag        string `json:"tag"`
}

func main() {
	fmt.Println("═══════════════════════════════════════════════════════════")
	fmt.Println("🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil")
	fmt.Println("    Population Data Verification Service")
	fmt.Println("═══════════════════════════════════════════════════════════\n")

	// Load configuration from environment variables
	config, err := LoadConfig()
	if err != nil {
		log.Fatalf("❌ Failed to load configuration: %v", err)
	}

	// Print configuration (excluding sensitive data)
	config.PrintConfig()
	fmt.Println()

	// Create Kafka configuration
	kafkaConfig := &types.KafkaConfig{
		Brokers:          config.Kafka.Brokers,
		ClientID:         config.Kafka.ClientID,
		GroupID:          config.Kafka.GroupID,
		RequestTimeoutMs: 30000,
	}

	// Create Kafka wrapper
	kafkaWrapper, err := kafka.NewKafkaWrapper(kafkaConfig)
	if err != nil {
		log.Fatalf("❌ Failed to create Kafka wrapper: %v", err)
	}
	if err := kafkaWrapper.Initialize(); err != nil {
		log.Fatalf("❌ Failed to initialize Kafka: %v", err)
	}

	// Create encryptor
	encryptor, err := crypto.NewEncryptor(config.Encryption.Key)
	if err != nil {
		log.Fatalf("❌ Failed to create encryptor: %v", err)
	}

	fmt.Println("✓ Dukcapil terhubung ke Kafka")
	fmt.Printf("✓ Listening on topic: %s (group: %s)\n", config.Kafka.ConsumerTopic, config.Kafka.GroupID)
	fmt.Println("✓ Siap memverifikasi data kependudukan\n")

	// Set up message processor
	processor := &MessageProcessor{
		encryptor:    encryptor,
		kafkaWrapper: kafkaWrapper,
		config:       config,
	}
	kafkaWrapper.SetMessageHandler(processor)

	// Start consuming
	if err := kafkaWrapper.StartConsuming([]string{config.Kafka.ConsumerTopic}); err != nil {
		log.Fatalf("❌ Failed to start consuming: %v", err)
	}

	fmt.Printf("Service 1 (%s) is running and waiting for messages...\n", config.Service.Name)
	fmt.Println("Press Ctrl+C to exit\n")

	// Wait for interrupt signal
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)
	<-sigChan

	fmt.Printf("\n\nShutting down %s...\n", config.Service.Name)
	kafkaWrapper.Close()
}

// MessageProcessor handles incoming messages
type MessageProcessor struct {
	encryptor    types.Encryptor
	kafkaWrapper types.KafkaClient
	config       *Config
}

// ProcessMessage implements the MessageProcessor interface
func (p *MessageProcessor) ProcessMessage(message []byte) error {
	startTime := time.Now()

	fmt.Println(strings.Repeat("─", 60))
	fmt.Println("📥 [DUKCAPIL] Menerima data dari Command Center")

	// Parse encrypted message
	var encryptedMsg types.EncryptedMessage
	if err := json.Unmarshal(message, &encryptedMsg); err != nil {
		return fmt.Errorf("failed to parse encrypted message: %w", err)
	}

	// Decrypt the message
	requestID, decryptedData, err := p.encryptor.Decrypt(&encryptedMsg)
	if err != nil {
		return fmt.Errorf("failed to decrypt message: %w", err)
	}

	// Parse the decrypted payload
	var payload BansosCitizenRequest
	switch v := decryptedData.(type) {
	case map[string]interface{}:
		// Convert map to JSON and then to struct
		jsonBytes, err := json.Marshal(v)
		if err != nil {
			return fmt.Errorf("failed to marshal decrypted data: %w", err)
		}
		if err := json.Unmarshal(jsonBytes, &payload); err != nil {
			return fmt.Errorf("failed to unmarshal payload: %w", err)
		}
	default:
		return fmt.Errorf("unexpected decrypted data type: %T", decryptedData)
	}

	fmt.Printf("  Request ID: %s\n", requestID)
	fmt.Printf("  Registration ID: %s\n", payload.RegistrationID)
	fmt.Printf("  NIK: %s\n", payload.NIK)
	fmt.Printf("  Nama: %s\n", payload.FullName)
	fmt.Printf("  Tanggal Lahir: %s\n", payload.DateOfBirth)
	fmt.Printf("  Alamat: %s\n", payload.Address)
	fmt.Println()

	// Verify population data
	fmt.Println("🔄 Memverifikasi data kependudukan...")
	time.Sleep(1 * time.Second) // Simulate verification

	// Simulate NIK verification
	nikValid := len(payload.NIK) == 16 && strings.HasPrefix(payload.NIK, "317") // Jakarta NIK
	dataMatch := len(payload.FullName) > 0
	familyMembers := rand.Intn(5) + 1 // 1-5 family members

	processedData := DukcapilVerificationResult{
		RegistrationID:  payload.RegistrationID,
		NIK:             payload.NIK,
		FullName:        payload.FullName,
		DateOfBirth:     payload.DateOfBirth,
		Address:         payload.Address,
		AssistanceType:  payload.AssistanceType,
		RequestedAmount: payload.RequestedAmount,
		ProcessedBy:     "dukcapil",
		NIKStatus: func() string {
			if nikValid {
				return "valid"
			} else {
				return "invalid"
			}
		}(),
		DataMatch:       dataMatch,
		FamilyMembers:   familyMembers,
		AddressVerified: true,
		VerifiedAt:      time.Now().Format(time.RFC3339),
		Notes: func() string {
			if nikValid {
				return "Data kependudukan terverifikasi"
			} else {
				return "NIK tidak valid"
			}
		}(),
	}

	fmt.Printf("  ✅ Status NIK: %s\n", strings.ToUpper(processedData.NIKStatus))
	fmt.Printf("  ✅ Data Cocok: %s\n", func() string {
		if dataMatch {
			return "YA"
		} else {
			return "TIDAK"
		}
	}())
	fmt.Printf("  ✅ Jumlah Anggota Keluarga: %d\n", familyMembers)
	fmt.Printf("  ✅ Alamat Terverifikasi: %s\n", func() string {
		if processedData.AddressVerified {
			return "YA"
		} else {
			return "TIDAK"
		}
	}())
	fmt.Printf("  📋 Catatan: %s\n", processedData.Notes)
	fmt.Printf("  🏢 Diproses oleh: DUKCAPIL\n")
	fmt.Println()

	// Encrypt processed data
	encryptedResult, err := p.encryptor.Encrypt(processedData, requestID)
	if err != nil {
		return fmt.Errorf("failed to encrypt result: %w", err)
	}

	// Send back to Command Center for routing to service_2
	outgoingMessage := CommandCenterMessage{
		RequestID:  requestID,
		WorkerName: "service-1-publisher", // This identifies routing: service_1 -> service_2
		Data:       encryptedResult.Data,
		IV:         encryptedResult.IV,
		Tag:        encryptedResult.Tag,
	}

	messageBytes, err := json.Marshal(outgoingMessage)
	if err != nil {
		return fmt.Errorf("failed to marshal outgoing message: %w", err)
	}

	fmt.Println("📤 Mengirim hasil verifikasi ke Command Center...")
	if err := p.kafkaWrapper.SendMessage(p.config.Kafka.ProducerTopic, string(messageBytes)); err != nil {
		return fmt.Errorf("failed to send message: %w", err)
	}

	duration := time.Since(startTime)
	fmt.Println("✓ Hasil verifikasi terkirim ke Command Center")
	fmt.Printf("  Waktu proses: %v\n", duration)
	fmt.Println(strings.Repeat("─", 60))
	fmt.Println()

	return nil
}
