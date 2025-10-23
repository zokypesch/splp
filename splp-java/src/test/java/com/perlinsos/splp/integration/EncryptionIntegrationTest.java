package com.perlinsos.splp.integration;

import com.perlinsos.splp.crypto.EncryptionService;
import com.perlinsos.splp.types.EncryptedMessage;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.RepeatedTest;

import java.util.*;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("Encryption Integration Tests")
class EncryptionIntegrationTest {

    private ObjectMapper objectMapper;
    private String encryptionKey;

    @BeforeEach
    void setUp() {
        objectMapper = new ObjectMapper();
        encryptionKey = EncryptionService.generateEncryptionKey();
    }

    @Test
    @DisplayName("Should encrypt and decrypt complex objects end-to-end")
    void shouldEncryptAndDecryptComplexObjectsEndToEnd() {
        // Create complex test data
        ComplexTestData testData = new ComplexTestData(
            "John Doe",
            30,
            Arrays.asList("reading", "swimming", "coding"),
            Map.of(
                "address", "123 Main St",
                "phone", "+1234567890",
                "email", "john@example.com"
            ),
            new NestedData("nested value", 42, true)
        );

        String requestId = "INTEGRATION_TEST_001";

        // Encrypt the data
        EncryptedMessage encrypted = EncryptionService.encryptPayload(testData, encryptionKey, requestId);
        
        assertNotNull(encrypted, "Encrypted message should not be null");
        assertEquals(requestId, encrypted.getRequestId(), "Request ID should match");

        // Decrypt the data
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
        
        assertTrue(result.isSuccess(), "Decryption should be successful");
        assertNotNull(result.getPayload(), "Decrypted payload should not be null");

        // Verify the decrypted data
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedMap = (Map<String, Object>) result.getPayload();
        
        assertEquals(testData.getName(), decryptedMap.get("name"));
        assertEquals(testData.getAge(), decryptedMap.get("age"));
        assertEquals(testData.getHobbies(), decryptedMap.get("hobbies"));
        assertEquals(testData.getMetadata(), decryptedMap.get("metadata"));
        
        @SuppressWarnings("unchecked")
        Map<String, Object> nestedMap = (Map<String, Object>) decryptedMap.get("nested");
        assertEquals(testData.getNested().getValue(), nestedMap.get("value"));
        assertEquals(testData.getNested().getNumber(), nestedMap.get("number"));
        assertEquals(testData.getNested().isFlag(), nestedMap.get("flag"));
    }

    @Test
    @DisplayName("Should handle JSON serialization and encryption together")
    void shouldHandleJsonSerializationAndEncryptionTogether() throws Exception {
        // Create test data
        Map<String, Object> testData = Map.of(
            "userId", "user123",
            "action", "login",
            "timestamp", System.currentTimeMillis(),
            "metadata", Map.of("ip", "192.168.1.1", "userAgent", "TestAgent/1.0")
        );

        String requestId = "JSON_TEST_001";

        // First serialize to JSON
        String jsonPayload = objectMapper.writeValueAsString(testData);

        // Then encrypt the JSON
        EncryptedMessage encrypted = EncryptionService.encryptPayload(jsonPayload, encryptionKey, requestId);

        // Decrypt back to JSON
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
        assertTrue(result.isSuccess(), "Decryption should be successful");

        String decryptedJson = (String) result.getPayload();

        // Deserialize back to object
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedData = objectMapper.readValue(decryptedJson, Map.class);

        assertEquals(testData.get("userId"), decryptedData.get("userId"));
        assertEquals(testData.get("action"), decryptedData.get("action"));
        assertEquals(testData.get("timestamp"), decryptedData.get("timestamp"));
        
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedMetadata = (Map<String, Object>) decryptedData.get("metadata");
        @SuppressWarnings("unchecked")
        Map<String, Object> originalMetadata = (Map<String, Object>) testData.get("metadata");
        assertEquals(originalMetadata.get("ip"), decryptedMetadata.get("ip"));
        assertEquals(originalMetadata.get("userAgent"), decryptedMetadata.get("userAgent"));
    }

