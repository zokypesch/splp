<?php

declare(strict_types=1);

namespace Splp\Messaging\Contracts;

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
     * Register a handler for a specific topic
     */
    public function registerHandler(string $topic, RequestHandlerInterface $handler): void;

    /**
     * Start consuming messages and processing with registered handlers
     */
    public function startConsuming(array $topics): void;

    /**
     * Send a request with automatic encryption and wait for reply
     */
    public function request(string $topic, mixed $payload, int $timeoutMs = 30000): mixed;

    /**
     * Get health status
     */
    public function getHealthStatus(): array;

    /**
     * Close all connections
     */
    public function close(): void;
}

/**
 * Request Handler Interface
 */
interface RequestHandlerInterface
{
    /**
     * Handle incoming request
     */
    public function handle(string $requestId, mixed $payload): mixed;
}

/**
 * Command Center Interface
 */
interface CommandCenterInterface
{
    /**
     * Initialize Command Center
     */
    public function initialize(): void;

    /**
     * Start listening for messages and route them
     */
    public function start(): void;

    /**
     * Register a route
     */
    public function registerRoute(array $routeConfig): void;

    /**
     * Get schema registry
     */
    public function getSchemaRegistry(): SchemaRegistryInterface;

    /**
     * Get metadata logger
     */
    public function getMetadataLogger(): MetadataLoggerInterface;

    /**
     * Shutdown Command Center
     */
    public function shutdown(): void;
}

/**
 * Schema Registry Interface
 */
interface SchemaRegistryInterface
{
    /**
     * Register a route
     */
    public function registerRoute(array $routeConfig): void;

    /**
     * Get route by publisher name
     */
    public function getRoute(string $publisherName): ?array;

    /**
     * Get all routes
     */
    public function getAllRoutes(): array;

    /**
     * Update route status
     */
    public function updateRouteStatus(string $publisherName, bool $enabled): void;

    /**
     * Delete route
     */
    public function deleteRoute(string $publisherName): void;
}

/**
 * Metadata Logger Interface
 */
interface MetadataLoggerInterface
{
    /**
     * Log routing metadata
     */
    public function log(array $metadata): void;

    /**
     * Get metadata by request ID
     */
    public function getByRequestId(string $requestId): array;

    /**
     * Get metadata by worker name
     */
    public function getByWorkerName(string $workerName, int $limit = 100): array;

    /**
     * Get metadata by time range
     */
    public function getByTimeRange(\DateTime $startTime, \DateTime $endTime): array;

    /**
     * Get route statistics
     */
    public function getRouteStats(string $routeId): array;
}
