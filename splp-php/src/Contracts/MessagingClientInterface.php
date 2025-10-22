<?php

declare(strict_types=1);

namespace Splp\Messaging\Contracts;

use Splp\Messaging\Contracts\RequestHandlerInterface;

/**
 * Messaging Client Interface
 */
interface MessagingClientInterface
{
    /**
     * Initialize the messaging client
     */
    public function initialize(): void;

    /**
     * Register a handler for a topic
     *
     * @param string $topic The topic name
     * @param RequestHandlerInterface $handler The handler
     */
    public function registerHandler(string $topic, RequestHandlerInterface $handler): void;

    /**
     * Send a request to a topic
     *
     * @param string $topic The topic name
     * @param array $payload The request payload
     * @return array The response
     */
    public function request(string $topic, array $payload): array;

    /**
     * Start consuming messages from topics
     *
     * @param array $topics Array of topic names
     */
    public function startConsuming(array $topics): void;

    /**
     * Get configuration
     *
     * @return array The configuration
     */
    public function getConfig(): array;
}
