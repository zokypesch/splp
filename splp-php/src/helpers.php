<?php

declare(strict_types=1);

namespace Splp\Messaging;

/**
 * Helper Functions
 * 
 * Global helper functions for SPLP Messaging
 */

use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Core\Utils\RequestIdGenerator;

/**
 * Generate a new request ID
 */
function generateRequestId(): string
{
    return RequestIdGenerator::generate();
}

/**
 * Validate if a string is a valid request ID
 */
function isValidRequestId(string $requestId): bool
{
    return RequestIdGenerator::isValid($requestId);
}

/**
 * Generate a new encryption key
 */
function generateEncryptionKey(): string
{
    return EncryptionService::generateEncryptionKey();
}

/**
 * Encrypt payload
 */
function encryptPayload(mixed $payload, string $encryptionKey, string $requestId): array
{
    $encryption = new EncryptionService(['encryptionKey' => $encryptionKey]);
    return $encryption->encryptPayload($payload, $requestId);
}

/**
 * Decrypt payload
 */
function decryptPayload(array $encryptedMessage, string $encryptionKey): array
{
    $encryption = new EncryptionService(['encryptionKey' => $encryptionKey]);
    return $encryption->decryptPayload($encryptedMessage);
}
