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

        echo "🔓 Starting decryption process...\n";
        echo "  Data length: " . strlen($encryptedMessage->data) . " chars\n";
        echo "  IV length: " . strlen($encryptedMessage->iv) . " chars\n";
        echo "  Tag length: " . strlen($encryptedMessage->tag) . " chars\n";

        // Try to decode data - handle both base64 and hex formats
        $encrypted = $this->decodeData($encryptedMessage->data);
        $iv = $this->decodeData($encryptedMessage->iv);
        $tag = $this->decodeData($encryptedMessage->tag);

        if ($encrypted === false || $iv === false || $tag === false) {
            echo "❌ Failed to decode encrypted message components\n";
            echo "  Data decode: " . ($encrypted === false ? "FAILED" : "SUCCESS") . "\n";
            echo "  IV decode: " . ($iv === false ? "FAILED" : "SUCCESS") . "\n";
            echo "  Tag decode: " . ($tag === false ? "FAILED" : "SUCCESS") . "\n";
            throw new \Exception("Failed to decode encrypted message");
        }

        echo "✅ Successfully decoded all components\n";
        echo "  Decrypted data length: " . strlen($encrypted) . " bytes\n";
        echo "  Decoded IV length: " . strlen($iv) . " bytes\n";
        echo "  Decoded tag length: " . strlen($tag) . " bytes\n";

        // Validate and normalize IV length for AES-256-GCM (should be 12 bytes)
        $ivLength = strlen($iv);
        if ($ivLength !== 12) {
            if ($ivLength > 12) {
                $iv = substr($iv, 0, 12); // Truncate to 12 bytes
            } else {
                $iv = str_pad($iv, 12, "\0", STR_PAD_RIGHT); // Pad to 12 bytes
            }
        }

        // Validate and normalize tag length for AES-256-GCM (should be 16 bytes)
        $tagLength = strlen($tag);
        if ($tagLength !== 16) {
            if ($tagLength > 16) {
                $tag = substr($tag, 0, 16); // Truncate to 16 bytes
            } else {
                $tag = str_pad($tag, 16, "\0", STR_PAD_RIGHT); // Pad to 16 bytes
            }
        }

        // Validate key
        $keyBinary = hex2bin($this->key);
        if ($keyBinary === false) {
            throw new \Exception("Invalid encryption key format");
        }

        // Clear any previous OpenSSL errors
        while (openssl_error_string() !== false) {
            // Clear all errors
        }

        echo "🔐 Attempting decryption with AES-256-GCM...\n";
        echo "  Key length: " . strlen($keyBinary) . " bytes\n";
        echo "  IV length: " . strlen($iv) . " bytes\n";
        echo "  Tag length: " . strlen($tag) . " bytes\n";
        echo "  Encrypted data length: " . strlen($encrypted) . " bytes\n";

        // Decrypt data
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $keyBinary,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            // Get detailed error information
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            
            $errorMessage = "Decryption failed";
            if (!empty($errors)) {
                $errorMessage .= ": " . implode("; ", $errors);
            } else {
                $errorMessage .= ": Invalid key, corrupted data, or authentication failure";
            }
            
            // Additional debugging info
            $errorMessage .= " (Data: " . strlen($encrypted) . " bytes, IV: " . strlen($iv) . " bytes, Tag: " . strlen($tag) . " bytes)";
            
            echo "❌ " . $errorMessage . "\n";
            throw new \Exception($errorMessage);
        }

        echo "✅ Decryption successful!\n";
        echo "  Decrypted data length: " . strlen($decrypted) . " bytes\n";

        // Parse JSON
        $data = json_decode($decrypted, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ Failed to parse decrypted JSON: " . json_last_error_msg() . "\n";
            echo "Raw decrypted data: " . substr($decrypted, 0, 200) . "...\n";
            throw new \Exception("Failed to parse decrypted JSON: " . json_last_error_msg());
        }

        echo "✅ JSON parsing successful!\n";
        echo "  Parsed data keys: " . implode(', ', array_keys($data)) . "\n";

        // Extract request ID from data if available
        $requestId = $data['requestId'] ?? 'unknown';
        echo "  Request ID: {$requestId}\n";

        return [$requestId, $data];
    }

    /**
     * Decode data from either base64 or hex format
     * @param string $data The encoded data
     * @return string|false Decoded binary data or false on failure
     */
    private function decodeData(string $data): string|false
    {
        echo "    🔍 Decoding data: " . substr($data, 0, 20) . "... (length: " . strlen($data) . ")\n";
        
        // Try hex format first (Node.js/Bun format) - most common
        if (ctype_xdigit($data) && strlen($data) % 2 === 0) {
            echo "    🔍 Attempting hex decode...\n";
            $hexDecoded = hex2bin($data);
            if ($hexDecoded !== false) {
                echo "    ✅ Hex decode successful! (length: " . strlen($hexDecoded) . " bytes)\n";
                return $hexDecoded;
            } else {
                echo "    ❌ Hex decode failed\n";
            }
        } else {
            echo "    ⚠️  Data is not valid hex format\n";
        }

        // Try base64 format (PHP format)
        echo "    🔍 Attempting base64 decode...\n";
        $decoded = base64_decode($data, true);
        if ($decoded !== false && strlen($decoded) > 0) {
            echo "    ✅ Base64 decode successful! (length: " . strlen($decoded) . " bytes)\n";
            return $decoded;
        } else {
            echo "    ❌ Base64 decode failed\n";
        }

        echo "    ❌ All decode attempts failed\n";
        return false;
    }
}
