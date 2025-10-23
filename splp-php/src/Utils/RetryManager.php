<?php

namespace Splp\Messaging\Utils;

class RetryManager
{
    private int $maxRetries = 3;
    private int $baseDelay = 1000; // milliseconds

    public function execute(callable $operation): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->maxRetries) {
                    $delay = $this->baseDelay * pow(2, $attempt - 1); // Exponential backoff
                    usleep($delay * 1000); // Convert to microseconds
                    echo "⚠️  Retry attempt {$attempt}/{$this->maxRetries} after {$delay}ms delay\n";
                }
            }
        }

        throw $lastException ?? new \Exception("Operation failed after {$this->maxRetries} attempts");
    }
}
