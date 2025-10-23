<?php

namespace Splp\Messaging\Contracts;

use Splp\Messaging\Types\EncryptedMessage;

interface MessageProcessorInterface
{
    public function processMessage(EncryptedMessage $message): void;
}
