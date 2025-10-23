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
        Arrays.asList("localhost:9092"),
        "dukcapil-service",
        "dukcapil-group"
    );

    // Encryption key (in production, load from secure configuration)
    private static final String ENCRYPTION_KEY = "your-32-character-encryption-key-here-12345";

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
            BansosCitizenRequest citizenRequest = (BansosCitizenRequest) EncryptionService.decryptPayload(
                encryptedMessage, ENCRYPTION_KEY);

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

            // Send to command-center-inbox for routing to service_2
            String resultJson = objectMapper.writeValueAsString(encryptedResult);
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
        logger.info("Verifying citizen data for: {} (NIK: {})", request.getName(), request.getNik());

        // Simulate NIK validation
        String nikStatus = validateNik(request.getNik()) ? "VALID" : "INVALID";

        // Simulate data matching
        boolean dataMatch = simulateDataMatching(request);

        // Simulate family member verification
        List<FamilyMember> familyMembers = simulateFamilyMemberVerification(request.getNik());

        // Simulate address verification
        AddressVerification addressVerification = simulateAddressVerification(request.getAddress());

        return new DukcapilVerificationResult(
            request.getNik(),
            request.getName(),
            nikStatus,
            dataMatch,
            request.getDateOfBirth(),
            request.getAddress(),
            familyMembers,
            addressVerification,
            Instant.now()
        );
    }

    private static boolean validateNik(String nik) {
        // Simple NIK validation (16 digits)
        return nik != null && nik.matches("\\d{16}");
    }

    private static boolean simulateDataMatching(BansosCitizenRequest request) {
        // Simulate 90% success rate for data matching
        return Math.random() > 0.1;
    }

    private static List<FamilyMember> simulateFamilyMemberVerification(String nik) {
        // Simulate family member data
        return Arrays.asList(
            new FamilyMember("1234567890123457", "John Doe Jr", "CHILD", "ACTIVE"),
            new FamilyMember("1234567890123458", "Jane Doe", "SPOUSE", "ACTIVE")
        );
    }

    private static AddressVerification simulateAddressVerification(String address) {
        // Simulate address verification
        return new AddressVerification(
            true,
            "12345",
            "Central Jakarta",
            "DKI Jakarta",
            "Verified address matches population registry"
        );
    }

    private static void logProcessingSteps(DukcapilVerificationResult result) {
        logger.info("=== Dukcapil Verification Results ===");
        logger.info("NIK: {} - Status: {}", result.getNik(), result.getNikStatus());
        logger.info("Name: {}", result.getName());
        logger.info("Date of Birth: {}", result.getDateOfBirth());
        logger.info("Data Match: {}", result.isDataMatch() ? "YES" : "NO");
        logger.info("Address Verified: {}", result.getAddressVerification().isVerified() ? "YES" : "NO");
        logger.info("Family Members Found: {}", result.getFamilyMembers().size());
        
        for (FamilyMember member : result.getFamilyMembers()) {
            logger.info("  - {} ({}) - {}", member.getName(), member.getRelation(), member.getStatus());
        }
        
        logger.info("Verification completed at: {}", result.getVerificationTimestamp());
        logger.info("=====================================");
    }

    // Data classes for the service
    public static class BansosCitizenRequest {
        private String nik;
        private String name;
        private LocalDate dateOfBirth;
        private String address;

        // Constructors
        public BansosCitizenRequest() {}

        public BansosCitizenRequest(String nik, String name, LocalDate dateOfBirth, String address) {
            this.nik = nik;
            this.name = name;
            this.dateOfBirth = dateOfBirth;
            this.address = address;
        }

        // Getters and setters
        public String getNik() { return nik; }
        public void setNik(String nik) { this.nik = nik; }

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }

        public LocalDate getDateOfBirth() { return dateOfBirth; }
        public void setDateOfBirth(LocalDate dateOfBirth) { this.dateOfBirth = dateOfBirth; }

        public String getAddress() { return address; }
        public void setAddress(String address) { this.address = address; }
    }

    public static class DukcapilVerificationResult {
        private String nik;
        private String name;
        private String nikStatus;
        private boolean dataMatch;
        private LocalDate dateOfBirth;
        private String address;
        private List<FamilyMember> familyMembers;
        private AddressVerification addressVerification;
        private Instant verificationTimestamp;

        // Constructors
        public DukcapilVerificationResult() {}

        public DukcapilVerificationResult(String nik, String name, String nikStatus, boolean dataMatch,
                                        LocalDate dateOfBirth, String address, List<FamilyMember> familyMembers,
                                        AddressVerification addressVerification, Instant verificationTimestamp) {
            this.nik = nik;
            this.name = name;
            this.nikStatus = nikStatus;
            this.dataMatch = dataMatch;
            this.dateOfBirth = dateOfBirth;
            this.address = address;
            this.familyMembers = familyMembers;
            this.addressVerification = addressVerification;
            this.verificationTimestamp = verificationTimestamp;
        }

        // Getters and setters
        public String getNik() { return nik; }
        public void setNik(String nik) { this.nik = nik; }

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }

        public String getNikStatus() { return nikStatus; }
        public void setNikStatus(String nikStatus) { this.nikStatus = nikStatus; }

        public boolean isDataMatch() { return dataMatch; }
        public void setDataMatch(boolean dataMatch) { this.dataMatch = dataMatch; }

        public LocalDate getDateOfBirth() { return dateOfBirth; }
        public void setDateOfBirth(LocalDate dateOfBirth) { this.dateOfBirth = dateOfBirth; }

        public String getAddress() { return address; }
        public void setAddress(String address) { this.address = address; }

        public List<FamilyMember> getFamilyMembers() { return familyMembers; }
        public void setFamilyMembers(List<FamilyMember> familyMembers) { this.familyMembers = familyMembers; }

        public AddressVerification getAddressVerification() { return addressVerification; }
        public void setAddressVerification(AddressVerification addressVerification) { this.addressVerification = addressVerification; }

        public Instant getVerificationTimestamp() { return verificationTimestamp; }
        public void setVerificationTimestamp(Instant verificationTimestamp) { this.verificationTimestamp = verificationTimestamp; }
    }

    public static class FamilyMember {
        private String nik;
        private String name;
        private String relation;
        private String status;

        // Constructors
        public FamilyMember() {}

        public FamilyMember(String nik, String name, String relation, String status) {
            this.nik = nik;
            this.name = name;
            this.relation = relation;
            this.status = status;
        }

        // Getters and setters
        public String getNik() { return nik; }
        public void setNik(String nik) { this.nik = nik; }

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }

        public String getRelation() { return relation; }
        public void setRelation(String relation) { this.relation = relation; }

        public String getStatus() { return status; }
        public void setStatus(String status) { this.status = status; }
    }

    public static class AddressVerification {
        private boolean verified;
        private String postalCode;
        private String district;
        private String province;
        private String notes;

        // Constructors
        public AddressVerification() {}

        public AddressVerification(boolean verified, String postalCode, String district, String province, String notes) {
            this.verified = verified;
            this.postalCode = postalCode;
            this.district = district;
            this.province = province;
            this.notes = notes;
        }

        // Getters and setters
        public boolean isVerified() { return verified; }
        public void setVerified(boolean verified) { this.verified = verified; }

        public String getPostalCode() { return postalCode; }
        public void setPostalCode(String postalCode) { this.postalCode = postalCode; }

        public String getDistrict() { return district; }
        public void setDistrict(String district) { this.district = district; }

        public String getProvince() { return province; }
        public void setProvince(String province) { this.province = province; }

        public String getNotes() { return notes; }
        public void setNotes(String notes) { this.notes = notes; }
    }
}