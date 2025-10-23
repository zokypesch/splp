package com.perlinsos.splp.crypto;

import com.perlinsos.splp.types.EncryptedMessage;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;

import java.util.HashMap;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("EncryptionService Tests")
class EncryptionServiceTest {

    private String encryptionKey;
    private String requestId;
    private TestPayload testPayload;

    @BeforeEach
    void setUp() {
        encryptionKey = EncryptionService.generateEncryptionKey();
        requestId = "TEST123456789ABC";
        testPayload = new TestPayload("John Doe", 30, "test@example.com");
    }

    @Test
    @DisplayName("Should generate valid encryption key")
    void shouldGenerateValidEncryptionKey() {
        String key = EncryptionService.generateEncryptionKey();
        
        assertNotNull(key, "Generated key should not be null");
        assertEquals(44, key.length(), "Generated key should be 44 characters (32 bytes base64 encoded)");
        assertTrue(key.matches("[A-Za-z0-9+/=]+"), "Generated key should be valid base64");
    }

    @Test
    @DisplayName("Should generate different keys each time")
    void shouldGenerateDifferentKeysEachTime() {
        String key1 = EncryptionService.generateEncryptionKey();
        String key2 = EncryptionService.generateEncryptionKey();
        
        assertNotEquals(key1, key2, "Generated keys should be different");
    }

    @Test
    @DisplayName("Should encrypt payload successfully")
    void shouldEncryptPayloadSuccessfully() {
        EncryptedMessage encrypted = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        
        assertNotNull(encrypted, "Encrypted message should not be null");
        assertEquals(requestId, encrypted.getRequestId(), "Request ID should match");
        assertNotNull(encrypted.getData(), "Encrypted data should not be null");
        assertNotNull(encrypted.getIv(), "IV should not be null");
        assertNotNull(encrypted.getTag(), "Tag should not be null");
        
        assertTrue(encrypted.getData().length() > 0, "Encrypted data should not be empty");
        assertEquals(24, encrypted.getIv().length(), "IV should be 24 characters (16 bytes base64)");
        assertEquals(24, encrypted.getTag().length(), "Tag should be 24 characters (16 bytes base64)");
    }

    @Test
    @DisplayName("Should decrypt payload successfully")
    void shouldDecryptPayloadSuccessfully() {
        EncryptedMessage encrypted = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
        
        assertNotNull(result, "Decryption result should not be null");
        assertTrue(result.isSuccess(), "Decryption should be successful");
        assertNotNull(result.getPayload(), "Decrypted payload should not be null");
        assertNull(result.getError(), "Error should be null for successful decryption");
        
        // Verify the decrypted data matches original
        Map<String, Object> decryptedData = (Map<String, Object>) result.getPayload();
        assertEquals(testPayload.getName(), decryptedData.get("name"));
        assertEquals(testPayload.getAge(), decryptedData.get("age"));
        assertEquals(testPayload.getEmail(), decryptedData.get("email"));
    }

    @Test
    @DisplayName("Should handle encryption with different payload types")
    void shouldHandleEncryptionWithDifferentPayloadTypes() {
        // Test with Map
        Map<String, Object> mapPayload = new HashMap<>();
        mapPayload.put("key1", "value1");
        mapPayload.put("key2", 42);
        
        EncryptedMessage encrypted = EncryptionService.encryptPayload(mapPayload, encryptionKey, requestId);
        assertNotNull(encrypted, "Should encrypt Map payload");
        
        // Test with String
        String stringPayload = "Simple string payload";
        encrypted = EncryptionService.encryptPayload(stringPayload, encryptionKey, requestId);
        assertNotNull(encrypted, "Should encrypt String payload");
        
        // Test with Number
        Integer numberPayload = 12345;
        encrypted = EncryptionService.encryptPayload(numberPayload, encryptionKey, requestId);
        assertNotNull(encrypted, "Should encrypt Number payload");
    }

    @Test
    @DisplayName("Should fail decryption with wrong key")
    void shouldFailDecryptionWithWrongKey() {
        EncryptedMessage encrypted = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        String wrongKey = EncryptionService.generateEncryptionKey();
        
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, wrongKey);
        
