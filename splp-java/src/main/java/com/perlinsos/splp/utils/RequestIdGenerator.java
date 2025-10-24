package com.perlinsos.splp.utils;

import java.security.SecureRandom;
import java.util.regex.Pattern;

/**
 * Utility class for generating and validating request IDs
 */
public class RequestIdGenerator {
    private static final SecureRandom RANDOM = new SecureRandom();
    private static final String CHARACTERS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    private static final int REQUEST_ID_LENGTH = 16;
    private static final Pattern VALID_REQUEST_ID_PATTERN = Pattern.compile("^[A-Za-z0-9]{16}$");

    /**
     * Generate a new random request ID (alias for generateRequestId)
     * @return A 16-character alphanumeric request ID
     */
    public static String generate() {
        return generateRequestId();
    }

    /**
     * Generate a new random request ID
     * @return A 16-character alphanumeric request ID
     */
    public static String generateRequestId() {
        StringBuilder sb = new StringBuilder(REQUEST_ID_LENGTH);
        for (int i = 0; i < REQUEST_ID_LENGTH; i++) {
            int index = RANDOM.nextInt(CHARACTERS.length());
            sb.append(CHARACTERS.charAt(index));
        }
        return sb.toString();
    }

    /**
     * Validate if a string is a valid request ID
     * @param requestId The string to validate
     * @return true if valid, false otherwise
     */
    public static boolean isValidRequestId(String requestId) {
        if (requestId == null) {
            return false;
        }
        return VALID_REQUEST_ID_PATTERN.matcher(requestId).matches();
    }

    /**
     * Generate a request ID with a prefix for easier identification
     * @param prefix The prefix to add (will be truncated if too long)
     * @return A request ID starting with the prefix
     */
    public static String generateRequestIdWithPrefix(String prefix) {
        if (prefix == null || prefix.isEmpty()) {
            return generateRequestId();
        }
        
        // Ensure prefix doesn't exceed the total length
        String safePrefix = prefix.length() > REQUEST_ID_LENGTH - 4 ? 
                           prefix.substring(0, REQUEST_ID_LENGTH - 4) : prefix;
        
        int remainingLength = REQUEST_ID_LENGTH - safePrefix.length();
        StringBuilder sb = new StringBuilder(safePrefix);
        
        for (int i = 0; i < remainingLength; i++) {
            int index = RANDOM.nextInt(CHARACTERS.length());
            sb.append(CHARACTERS.charAt(index));
        }
        
        return sb.toString();
    }
}