package com.perlinsos.splp.utils;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.RepeatedTest;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;

import java.util.HashSet;
import java.util.Set;

import static org.junit.jupiter.api.Assertions.*;

@DisplayName("RequestIdGenerator Tests")
class RequestIdGeneratorTest {

    @Test
    @DisplayName("Should generate request ID with correct length")
    void shouldGenerateRequestIdWithCorrectLength() {
        String requestId = RequestIdGenerator.generate();
        assertEquals(16, requestId.length(), "Request ID should be 16 characters long");
    }

    @Test
    @DisplayName("Should generate alphanumeric request ID")
    void shouldGenerateAlphanumericRequestId() {
        String requestId = RequestIdGenerator.generate();
        assertTrue(requestId.matches("[A-Za-z0-9]+"), "Request ID should contain only alphanumeric characters");
    }

    @RepeatedTest(100)
    @DisplayName("Should generate unique request IDs")
    void shouldGenerateUniqueRequestIds() {
        Set<String> generatedIds = new HashSet<>();
        
        for (int i = 0; i < 1000; i++) {
            String requestId = RequestIdGenerator.generate();
            assertFalse(generatedIds.contains(requestId), "Generated request ID should be unique");
            generatedIds.add(requestId);
        }
    }

    @Test
    @DisplayName("Should validate correct request ID format")
    void shouldValidateCorrectRequestIdFormat() {
        String validRequestId = "ABC123DEF456GH78";
        assertTrue(RequestIdGenerator.isValidRequestId(validRequestId), "Valid request ID should pass validation");
    }

    @ParameterizedTest
    @ValueSource(strings = {
        "ABC123DEF456GH7", // 15 characters
        "ABC123DEF456GH789", // 17 characters
        "ABC123DEF456GH7!", // contains special character
        "ABC123DEF456 H78", // contains space
        "", // empty string
        "abc123def456gh78" // lowercase (should still be valid)
    })
    @DisplayName("Should validate request ID format correctly")
    void shouldValidateRequestIdFormat(String requestId) {
        boolean isValid = RequestIdGenerator.isValidRequestId(requestId);
        
        if (requestId.length() == 16 && requestId.matches("[A-Za-z0-9]+")) {
            assertTrue(isValid, "Valid 16-character alphanumeric string should pass validation");
        } else {
            assertFalse(isValid, "Invalid request ID should fail validation: " + requestId);
        }
    }

    @Test
    @DisplayName("Should handle null input in validation")
    void shouldHandleNullInputInValidation() {
        assertFalse(RequestIdGenerator.isValidRequestId(null), "Null input should fail validation");
    }

    @Test
    @DisplayName("Should generate request ID with prefix")
    void shouldGenerateRequestIdWithPrefix() {
        String prefix = "REQ";
        String requestId = RequestIdGenerator.generateWithPrefix(prefix);
        
        assertTrue(requestId.startsWith(prefix), "Request ID should start with the specified prefix");
        assertEquals(16, requestId.length(), "Request ID should still be 16 characters long");
        assertTrue(requestId.matches("[A-Za-z0-9]+"), "Request ID with prefix should be alphanumeric");
    }

    @Test
    @DisplayName("Should handle empty prefix")
    void shouldHandleEmptyPrefix() {
        String requestId = RequestIdGenerator.generateWithPrefix("");
        assertEquals(16, requestId.length(), "Request ID with empty prefix should be 16 characters long");
        assertTrue(requestId.matches("[A-Za-z0-9]+"), "Request ID with empty prefix should be alphanumeric");
    }

    @Test
    @DisplayName("Should handle null prefix")
    void shouldHandleNullPrefix() {
        String requestId = RequestIdGenerator.generateWithPrefix(null);
        assertEquals(16, requestId.length(), "Request ID with null prefix should be 16 characters long");
        assertTrue(requestId.matches("[A-Za-z0-9]+"), "Request ID with null prefix should be alphanumeric");
    }

    @Test
    @DisplayName("Should handle long prefix by truncating")
    void shouldHandleLongPrefixByTruncating() {
        String longPrefix = "VERYLONGPREFIXTHATEXCEEDS16CHARS";
        String requestId = RequestIdGenerator.generateWithPrefix(longPrefix);
        
        assertEquals(16, requestId.length(), "Request ID should be exactly 16 characters even with long prefix");
        assertTrue(requestId.startsWith(longPrefix.substring(0, Math.min(longPrefix.length(), 16))), 
                  "Request ID should start with truncated prefix");
    }

    @Test
    @DisplayName("Should generate different IDs with same prefix")
    void shouldGenerateDifferentIdsWithSamePrefix() {
        String prefix = "TEST";
        Set<String> generatedIds = new HashSet<>();
        
        for (int i = 0; i < 100; i++) {
            String requestId = RequestIdGenerator.generateWithPrefix(prefix);
            assertFalse(generatedIds.contains(requestId), "Request IDs with same prefix should be unique");
            generatedIds.add(requestId);
        }
    }

    @Test
    @DisplayName("Should maintain randomness in suffix when using prefix")
    void shouldMaintainRandomnessInSuffixWhenUsingPrefix() {
        String prefix = "ABC";
        String requestId1 = RequestIdGenerator.generateWithPrefix(prefix);
        String requestId2 = RequestIdGenerator.generateWithPrefix(prefix);
        
        assertNotEquals(requestId1, requestId2, "Request IDs with same prefix should have different suffixes");
        assertTrue(requestId1.startsWith(prefix), "First request ID should start with prefix");
        assertTrue(requestId2.startsWith(prefix), "Second request ID should start with prefix");
    }
}