    @Test
    @DisplayName("Should handle multiple encryption keys correctly")
    void shouldHandleMultipleEncryptionKeysCorrectly() {
        String testPayload = "Multi-key test payload";
        String requestId = "MULTIKEY_TEST_001";

        // Generate multiple keys
        String key1 = EncryptionService.generateEncryptionKey();
        String key2 = EncryptionService.generateEncryptionKey();
        String key3 = EncryptionService.generateEncryptionKey();

        // Encrypt with different keys
        EncryptedMessage encrypted1 = EncryptionService.encryptPayload(testPayload, key1, requestId);
        EncryptedMessage encrypted2 = EncryptionService.encryptPayload(testPayload, key2, requestId);
        EncryptedMessage encrypted3 = EncryptionService.encryptPayload(testPayload, key3, requestId);

        // Verify each can only be decrypted with its own key
        EncryptionService.DecryptionResult result1 = EncryptionService.decryptPayload(encrypted1, key1);
        assertTrue(result1.isSuccess(), "Should decrypt with correct key 1");
        assertEquals(testPayload, result1.getPayload());

        EncryptionService.DecryptionResult result2 = EncryptionService.decryptPayload(encrypted2, key2);
        assertTrue(result2.isSuccess(), "Should decrypt with correct key 2");
        assertEquals(testPayload, result2.getPayload());

        EncryptionService.DecryptionResult result3 = EncryptionService.decryptPayload(encrypted3, key3);
        assertTrue(result3.isSuccess(), "Should decrypt with correct key 3");
        assertEquals(testPayload, result3.getPayload());

        // Verify cross-key decryption fails
        EncryptionService.DecryptionResult wrongResult1 = EncryptionService.decryptPayload(encrypted1, key2);
        assertFalse(wrongResult1.isSuccess(), "Should fail with wrong key");

        EncryptionService.DecryptionResult wrongResult2 = EncryptionService.decryptPayload(encrypted2, key3);
        assertFalse(wrongResult2.isSuccess(), "Should fail with wrong key");

        EncryptionService.DecryptionResult wrongResult3 = EncryptionService.decryptPayload(encrypted3, key1);
        assertFalse(wrongResult3.isSuccess(), "Should fail with wrong key");
    }

