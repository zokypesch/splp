package com.perlinsos.splp.crypto;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.datatype.jsr310.JavaTimeModule;
import com.perlinsos.splp.types.EncryptedMessage;

import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.HexFormat;

/**
 * Service for encrypting and decrypting payloads using AES-256-GCM
 */
public class EncryptionService {
    private static final String ALGORITHM = "AES";
    private static final String TRANSFORMATION = "AES/GCM/NoPadding";
    private static final int IV_LENGTH = 12; // 96 bits for GCM
    private static final int TAG_LENGTH = 16; // 128 bits
    private static final int KEY_LENGTH = 32; // 256 bits
    
    private static final ObjectMapper OBJECT_MAPPER = new ObjectMapper()
            .registerModule(new JavaTimeModule());
    
    private static final SecureRandom SECURE_RANDOM = new SecureRandom();

    /**
     * Encrypt a payload using AES-256-GCM
     * @param payload The data to encrypt
     * @param encryptionKey 32-byte hex string for AES-256
     * @param requestId The request ID (not encrypted, used for correlation)
     * @param <T> Type of the payload
     * @return Encrypted message with IV and auth tag
     * @throws EncryptionException if encryption fails
     */
    public static <T> EncryptedMessage encryptPayload(T payload, String encryptionKey, String requestId) 
            throws EncryptionException {
        try {
            // Validate key
            byte[] keyBytes = HexFormat.of().parseHex(encryptionKey);
            if (keyBytes.length != KEY_LENGTH) {
                throw new EncryptionException("Encryption key must be 32 bytes (64 hex characters) for AES-256");
            }

            // Generate random IV
            byte[] iv = new byte[IV_LENGTH];
            SECURE_RANDOM.nextBytes(iv);

            // Convert payload to JSON string
            String payloadString = OBJECT_MAPPER.writeValueAsString(payload);
            byte[] payloadBytes = payloadString.getBytes(StandardCharsets.UTF_8);

            // Create cipher
            SecretKey secretKey = new SecretKeySpec(keyBytes, ALGORITHM);
            Cipher cipher = Cipher.getInstance(TRANSFORMATION);
            GCMParameterSpec gcmSpec = new GCMParameterSpec(TAG_LENGTH * 8, iv);
            cipher.init(Cipher.ENCRYPT_MODE, secretKey, gcmSpec);

            // Encrypt the data
            byte[] encryptedData = cipher.doFinal(payloadBytes);

            // Extract encrypted data and tag
            int encryptedLength = encryptedData.length - TAG_LENGTH;
            byte[] encrypted = new byte[encryptedLength];
            byte[] tag = new byte[TAG_LENGTH];
            
            System.arraycopy(encryptedData, 0, encrypted, 0, encryptedLength);
            System.arraycopy(encryptedData, encryptedLength, tag, 0, TAG_LENGTH);

            return new EncryptedMessage(
                requestId,
                HexFormat.of().formatHex(encrypted),
                HexFormat.of().formatHex(iv),
                HexFormat.of().formatHex(tag)
            );

        } catch (Exception e) {
            throw new EncryptionException("Failed to encrypt payload", e);
        }
    }

    /**
     * Decrypt an encrypted message using AES-256-GCM
     * @param encryptedMessage The encrypted message
     * @param encryptionKey 32-byte hex string for AES-256
     * @param payloadClass The class type to deserialize to
     * @param <T> Type of the payload
     * @return Decrypted payload with request ID
     * @throws EncryptionException if decryption fails
     */
    public static <T> DecryptionResult<T> decryptPayload(EncryptedMessage encryptedMessage, 
                                                        String encryptionKey, 
                                                        Class<T> payloadClass) 
            throws EncryptionException {
        try {
            // Validate key
            byte[] keyBytes = HexFormat.of().parseHex(encryptionKey);
            if (keyBytes.length != KEY_LENGTH) {
                throw new EncryptionException("Encryption key must be 32 bytes (64 hex characters) for AES-256");
            }

            // Parse hex values
            byte[] iv = HexFormat.of().parseHex(encryptedMessage.getIv());
            byte[] tag = HexFormat.of().parseHex(encryptedMessage.getTag());
            byte[] encrypted = HexFormat.of().parseHex(encryptedMessage.getData());

            // Combine encrypted data and tag for GCM
            byte[] encryptedWithTag = new byte[encrypted.length + tag.length];
            System.arraycopy(encrypted, 0, encryptedWithTag, 0, encrypted.length);
            System.arraycopy(tag, 0, encryptedWithTag, encrypted.length, tag.length);

            // Create cipher
            SecretKey secretKey = new SecretKeySpec(keyBytes, ALGORITHM);
            Cipher cipher = Cipher.getInstance(TRANSFORMATION);
            GCMParameterSpec gcmSpec = new GCMParameterSpec(TAG_LENGTH * 8, iv);
            cipher.init(Cipher.DECRYPT_MODE, secretKey, gcmSpec);

            // Decrypt the data
            byte[] decryptedBytes = cipher.doFinal(encryptedWithTag);
            String decryptedString = new String(decryptedBytes, StandardCharsets.UTF_8);

            // Parse JSON payload
            T payload = OBJECT_MAPPER.readValue(decryptedString, payloadClass);

            return new DecryptionResult<>(encryptedMessage.getRequestId(), payload);

        } catch (Exception e) {
            throw new EncryptionException("Failed to decrypt payload", e);
        }
    }

    /**
     * Generate a new encryption key for AES-256
     * @return 64-character hex string representing a 32-byte key
     * @throws EncryptionException if key generation fails
     */
    public static String generateEncryptionKey() throws EncryptionException {
        try {
            KeyGenerator keyGenerator = KeyGenerator.getInstance(ALGORITHM);
            keyGenerator.init(256); // 256-bit key
            SecretKey secretKey = keyGenerator.generateKey();
            return HexFormat.of().formatHex(secretKey.getEncoded());
        } catch (NoSuchAlgorithmException e) {
            throw new EncryptionException("Failed to generate encryption key", e);
        }
    }

    /**
     * Result of decryption operation
     * @param <T> Type of the decrypted payload
     */
    public static class DecryptionResult<T> {
        private final String requestId;
        private final T payload;

        public DecryptionResult(String requestId, T payload) {
            this.requestId = requestId;
            this.payload = payload;
        }

        public String getRequestId() {
            return requestId;
        }

        public T getPayload() {
            return payload;
        }
    }

    /**
     * Exception thrown when encryption/decryption operations fail
     */
    public static class EncryptionException extends Exception {
        public EncryptionException(String message) {
            super(message);
        }

        public EncryptionException(String message, Throwable cause) {
            super(message, cause);
        }
    }
}