package com.perlinsos.splp.examples.service1;

import com.perlinsos.splp.crypto.EncryptionService;
import com.perlinsos.splp.kafka.KafkaWrapper;
import com.perlinsos.splp.types.KafkaConfig;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import java.time.Instant;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.Arrays;
import java.util.List;
import java.util.concurrent.CompletableFuture;

/**
 * Service 1 - Dukcapil Service
 * Verifies citizen identity and population data
 */
public class Service1Application {
    private static final Logger logger = LoggerFactory.getLogger(Service1Application.class);
    private static final ObjectMapper objectMapper = new ObjectMapper();

    // Kafka configuration
    private static final KafkaConfig KAFKA_CONFIG = new KafkaConfig(
        Arrays.asList("10.70.1.23:9092"),
        "dukcapil-service",
        "service-1f-group"
    );

    // Encryption key (in production, load from secure configuration)
    private static final String ENCRYPTION_KEY = "b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d";

    private static KafkaWrapper kafka;

    public static void main(String[] args) {
        logger.info("Starting Dukcapil Service (Service 1)...");

        try {
            // Initialize Kafka
            kafka = new KafkaWrapper(KAFKA_CONFIG);
            
            // Connect producer and consumer
            CompletableFuture.allOf(
                kafka.connectProducer().thenApply(p -> null),
                kafka.connectConsumer().thenApply(c -> null)
            ).join();

            // Subscribe to service-1-topic
            kafka.subscribe(Arrays.asList("service-1-topic"), payload -> {
                try {
                    processMessage(payload.getValue());
                } catch (Exception e) {
                    logger.error("Error processing message: {}", e.getMessage(), e);
                }
            }).join();

            logger.info("Dukcapil Service is running and listening for messages...");

            // Keep the application running
            Runtime.getRuntime().addShutdownHook(new Thread(() -> {
                logger.info("Shutting down Dukcapil Service...");
                try {
                    kafka.disconnect().join();
                    logger.info("Dukcapil Service stopped gracefully");
                } catch (Exception e) {
                    logger.error("Error during shutdown: {}", e.getMessage(), e);
                }
            }));

            // Keep main thread alive
            Thread.currentThread().join();

        } catch (Exception e) {
            logger.error("Failed to start Dukcapil Service: {}", e.getMessage(), e);
            System.exit(1);
        }
    }

    private static void processMessage(String encryptedMessageJson) {
        long startTime = System.currentTimeMillis();
        String requestId = "";

        try {
            logger.info("Processing encrypted message from service-1-topic");

            // Parse and decrypt the message
            var encryptedMessage = objectMapper.readValue(encryptedMessageJson, 
                com.perlinsos.splp.types.EncryptedMessage.class);
            requestId = encryptedMessage.getRequestId();

            logger.info("Decrypting message with request ID: {}", requestId);

            // Decrypt the payload
            var decryptionResult = EncryptionService.decryptPayload(
                encryptedMessage, ENCRYPTION_KEY, BansosCitizenRequest.class);
            BansosCitizenRequest citizenRequest = decryptionResult.getPayload();

            logger.info("Processing citizen verification for NIK: {}", citizenRequest.getNik());

            // Simulate data verification process
            DukcapilVerificationResult result = verifyCitizenData(citizenRequest);

            logger.info("Citizen verification completed for NIK: {} - Status: {}", 
                citizenRequest.getNik(), result.getNikStatus());

            // Log processing steps
            logProcessingSteps(result);

            // Encrypt the result
            logger.info("Encrypting verification result for transmission");
            var encryptedResult = EncryptionService.encryptPayload(result, ENCRYPTION_KEY, requestId);

            // Create outgoing message with routing information (matching Bun version)
            var outgoingMessage = new com.perlinsos.splp.types.OutgoingMessage(
                requestId,
                "service-1-publisher", // This identifies routing: service_1 -> service_2
                encryptedResult.getData(),
                encryptedResult.getIv(),
                encryptedResult.getTag()
            );

            // Send to command-center-inbox for routing to service_2
            String resultJson = objectMapper.writeValueAsString(outgoingMessage);
            kafka.sendMessage("command-center-inbox", resultJson, requestId).join();

            long processingTime = System.currentTimeMillis() - startTime;
            logger.info("Message processed and sent to command-center-inbox in {}ms", processingTime);

        } catch (Exception e) {
            long processingTime = System.currentTimeMillis() - startTime;
            logger.error("Error processing message {} ({}ms): {}", requestId, processingTime, e.getMessage(), e);
        }
    }

    private static DukcapilVerificationResult verifyCitizenData(BansosCitizenRequest request) {
        // Simulate verification process with realistic data
        logger.info("Verifying citizen data for: {} (NIK: {})", request.getFullName(), request.getNik());

        // Simulate NIK validation
        String nikStatus = validateNik(request.getNik()) ? "valid" : "invalid";

        // Simulate data matching
        boolean dataMatch = simulateDataMatching(request);

        // Simulate family member count (instead of list)
        int familyMembersCount = 2; // Simulate 2 family members

        // Simulate address verification
        boolean addressVerified = Math.random() > 0.1; // 90% success rate

        // Create result matching TypeScript interface
        DukcapilVerificationResult result = new DukcapilVerificationResult();
        result.setRegistrationId(request.getRegistrationId());
        result.setNik(request.getNik());
        result.setFullName(request.getFullName());
        result.setDateOfBirth(request.getDateOfBirth());
        result.setAddress(request.getAddress());
        result.setAssistanceType(request.getAssistanceType());
        result.setRequestedAmount(request.getRequestedAmount());
        result.setProcessedBy("Dukcapil Service");
        result.setNikStatus(nikStatus);
        result.setDataMatch(dataMatch);
        result.setFamilyMembers(familyMembersCount);
        result.setAddressVerified(addressVerified);
        result.setVerifiedAt(Instant.now().toString());
        result.setNotes(dataMatch && addressVerified ? "Verification successful" : "Some verification checks failed");

        return result;
    }