        assertNotNull(result, "Decryption result should not be null");
        assertFalse(result.isSuccess(), "Decryption should fail with wrong key");
        assertNull(result.getPayload(), "Payload should be null for failed decryption");
        assertNotNull(result.getError(), "Error should not be null for failed decryption");
    }

    @Test
    @DisplayName("Should fail decryption with corrupted data")
    void shouldFailDecryptionWithCorruptedData() {
        EncryptedMessage encrypted = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        
        // Corrupt the encrypted data
        EncryptedMessage corruptedMessage = new EncryptedMessage(
            encrypted.getRequestId(),
            "corrupted_data",
            encrypted.getIv(),
            encrypted.getTag()
        );
        
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(corruptedMessage, encryptionKey);
        
        assertNotNull(result, "Decryption result should not be null");
        assertFalse(result.isSuccess(), "Decryption should fail with corrupted data");
        assertNull(result.getPayload(), "Payload should be null for failed decryption");
        assertNotNull(result.getError(), "Error should not be null for failed decryption");
    }

    @Test
    @DisplayName("Should handle null inputs gracefully")
    void shouldHandleNullInputsGracefully() {
        assertThrows(EncryptionService.EncryptionException.class, () -> {
            EncryptionService.encryptPayload(null, encryptionKey, requestId);
        }, "Should throw exception for null payload");
        
        assertThrows(EncryptionService.EncryptionException.class, () -> {
            EncryptionService.encryptPayload(testPayload, null, requestId);
        }, "Should throw exception for null key");
        
        assertThrows(EncryptionService.EncryptionException.class, () -> {
            EncryptionService.encryptPayload(testPayload, encryptionKey, null);
        }, "Should throw exception for null request ID");
        
        assertThrows(EncryptionService.EncryptionException.class, () -> {
            EncryptionService.decryptPayload(null, encryptionKey);
        }, "Should throw exception for null encrypted message");
    }

    @ParameterizedTest
    @ValueSource(strings = {"", "short", "this_key_is_way_too_long_to_be_valid_for_aes_encryption"})
    @DisplayName("Should handle invalid key lengths")
    void shouldHandleInvalidKeyLengths(String invalidKey) {
        assertThrows(EncryptionService.EncryptionException.class, () -> {
            EncryptionService.encryptPayload(testPayload, invalidKey, requestId);
        }, "Should throw exception for invalid key length: " + invalidKey);
    }

    @Test
    @DisplayName("Should produce different encrypted data for same payload")
    void shouldProduceDifferentEncryptedDataForSamePayload() {
        EncryptedMessage encrypted1 = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        EncryptedMessage encrypted2 = EncryptionService.encryptPayload(testPayload, encryptionKey, requestId);
        
        // Data should be different due to random IV
        assertNotEquals(encrypted1.getData(), encrypted2.getData(), "Encrypted data should be different");
        assertNotEquals(encrypted1.getIv(), encrypted2.getIv(), "IVs should be different");
        
        // But both should decrypt to the same payload
        EncryptionService.DecryptionResult result1 = EncryptionService.decryptPayload(encrypted1, encryptionKey);
        EncryptionService.DecryptionResult result2 = EncryptionService.decryptPayload(encrypted2, encryptionKey);
        
        assertTrue(result1.isSuccess(), "First decryption should succeed");
        assertTrue(result2.isSuccess(), "Second decryption should succeed");
    }

    @Test
    @DisplayName("Should handle large payloads")
    void shouldHandleLargePayloads() {
        // Create a large payload
        StringBuilder largeString = new StringBuilder();
        for (int i = 0; i < 10000; i++) {
            largeString.append("This is a large payload for testing encryption performance. ");
        }
        
        String largePayload = largeString.toString();
        
        EncryptedMessage encrypted = EncryptionService.encryptPayload(largePayload, encryptionKey, requestId);
        assertNotNull(encrypted, "Should encrypt large payload");
        
        EncryptionService.DecryptionResult result = EncryptionService.decryptPayload(encrypted, encryptionKey);
        assertTrue(result.isSuccess(), "Should decrypt large payload successfully");
        assertEquals(largePayload, result.getPayload(), "Decrypted large payload should match original");
    }

    // Test payload class
    private static class TestPayload {
        private String name;
        private int age;
        private String email;

        public TestPayload() {}

        public TestPayload(String name, int age, String email) {
            this.name = name;
            this.age = age;
            this.email = email;
        }

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }

        public int getAge() { return age; }
        public void setAge(int age) { this.age = age; }

        public String getEmail() { return email; }
        public void setEmail(String email) { this.email = email; }
    }
}