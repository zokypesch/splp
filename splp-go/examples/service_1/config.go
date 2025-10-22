package main

import (
	"fmt"
	"log"

	"github.com/kelseyhightower/envconfig"
)

// Config holds all configuration for the service
type Config struct {
	// Kafka Configuration
	Kafka KafkaConfig `envconfig:"KAFKA"`

	// Encryption Configuration
	Encryption EncryptionConfig `envconfig:"ENCRYPTION"`

	// Service Configuration
	Service ServiceConfig `envconfig:"SERVICE"`
}

// KafkaConfig holds Kafka-related configuration
type KafkaConfig struct {
	Brokers           []string `envconfig:"BROKERS" default:"10.70.1.23:9092"`
	GroupID           string   `envconfig:"GROUP_ID" default:"dukcapil-service"`
	ClientID          string   `envconfig:"CLIENT_ID" default:"dukcapil-service"`
	ConsumerTopic     string   `envconfig:"CONSUMER_TOPIC" default:"service-1-topic"`
	ProducerTopic     string   `envconfig:"PRODUCER_TOPIC" default:"command-center-inbox"`
	AutoOffsetReset   string   `envconfig:"AUTO_OFFSET_RESET" default:"earliest"`
	EnableAutoCommit  bool     `envconfig:"ENABLE_AUTO_COMMIT" default:"true"`
	SessionTimeoutMs  int      `envconfig:"SESSION_TIMEOUT_MS" default:"30000"`
	HeartbeatInterval int      `envconfig:"HEARTBEAT_INTERVAL" default:"3000"`
}

// EncryptionConfig holds encryption-related configuration
type EncryptionConfig struct {
	Key string `envconfig:"KEY" required:"true" default:"b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d"`
}

// ServiceConfig holds service-specific configuration
type ServiceConfig struct {
	Name        string `envconfig:"NAME" default:"Dukcapil Service"`
	Version     string `envconfig:"VERSION" default:"1.0.0"`
	Environment string `envconfig:"ENVIRONMENT" default:"development"`
	LogLevel    string `envconfig:"LOG_LEVEL" default:"info"`
}

// LoadConfig loads configuration from environment variables
func LoadConfig() (*Config, error) {
	var config Config

	// Process environment variables with prefix
	err := envconfig.Process("", &config)
	if err != nil {
		return nil, fmt.Errorf("failed to load configuration: %w", err)
	}

	// Validate required fields
	if config.Encryption.Key == "" {
		return nil, fmt.Errorf("ENCRYPTION_KEY is required")
	}

	return &config, nil
}

// PrintConfig prints the current configuration (excluding sensitive data)
func (c *Config) PrintConfig() {
	log.Printf("=== %s Configuration ===", c.Service.Name)
	log.Printf("Service Version: %s", c.Service.Version)
	log.Printf("Environment: %s", c.Service.Environment)
	log.Printf("Log Level: %s", c.Service.LogLevel)
	log.Printf("Kafka Brokers: %v", c.Kafka.Brokers)
	log.Printf("Kafka Group ID: %s", c.Kafka.GroupID)
	log.Printf("Kafka Client ID: %s", c.Kafka.ClientID)
	log.Printf("Consumer Topic: %s", c.Kafka.ConsumerTopic)
	log.Printf("Producer Topic: %s", c.Kafka.ProducerTopic)
	log.Printf("Auto Offset Reset: %s", c.Kafka.AutoOffsetReset)
	log.Printf("Encryption Key: [HIDDEN]")
	log.Printf("================================")
}
