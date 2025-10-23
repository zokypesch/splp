<?php

namespace Splp\Messaging\Utils;

class SignalHandler
{
    private array $signalHandlers = [];
    private bool $shutdownRequested = false;
    private array $cleanupCallbacks = [];

    public function __construct()
    {
        $this->setupSignalHandlers();
    }

    private function setupSignalHandlers(): void
    {
        // Handle SIGINT (Ctrl+C)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleShutdownSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleShutdownSignal']);
        } else {
            // Fallback for systems without pcntl
            register_shutdown_function([$this, 'handleShutdown']);
        }
    }

    public function handleShutdownSignal(int $signal): void
    {
        echo "\n\n🛑 Shutdown signal received (signal: {$signal})\n";
        $this->shutdownRequested = true;
        $this->executeCleanup();
    }

    public function handleShutdown(): void
    {
        if (!$this->shutdownRequested) {
            echo "\n\n🛑 Unexpected shutdown detected\n";
            $this->executeCleanup();
        }
    }

    public function addCleanupCallback(callable $callback): void
    {
        $this->cleanupCallbacks[] = $callback;
    }

    public function isShutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    public function processSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    private function executeCleanup(): void
    {
        echo "🧹 Executing cleanup procedures...\n";
        
        foreach ($this->cleanupCallbacks as $callback) {
            try {
                $callback();
            } catch (\Exception $e) {
                echo "⚠️  Cleanup error: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ Cleanup completed\n";
    }
}
