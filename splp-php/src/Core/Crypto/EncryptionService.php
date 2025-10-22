<?php

declare(strict_types=1);

namespace Splp\Messaging\Core\Crypto;

use Splp\Messaging\Exceptions\EncryptionException;

/**
 * Encryption Service
 * 
 * Provides AES-256-GCM encryption/decryption for message payloads
 * Compatible with the TypeScript/Bun implementation
 */
class EncryptionService
{
    private const ALGORITHM = 'aes-256-gcm';
    private const IV_LENGTH = 16;
    private const TAG_LENGTH = 16;

    private string $encryptionKey;

    public function __construct(array $config)
    {
        $this->encryptionKey = $config['encryptionKey'];
        $this->validateKey();
    }

    /**
     * Encrypts a payload using AES-256-GCM
     * 
     * @param mixed $payload The data to encrypt
     * @param string $requestId The request ID (not encrypted, used for correlation)
     * @return array Encrypted message with IV and auth tag
     */
    public function encryptPayload(mixed $payload, string $requestId): array
    {
        try {
            // Convert hex key to binary
            $key = hex2bin($this->encryptionKey);
            if ($key === false) {
                throw new EncryptionException('Invalid encryption key format');
            }

            // Generate random IV
            $iv = random_bytes(self::IV_LENGTH);

            // Convert payload to JSON string
            $payloadString = json_encode($payload);
            if ($payloadString === false) {
                throw new EncryptionException('Failed to encode payload to JSON');
            }

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $payloadString,
                self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($encrypted === false) {
                throw new EncryptionException('Encryption failed');
            }

            return [
                'request_id' => $requestId, // Not encrypted for tracing
                'data' => bin2hex($encrypted),
                'iv' => bin2hex($iv),
                'tag' => bin2hex($tag),
            ];
        } catch (\Exception $e) {
            throw new EncryptionException('Encryption error: ' . $e->getMessage());
        }
    }

    /**
     * Decrypts an encrypted message using AES-256-GCM
     * 
     * @param array $encryptedMessage The encrypted message
     * @return array Decrypted payload with request ID
     */
    public function decryptPayload(array $encryptedMessage): array
    {
        try {
            // Convert hex key to binary
            $key = hex2bin($this->encryptionKey);
            if ($key === false) {
                throw new EncryptionException('Invalid encryption key format');
            }

            // Convert IV and tag from hex
            $iv = hex2bin($encryptedMessage['iv']);
            $tag = hex2bin($encryptedMessage['tag']);
            $encryptedData = hex2bin($encryptedMessage['data']);

            if ($iv === false || $tag === false || $encryptedData === false) {
                throw new EncryptionException('Invalid encrypted message format');
            }

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encryptedData,
                self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decrypted === false) {
                throw new EncryptionException('Decryption failed');
            }

            // Parse JSON payload
            $payload = json_decode($decrypted, true);
            if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new EncryptionException('Failed to decode decrypted payload: ' . json_last_error_msg());
            }

            return [
                'requestId' => $encryptedMessage['request_id'],
                'payload' => $payload,
            ];
        } catch (\Exception $e) {
            throw new EncryptionException('Decryption error: ' . $e->getMessage());
        }
    }

    /**
     * Generates a random encryption key for AES-256
     * 
     * @return string 32-byte hex string
     */
    public static function generateEncryptionKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validates encryption key format
     */
    private function validateKey(): void
    {
        if (strlen($this->encryptionKey) !== 64) {
            throw new EncryptionException('Encryption key must be 32 bytes (64 hex characters) for AES-256');
        }

        if (!ctype_xdigit($this->encryptionKey)) {
            throw new EncryptionException('Encryption key must contain only hexadecimal characters');
        }
    }
}
