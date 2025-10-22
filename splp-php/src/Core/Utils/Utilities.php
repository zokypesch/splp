<?php

declare(strict_types=1);

namespace Splp\Messaging\Core\Utils;

use Splp\Messaging\Exceptions\MessagingException;

/**
 * Request ID Generator
 * 
 * Generates UUID v4 request IDs for distributed tracing
 * Compatible with the TypeScript/Bun implementation
 */
class RequestIdGenerator
{
    /**
     * Generate a new UUID v4 request ID
     */
    public static function generate(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    /**
     * Validate if a string is a valid UUID v4
     */
    public static function isValid(string $requestId): bool
    {
        try {
            $uuid = \Ramsey\Uuid\Uuid::fromString($requestId);
            return $uuid->getVersion() === 4;
        } catch (\Exception $e) {
            return false;
        }
    }
}

/**
 * Circuit Breaker Pattern Implementation
 * 
 * Provides fault tolerance and prevents cascading failures
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'CLOSED';
    private const STATE_OPEN = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $lastFailureTime = 0;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'failureThreshold' => 5,
            'recoveryTimeout' => 30000,
            'monitoringPeriod' => 60000
        ], $config);
    }

    /**
     * Execute function with circuit breaker protection
     */
    public function execute(callable $fn): mixed
    {
        if ($this->state === self::STATE_OPEN) {
            if ((microtime(true) * 1000) - $this->lastFailureTime > $this->config['recoveryTimeout']) {
                $this->state = self::STATE_HALF_OPEN;
            } else {
                throw new MessagingException('Circuit breaker is OPEN - service unavailable');
            }
        }

        try {
            $result = $fn();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = self::STATE_CLOSED;
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true) * 1000;

        if ($this->failureCount >= $this->config['failureThreshold']) {
            $this->state = self::STATE_OPEN;
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }
}

/**
 * Retry Manager with Exponential Backoff
 * 
 * Provides automatic retry for transient failures
 */
class RetryManager
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'maxAttempts' => 3,
            'baseDelay' => 1000,
            'maxDelay' => 10000,
            'backoffMultiplier' => 2,
            'jitter' => true
        ], $config);
    }

    /**
     * Execute function with retry logic
     */
    public function execute(callable $fn, ?callable $isRetryableError = null): mixed
    {
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $this->config['maxAttempts']; $attempt++) {
            try {
                return $fn();
            } catch (\Exception $e) {
                $lastError = $e;
                
                // Check if error is retryable
                if ($isRetryableError && !$isRetryableError($e)) {
                    throw $e;
                }
                
                // Don't retry on last attempt
                if ($attempt === $this->config['maxAttempts']) {
                    throw $e;
                }
                
                // Calculate delay with exponential backoff
                $delay = $this->calculateDelay($attempt);
                error_log("Attempt {$attempt} failed, retrying in {$delay}ms...");
                
                usleep($delay * 1000); // Convert to microseconds
            }
        }
        
        throw $lastError;
    }

    private function calculateDelay(int $attempt): int
    {
        $exponentialDelay = $this->config['baseDelay'] * 
            pow($this->config['backoffMultiplier'], $attempt - 1);
        
        $cappedDelay = min($exponentialDelay, $this->config['maxDelay']);
        
        if ($this->config['jitter']) {
            // Add random jitter (±25%)
            $jitterRange = $cappedDelay * 0.25;
            $jitter = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $jitterRange;
            return max(0, (int)($cappedDelay + $jitter));
        }
        
        return (int)$cappedDelay;
    }
}