    @RepeatedTest(10)
    @DisplayName("Should maintain encryption consistency across multiple runs")
    void shouldMaintainEncryptionConsistencyAcrossMultipleRuns() {
        String testPayload = "Consistency test payload - run " + System.currentTimeMillis();
        String requestId = "CONSISTENCY_TEST_" + System.currentTimeMillis();

        // Encrypt and decrypt multiple times
        for (int i = 0; i < 5; i++) {
            EncryptedMessage encrypted = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
            EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);

            assertTrue(result.isSuccess(), "Decryption should always succeed on iteration " + i);
            assertEquals(testPayload, result.getPayload(), "Payload should match on iteration " + i);
        }
    }

    @Test
    @DisplayName("Should handle concurrent encryption and decryption operations")
    void shouldHandleConcurrentEncryptionAndDecryptionOperations() throws InterruptedException {
        int threadCount = 10;
        int operationsPerThread = 50;
        ExecutorService executor = Executors.newFixedThreadPool(threadCount);
        
        AtomicInteger successCount = new AtomicInteger(0);
        AtomicInteger failureCount = new AtomicInteger(0);

        List<CompletableFuture<Void>> futures = new ArrayList<>();

        for (int i = 0; i < threadCount; i++) {
            final int threadId = i;
            CompletableFuture<Void> future = CompletableFuture.runAsync(() -> {
                for (int j = 0; j < operationsPerThread; j++) {
                    try {
                        String payload = "Thread " + threadId + " Operation " + j;
                        String requestId = "CONCURRENT_" + threadId + "_" + j;

                        // Encrypt
                        EncryptedMessage encrypted = EncryptionService.encryptPayload(payload, encryptionKey, requestId);
                        
                        // Decrypt
                        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
                        
                        if (result.isSuccess() && payload.equals(result.getPayload())) {
                            successCount.incrementAndGet();
                        } else {
                            failureCount.incrementAndGet();
                        }
                    } catch (Exception e) {
                        failureCount.incrementAndGet();
                    }
                }
            }, executor);
            futures.add(future);
        }

        // Wait for all operations to complete
        CompletableFuture.allOf(futures.toArray(new CompletableFuture[0]))
                .get(60, TimeUnit.SECONDS);

        executor.shutdown();
        assertTrue(executor.awaitTermination(10, TimeUnit.SECONDS), "Executor should terminate");

        int expectedOperations = threadCount * operationsPerThread;
        assertEquals(expectedOperations, successCount.get(), "All operations should succeed");
        assertEquals(0, failureCount.get(), "No operations should fail");
    }

    @Test
    @DisplayName("Should handle very large payloads efficiently")
    void shouldHandleVeryLargePayloadsEfficiently() {
        // Create a large payload (5MB)
        StringBuilder largePayloadBuilder = new StringBuilder();
        String baseString = "This is a large payload for testing encryption performance and memory usage. ";
        for (int i = 0; i < 70000; i++) {
            largePayloadBuilder.append(baseString).append(i).append(" ");
        }
        String largePayload = largePayloadBuilder.toString();
        
        String requestId = "LARGE_PAYLOAD_TEST";

        long startTime = System.currentTimeMillis();

        // Encrypt large payload
        EncryptedMessage encrypted = EncryptionService.encryptPayload(largePayload, encryptionKey, requestId);
        
        long encryptionTime = System.currentTimeMillis() - startTime;
        
        assertNotNull(encrypted, "Large payload should be encrypted successfully");
        assertTrue(encryptionTime < 10000, "Encryption should complete within 10 seconds");

        startTime = System.currentTimeMillis();

        // Decrypt large payload
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
        
        long decryptionTime = System.currentTimeMillis() - startTime;

        assertTrue(result.isSuccess(), "Large payload should be decrypted successfully");
        assertEquals(largePayload, result.getPayload(), "Decrypted large payload should match original");
        assertTrue(decryptionTime < 10000, "Decryption should complete within 10 seconds");
    }

    @Test
    @DisplayName("Should handle edge cases in real-world scenarios")
    void shouldHandleEdgeCasesInRealWorldScenarios() {
        // Test various edge cases that might occur in real applications
        
        // Empty payload
        EncryptedMessage emptyEncrypted = EncryptionService.encryptPayload("", encryptionKey, "EMPTY_TEST");
        EncryptionService.DecryptionResult emptyResult = EncryptionService.decryptPayload(emptyEncrypted, encryptionKey);
        assertTrue(emptyResult.isSuccess(), "Empty payload should encrypt/decrypt successfully");
        assertEquals("", emptyResult.getPayload(), "Empty payload should remain empty");

        // Special characters
        String specialChars = "!@#$%^&*()_+-=[]{}|;':\",./<>?`~äöüß中文🚀";
        EncryptedMessage specialEncrypted = EncryptionService.encryptPayload(specialChars, encryptionKey, "SPECIAL_TEST");
        EncryptionService.DecryptionResult specialResult = EncryptionService.decryptPayload(specialEncrypted, encryptionKey);
        assertTrue(specialResult.isSuccess(), "Special characters should encrypt/decrypt successfully");
        assertEquals(specialChars, specialResult.getPayload(), "Special characters should be preserved");

        // Numeric payload
        Integer numericPayload = 42;
        EncryptedMessage numericEncrypted = EncryptionService.encryptPayload(numericPayload, encryptionKey, "NUMERIC_TEST");
        EncryptionService.DecryptionResult numericResult = EncryptionService.decryptPayload(numericEncrypted, encryptionKey);
        assertTrue(numericResult.isSuccess(), "Numeric payload should encrypt/decrypt successfully");
        assertEquals(numericPayload, numericResult.getPayload(), "Numeric payload should be preserved");

        // Boolean payload
        Boolean booleanPayload = true;
        EncryptedMessage booleanEncrypted = EncryptionService.encryptPayload(booleanPayload, encryptionKey, "BOOLEAN_TEST");
        EncryptionService.DecryptionResult booleanResult = EncryptionService.decryptPayload(booleanEncrypted, encryptionKey);
        assertTrue(booleanResult.isSuccess(), "Boolean payload should encrypt/decrypt successfully");
        assertEquals(booleanPayload, booleanResult.getPayload(), "Boolean payload should be preserved");

        // Array payload
        List<String> arrayPayload = Arrays.asList("item1", "item2", "item3");
        EncryptedMessage arrayEncrypted = EncryptionService.encryptPayload(arrayPayload, encryptionKey, "ARRAY_TEST");
        EncryptionService.DecryptionResult arrayResult = EncryptionService.decryptPayload(arrayEncrypted, encryptionKey);
        assertTrue(arrayResult.isSuccess(), "Array payload should encrypt/decrypt successfully");
        assertEquals(arrayPayload, arrayResult.getPayload(), "Array payload should be preserved");
    }

    @Test
    @DisplayName("Should maintain data integrity across serialization boundaries")
    void shouldMaintainDataIntegrityAcrossSerializationBoundaries() throws Exception {
        // Create complex nested data structure
        Map<String, Object> complexData = new HashMap<>();
        complexData.put("string", "test string");
        complexData.put("integer", 123);
        complexData.put("double", 45.67);
        complexData.put("boolean", true);
        complexData.put("array", Arrays.asList(1, 2, 3, "four", 5.0));
        complexData.put("nested", Map.of(
            "level2", Map.of(
                "level3", "deep value",
                "numbers", Arrays.asList(1, 2, 3)
            )
        ));

        String requestId = "INTEGRITY_TEST";

        // Encrypt the complex data
        EncryptedMessage encrypted = EncryptionService.encryptPayload(complexData, encryptionKey, requestId);

        // Serialize the encrypted message to JSON (simulating network transmission)
        String serializedEncrypted = objectMapper.writeValueAsString(encrypted);

        // Deserialize back from JSON
        EncryptedMessage deserializedEncrypted = objectMapper.readValue(serializedEncrypted, EncryptedMessage.class);

        // Decrypt the deserialized message
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(deserializedEncrypted, encryptionKey);

        assertTrue(result.isSuccess(), "Decryption should succeed after serialization round-trip");
        
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedData = (Map<String, Object>) result.getPayload();

        // Verify all data types are preserved
        assertEquals(complexData.get("string"), decryptedData.get("string"));
        assertEquals(complexData.get("integer"), decryptedData.get("integer"));
        assertEquals(complexData.get("double"), decryptedData.get("double"));
        assertEquals(complexData.get("boolean"), decryptedData.get("boolean"));
        assertEquals(complexData.get("array"), decryptedData.get("array"));
        
        @SuppressWarnings("unchecked")
        Map<String, Object> originalNested = (Map<String, Object>) complexData.get("nested");
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedNested = (Map<String, Object>) decryptedData.get("nested");
        
        @SuppressWarnings("unchecked")
        Map<String, Object> originalLevel2 = (Map<String, Object>) originalNested.get("level2");
        @SuppressWarnings("unchecked")
        Map<String, Object> decryptedLevel2 = (Map<String, Object>) decryptedNested.get("level2");
        
        assertEquals(originalLevel2.get("level3"), decryptedLevel2.get("level3"));
        assertEquals(originalLevel2.get("numbers"), decryptedLevel2.get("numbers"));
    }

    // Test data classes
    private static class ComplexTestData {
        private String name;
        private int age;
        private List<String> hobbies;
        private Map<String, String> metadata;
        private NestedData nested;

        public ComplexTestData() {}

        public ComplexTestData(String name, int age, List<String> hobbies, Map<String, String> metadata, NestedData nested) {
            this.name = name;
            this.age = age;
            this.hobbies = hobbies;
            this.metadata = metadata;
            this.nested = nested;
        }

        // Getters and setters
        public String getName() { return name; }
        public void setName(String name) { this.name = name; }

        public int getAge() { return age; }
        public void setAge(int age) { this.age = age; }

        public List<String> getHobbies() { return hobbies; }
        public void setHobbies(List<String> hobbies) { this.hobbies = hobbies; }

        public Map<String, String> getMetadata() { return metadata; }
        public void setMetadata(Map<String, String> metadata) { this.metadata = metadata; }

        public NestedData getNested() { return nested; }
        public void setNested(NestedData nested) { this.nested = nested; }
    }

    private static class NestedData {
        private String value;
        private int number;
        private boolean flag;

        public NestedData() {}

        public NestedData(String value, int number, boolean flag) {
            this.value = value;
            this.number = number;
            this.flag = flag;
        }

        // Getters and setters
        public String getValue() { return value; }
        public void setValue(String value) { this.value = value; }

        public int getNumber() { return number; }
        public void setNumber(int number) { this.number = number; }

        public boolean isFlag() { return flag; }
        public void setFlag(boolean flag) { this.flag = flag; }
    }
}