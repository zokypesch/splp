<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Splp\Messaging\Core\KafkaWrapper;
use Splp\Messaging\Core\Service1MessageProcessor;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CassandraConfig;
use Splp\Messaging\Types\EncryptionConfig;
use Splp\Messaging\Utils\SignalHandler;

class SplpListenerCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'splp:listen 
                            {--topic=service-1-topic : Topic yang akan di-listen}
                            {--group-id=service-1z-group : Consumer group ID}
                            {--worker-name=service-1-publisher : Worker name untuk routing}';

    /**
     * The console command description.
     */
    protected $description = 'Menjalankan SPLP Listener untuk memproses pesan dari Kafka';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil');
        $this->info('    Population Data Verification Service (Laravel)');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        try {
            // Load configuration
            $config = $this->loadConfiguration();
            
            // Print configuration
            $this->printConfiguration($config);
            $this->newLine();

            // Create configurations
            $kafkaConfig = new KafkaConfig(
                brokers: $config['kafka']['brokers'],
                clientId: $config['kafka']['clientId'],
                groupId: $this->option('group-id'),
                requestTimeoutMs: $config['kafka']['requestTimeoutMs'],
                consumerTopic: $this->option('topic'),
                producerTopic: $config['kafka']['producerTopic']
            );

            $cassandraConfig = new CassandraConfig(
                contactPoints: $config['cassandra']['contactPoints'],
                localDataCenter: $config['cassandra']['localDataCenter'],
                keyspace: $config['cassandra']['keyspace']
            );

            $encryptionConfig = new EncryptionConfig($config['encryption']['key']);

            // Create services
            $encryptionService = new EncryptionService($encryptionConfig->key);
            $logger = new CassandraLogger($cassandraConfig);
            $kafkaWrapper = new KafkaWrapper($kafkaConfig, $encryptionService, $logger);

            // Initialize services
            $encryptionService->initialize();
            $logger->initialize();
            $kafkaWrapper->initialize();

            // Create message processor
            $processor = new Service1MessageProcessor(
                $encryptionService,
                $logger,
                $kafkaConfig,
                $kafkaWrapper,
                $this->option('worker-name')
            );

            // Set up signal handling for graceful shutdown
            $signalHandler = new SignalHandler();
            $signalHandler->addCleanupCallback(function() use ($kafkaWrapper) {
                $this->info('🔒 Closing Kafka connections...');
                $kafkaWrapper->close();
            });

            // Set message handler
            $kafkaWrapper->setMessageHandler($processor);

            $this->info('✓ Dukcapil terhubung ke Kafka');
            $this->info('✓ Listening on topic: ' . $this->option('topic') . ' (group: ' . $this->option('group-id') . ')');
            $this->info('✓ Producer topic: ' . $config['kafka']['producerTopic']);
            $this->info('✓ Siap memverifikasi data kependudukan dan mengirim hasil ke Command Center');
            $this->newLine();

            // Start consuming
            $kafkaWrapper->startConsuming([$this->option('topic')]);

            $this->info('Service 1 (' . $config['service']['name'] . ') is running and waiting for messages...');
            $this->info('Press Ctrl+C to exit');
            $this->newLine();

            // Main loop with signal handling
            while (!$signalHandler->isShutdownRequested()) {
                $signalHandler->processSignals();
                usleep(100000); // 100ms
            }

            $this->newLine();
            $this->info('Shutting down ' . $config['service']['name'] . '...');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Fatal error: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Load configuration from Laravel config
     */
    private function loadConfiguration(): array
    {
        $config = config('splp');
        
        return [
            'kafka' => [
                'brokers' => $config['kafka']['brokers'],
                'clientId' => $config['kafka']['clientId'],
                'groupId' => $this->option('group-id'),
                'requestTimeoutMs' => $config['kafka']['requestTimeoutMs'],
                'consumerTopic' => $this->option('topic'),
                'producerTopic' => $config['kafka']['producerTopic']
            ],
            'cassandra' => [
                'contactPoints' => $config['cassandra']['contactPoints'],
                'localDataCenter' => $config['cassandra']['localDataCenter'],
                'keyspace' => $config['cassandra']['keyspace']
            ],
            'encryption' => [
                'key' => $config['encryption']['key']
            ],
            'service' => [
                'name' => $config['service']['name'],
                'version' => $config['service']['version'],
                'workerName' => $this->option('worker-name')
            ]
        ];
    }

    /**
     * Print configuration to console
     */
    private function printConfiguration(array $config): void
    {
        $this->info('📋 Configuration:');
        $this->line('   Kafka Brokers: ' . implode(', ', $config['kafka']['brokers']));
        $this->line('   Client ID: ' . $config['kafka']['clientId']);
        $this->line('   Group ID: ' . $config['kafka']['groupId']);
        $this->line('   Consumer Topic: ' . $config['kafka']['consumerTopic']);
        $this->line('   Producer Topic: ' . $config['kafka']['producerTopic']);
        $this->line('   Cassandra Keyspace: ' . $config['cassandra']['keyspace']);
        $this->line('   Service Name: ' . $config['service']['name']);
        $this->line('   Service Version: ' . $config['service']['version']);
        $this->line('   Worker Name: ' . $config['service']['workerName']);
    }
}