    private static boolean validateNik(String nik) {
        // Simple NIK validation (16 digits)
        return nik != null && nik.matches("\\d{16}");
    }

    private static boolean simulateDataMatching(BansosCitizenRequest request) {
        // Simulate 90% success rate for data matching
        return Math.random() > 0.1;
    }

    private static void logProcessingSteps(DukcapilVerificationResult result) {
        logger.info("=== Dukcapil Verification Results ===");
        logger.info("Registration ID: {}", result.getRegistrationId());
        logger.info("NIK: {} - Status: {}", result.getNik(), result.getNikStatus());
        logger.info("Full Name: {}", result.getFullName());
        logger.info("Date of Birth: {}", result.getDateOfBirth());
        logger.info("Address: {}", result.getAddress());
        logger.info("Assistance Type: {}", result.getAssistanceType());
        logger.info("Requested Amount: {}", result.getRequestedAmount());
        logger.info("Data Match: {}", result.isDataMatch() ? "YES" : "NO");
        logger.info("Address Verified: {}", result.isAddressVerified() ? "YES" : "NO");
        logger.info("Family Members Count: {}", result.getFamilyMembers());
        logger.info("Processed By: {}", result.getProcessedBy());
        logger.info("Verified At: {}", result.getVerifiedAt());
        logger.info("Notes: {}", result.getNotes());
        logger.info("=====================================");
    }

    // Data classes for the service
    @com.fasterxml.jackson.annotation.JsonIgnoreProperties(ignoreUnknown = true)
    public static class BansosCitizenRequest {
        private String registrationId;
        private String nik;
        private String fullName;
        private String dateOfBirth;
        private String address;
        private String assistanceType;
        private Long requestedAmount;

        // Constructors
        public BansosCitizenRequest() {}

        // Getters and setters
        public String getRegistrationId() { return registrationId; }
        public void setRegistrationId(String registrationId) { this.registrationId = registrationId; }
        
        public String getNik() { return nik; }
        public void setNik(String nik) { this.nik = nik; }

        public String getFullName() { return fullName; }
        public void setFullName(String fullName) { this.fullName = fullName; }

        public String getDateOfBirth() { return dateOfBirth; }
        public void setDateOfBirth(String dateOfBirth) { this.dateOfBirth = dateOfBirth; }

        public String getAddress() { return address; }
        public void setAddress(String address) { this.address = address; }
        
        public String getAssistanceType() { return assistanceType; }
        public void setAssistanceType(String assistanceType) { this.assistanceType = assistanceType; }
        
        public Long getRequestedAmount() { return requestedAmount; }
        public void setRequestedAmount(Long requestedAmount) { this.requestedAmount = requestedAmount; }
    }

    @com.fasterxml.jackson.annotation.JsonIgnoreProperties(ignoreUnknown = true)
    public static class DukcapilVerificationResult {
        private String registrationId;
        private String nik;
        
        @com.fasterxml.jackson.annotation.JsonProperty("fullName")
        private String fullName;
        
        private String dateOfBirth;
        private String address;
        private String assistanceType;
        private Long requestedAmount;
        private String processedBy;
        private String nikStatus;
        private boolean dataMatch;
        private int familyMembers;
        private boolean addressVerified;
        private String verifiedAt;
        private String notes;

        // Constructors
        public DukcapilVerificationResult() {}

        // Getters and setters
        public String getRegistrationId() { return registrationId; }
        public void setRegistrationId(String registrationId) { this.registrationId = registrationId; }

        public String getNik() { return nik; }
        public void setNik(String nik) { this.nik = nik; }

        public String getFullName() { return fullName; }
        public void setFullName(String fullName) { this.fullName = fullName; }

        public String getDateOfBirth() { return dateOfBirth; }
        public void setDateOfBirth(String dateOfBirth) { this.dateOfBirth = dateOfBirth; }

        public String getAddress() { return address; }
        public void setAddress(String address) { this.address = address; }

        public String getAssistanceType() { return assistanceType; }
        public void setAssistanceType(String assistanceType) { this.assistanceType = assistanceType; }

        public Long getRequestedAmount() { return requestedAmount; }
        public void setRequestedAmount(Long requestedAmount) { this.requestedAmount = requestedAmount; }

        public String getProcessedBy() { return processedBy; }
        public void setProcessedBy(String processedBy) { this.processedBy = processedBy; }

        public String getNikStatus() { return nikStatus; }
        public void setNikStatus(String nikStatus) { this.nikStatus = nikStatus; }

        public boolean isDataMatch() { return dataMatch; }
        public void setDataMatch(boolean dataMatch) { this.dataMatch = dataMatch; }

        public int getFamilyMembers() { return familyMembers; }
        public void setFamilyMembers(int familyMembers) { this.familyMembers = familyMembers; }

        public boolean isAddressVerified() { return addressVerified; }
        public void setAddressVerified(boolean addressVerified) { this.addressVerified = addressVerified; }

        public String getVerifiedAt() { return verifiedAt; }
        public void setVerifiedAt(String verifiedAt) { this.verifiedAt = verifiedAt; }

        public String getNotes() { return notes; }
        public void setNotes(String notes) { this.notes = notes; }
    }

}