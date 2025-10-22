<?php

declare(strict_types=1);

namespace Splp\Messaging\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;
use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\CommandCenter\CommandCenter;

/**
 * Laravel Service Provider for SPLP Messaging
 */
class SplpServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/splp.php', 'splp');

        $this->app->singleton('splp.messaging', function ($app) {
            $config = $app['config']['splp'];
            return new MessagingClient($config);
        });

        $this->app->singleton('splp.command-center', function ($app) {
            $config = $app['config']['splp'];
            return new CommandCenter($config);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/splp.php' => config_path('splp.php'),
            ], 'splp-config');

            $this->publishes([
                __DIR__ . '/migrations' => database_path('migrations'),
            ], 'splp-migrations');
        }
    }
}

/**
 * Laravel Facade for SPLP Messaging
 */
class SplpMessaging extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'splp.messaging';
    }
}

/**
 * Laravel Facade for SPLP Command Center
 */
class SplpCommandCenter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'splp.command-center';
    }
}
