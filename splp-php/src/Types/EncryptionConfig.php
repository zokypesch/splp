<?php

namespace Splp\Messaging\Types;

class EncryptionConfig
{
    public string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }
}
