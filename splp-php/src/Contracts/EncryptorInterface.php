<?php

namespace Splp\Messaging\Contracts;

use Splp\Messaging\Types\EncryptedMessage;

interface EncryptorInterface
{
    public function initialize(): void;
    public function encrypt(mixed $data, string $requestId): EncryptedMessage;
    public function decrypt(EncryptedMessage $encryptedMessage): array; // Returns [requestId, decryptedData]
}
