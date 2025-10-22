# Service 1 - Dukcapil (Population Data Verification)

This is the Go implementation of Service 1, which simulates the Dukcapil (Direktorat Jenderal Kependudukan dan Pencatatan Sipil) service for verifying citizen identity and population data.

## Overview

Service 1 receives citizen registration requests from the Command Center, verifies the population data including NIK (National Identity Number) validation, family data, and address verification, then sends the verification results back to the Command Center for routing to Service 2.

## Features

- **NIK Verification**: Validates the 16-digit National Identity Number
- **Data Matching**: Verifies citizen personal information
- **Family Data**: Simulates family member count verification
- **Address Verification**: Confirms address validity
- **Encrypted Communication**: All messages are encrypted using AES-GCM

## Message Flow

1. Receives `BansosCitizenRequest` from `service-1-topic`
2. Decrypts and processes the citizen data
3. Performs population data verification
4. Encrypts the `DukcapilVerificationResult`
5. Sends result to `command-center-inbox` for routing to Service 2

## Data Structures

### Input: BansosCitizenRequest
```go
type BansosCitizenRequest struct {
    RegistrationID  string  `json:"registrationId"`
    NIK             string  `json:"nik"`
    FullName        string  `json:"fullName"`
    DateOfBirth     string  `json:"dateOfBirth"`
    Address         string  `json:"address"`
    AssistanceType  string  `json:"assistanceType"`
    RequestedAmount float64 `json:"requestedAmount"`
}
```

### Output: DukcapilVerificationResult
```go
type DukcapilVerificationResult struct {
    RegistrationID   string  `json:"registrationId"`
    NIK              string  `json:"nik"`
    FullName         string  `json:"fullName"`
    DateOfBirth      string  `json:"dateOfBirth"`
    Address          string  `json:"address"`
    AssistanceType   string  `json:"assistanceType"`
    RequestedAmount  float64 `json:"requestedAmount"`
    ProcessedBy      string  `json:"processedBy"`
    NIKStatus        string  `json:"nikStatus"` // valid, invalid, blocked
    DataMatch        bool    `json:"dataMatch"`
    FamilyMembers    int     `json:"familyMembers"`
    AddressVerified  bool    `json:"addressVerified"`
    VerifiedAt       string  `json:"verifiedAt"`
    Notes            string  `json:"notes,omitempty"`
}
```

## Running the Service

1. Make sure Kafka is running on `localhost:9092`
2. Navigate to the service directory:
   ```bash
   cd examples/service_1
   ```
3. Run the service:
   ```bash
   go run main.go
   ```

## Configuration

- **Kafka Brokers**: `localhost:9092`
- **Client ID**: `dukcapil-service`
- **Group ID**: `service-1-group`
- **Input Topic**: `service-1-topic`
- **Output Topic**: `command-center-inbox`

## Environment Variables

- `ENCRYPTION_KEY`: Optional. If not set, a new key will be generated for each run.

## Dependencies

This service uses the SPLP Go library located at `../../pkg` which provides:
- Kafka wrapper for message handling
- Encryption/decryption utilities
- Common types and interfaces