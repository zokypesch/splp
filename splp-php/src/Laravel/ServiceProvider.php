<?php

declare(strict_types=1);

namespace Splp\Messaging\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Facade;
use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;
use Splp\Messaging\Utils\SignalHandler;

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

        // Register legacy MessagingClient for backward compatibility
        $this->app->singleton('splp.messaging', function ($app) {
            $config = $app['config']['splp'];
            return new MessagingClient($config);
        });

        // Register production-ready KafkaWrapper
        $this->app->singleton('splp.kafka', function ($app) {
            $config = $app['config']['splp'];
            
            $kafkaConfig = new KafkaConfig(
                brokers: $config['kafka']['brokers'],
                clientId: $config['kafka']['clientId'],
                groupId: $config['kafka']['groupId'],
                requestTimeoutMs: $config['kafka']['requestTimeoutMs'] ?? 30000,
                consumerTopic: $config['kafka']['consumerTopic'] ?? 'service-1-topic',
                producerTopic: $config['kafka']['producerTopic'] ?? 'command-center-inbox'
            );

            $cassandraConfig = new CassandraConfig(
                contactPoints: $config['cassandra']['contactPoints'],
                localDataCenter: $config['cassandra']['localDataCenter'],
                keyspace: $config['cassandra']['keyspace']
            );

            $encryptionConfig = new EncryptionConfig($config['encryption']['key']);
            $encryptionService = new EncryptionService($encryptionConfig->key);
            $logger = new CassandraLogger($cassandraConfig);

            return new KafkaWrapper($kafkaConfig, $encryptionService, $logger);
        });

        // Register Service1MessageProcessor
        $this->app->singleton('splp.service1.processor', function ($app) {
            $config = $app['config']['splp'];
            
            $kafkaConfig = new KafkaConfig(
                brokers: $config['kafka']['brokers'],
                clientId: $config['kafka']['clientId'],
                groupId: $config['kafka']['groupId'],
                requestTimeoutMs: $config['kafka']['requestTimeoutMs'] ?? 30000,
                consumerTopic: $config['kafka']['consumerTopic'] ?? 'service-1-topic',
                producerTopic: $config['kafka']['producerTopic'] ?? 'command-center-inbox'
            );

            $cassandraConfig = new CassandraConfig(
                contactPoints: $config['cassandra']['contactPoints'],
                localDataCenter: $config['cassandra']['localDataCenter'],
                keyspace: $config['cassandra']['keyspace']
            );

            $encryptionConfig = new EncryptionConfig($config['encryption']['key']);
            $encryptionService = new EncryptionService($encryptionConfig->key);
            $logger = new CassandraLogger($cassandraConfig);
            $kafkaWrapper = $app['splp.kafka'];

            return new Service1MessageProcessor(
                $encryptionService,
                $logger,
                $kafkaConfig,
                $kafkaWrapper,
                $config['service']['workerName'] ?? 'service-1-publisher'
            );
        });

        // Register SignalHandler
        $this->app->singleton('splp.signal-handler', function ($app) {
            return new SignalHandler();
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

            // Register Artisan commands
            if ($this->app->runningInConsole()) {
                $this->commands([
                    Commands\SplpListenerCommand::class,
                ]);
            }
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
