<?php

namespace Splp\Messaging\Core;

use Splp\Messaging\Contracts\EncryptorInterface;
use Splp\Messaging\Types\EncryptedMessage;

class EncryptionService implements EncryptorInterface
{
    private string $key;
    private bool $initialized = false;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Validate key length (should be 32 bytes for AES-256)
        if (strlen($this->key) !== 64) { // 64 hex chars = 32 bytes
            // If key is not 64 chars, pad or truncate it
            if (strlen($this->key) < 64) {
                $this->key = str_pad($this->key, 64, '0', STR_PAD_RIGHT);
            } else {
                $this->key = substr($this->key, 0, 64);
            }
        }

        $this->initialized = true;
        echo "🔐 Encryption Service initialized\n";
    }

    public function encrypt(mixed $data, string $requestId): EncryptedMessage
    {
        if (!$this->initialized) {
            throw new \Exception("Encryption Service not initialized");
        }

        // Add request ID to data if it's an array
        if (is_array($data)) {
            $data['requestId'] = $requestId;
        }

        // Convert data to JSON
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            throw new \Exception("Failed to encode data to JSON");
        }

        // Generate random IV
        $iv = random_bytes(12); // 12 bytes for GCM

        // Encrypt data
        $encrypted = openssl_encrypt(
            $jsonData,
            'aes-256-gcm',
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new \Exception("Encryption failed");
        }

        return new EncryptedMessage(
            base64_encode($encrypted),
            base64_encode($iv),
            base64_encode($tag)
        );
    }

    public function decrypt(EncryptedMessage $encryptedMessage): array
    {
        if (!$this->initialized) {
            throw new \Exception("Encryption Service not initialized");
        }

        // Decode base64
        $encrypted = base64_decode($encryptedMessage->data);
        $iv = base64_decode($encryptedMessage->iv);
        $tag = base64_decode($encryptedMessage->tag);

        if ($encrypted === false || $iv === false || $tag === false) {
            throw new \Exception("Failed to decode encrypted message");
        }

        // Debug IV length
        $ivLength = strlen($iv);
        
        // Normalize IV length for AES-256-GCM (should be 12 bytes)
        if ($ivLength !== 12) {
            if ($ivLength > 12) {
                $iv = substr($iv, 0, 12); // Truncate to 12 bytes
            } else {
                $iv = str_pad($iv, 12, "\0", STR_PAD_RIGHT); // Pad to 12 bytes
            }
        }

        // Decrypt data
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            hex2bin($this->key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception("Decryption failed");
        }

        // Parse JSON
        $data = json_decode($decrypted, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse decrypted JSON: " . json_last_error_msg());
        }

        // Extract request ID from data if available
        $requestId = $data['requestId'] ?? 'unknown';

        return [$requestId, $data];
    }
}
