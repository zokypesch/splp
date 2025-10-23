<?php

namespace Splp\Messaging\Utils;

class RequestIdGenerator
{
    public function generate(): string
    {
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
}
