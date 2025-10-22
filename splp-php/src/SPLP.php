<?php

declare(strict_types=1);

namespace Splp\Messaging;

/**
 * Main SPLP Messaging Library Entry Point
 * 
 * This library provides a complete ecosystem for implementing Request-Reply patterns
 * over Kafka with Command Center routing, encryption, and distributed tracing.
 * 
 * @package Splp\Messaging
 * @version 1.0.0
 * @license MIT
 */
class SPLP
{
    /**
     * Library version
     */
    public const VERSION = '1.0.0';

    /**
     * Get library version
     */
    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * Get library information
     */
    public static function info(): array
    {
        return [
            'name' => 'SPLP PHP Messaging',
            'version' => self::VERSION,
            'description' => 'Kafka Request-Reply Messaging Ecosystem for PHP',
            'features' => [
                'Kafka Request-Reply Pattern',
                'AES-256-GCM Encryption',
                'Command Center Routing',
                'Cassandra Logging & Tracing',
                'Circuit Breaker Pattern',
                'Retry Mechanism',
                'Health Checks',
                'Laravel Integration',
                'CodeIgniter Integration'
            ]
        ];
    }
}
