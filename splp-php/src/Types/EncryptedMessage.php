<?php

namespace Splp\Messaging\Types;

class EncryptedMessage
{
    public string $data;
    public string $iv;
    public string $tag;

    public function __construct(string $data, string $iv, string $tag)
    {
        $this->data = $data;
        $this->iv = $iv;
        $this->tag = $tag;
    }
}
