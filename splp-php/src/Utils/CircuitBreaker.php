<?php

namespace Splp\Messaging\Utils;

class CircuitBreaker
{
    private int $failureThreshold = 5;
    private int $timeout = 60; // seconds
    private int $failureCount = 0;
    private int $lastFailureTime = 0;
    private string $state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN

    public function initialize(): void
    {
        $this->state = 'CLOSED';
        $this->failureCount = 0;
        $this->lastFailureTime = 0;
    }

    public function isAvailable(): bool
    {
        switch ($this->state) {
            case 'CLOSED':
                return true;
            case 'OPEN':
                if (time() - $this->lastFailureTime > $this->timeout) {
                    $this->state = 'HALF_OPEN';
                    return true;
                }
                return false;
            case 'HALF_OPEN':
                return true;
            default:
                return false;
        }
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = 'CLOSED';
    }

    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'OPEN';
        }
    }

    public function getStatus(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'last_failure_time' => $this->lastFailureTime,
            'threshold' => $this->failureThreshold,
            'timeout' => $this->timeout
        ];
    }
}
