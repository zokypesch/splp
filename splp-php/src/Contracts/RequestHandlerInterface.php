<?php

declare(strict_types=1);

namespace Splp\Messaging\Contracts;

/**
 * Request Handler Interface
 */
interface RequestHandlerInterface
{
    /**
     * Handle a request
     *
     * @param string $requestId The request ID
     * @param mixed $payload The request payload
     * @return mixed The response
     */
    public function handle(string $requestId, mixed $payload): mixed;
}
