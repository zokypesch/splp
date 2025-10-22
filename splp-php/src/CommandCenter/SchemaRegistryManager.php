<?php

declare(strict_types=1);

namespace Splp\Messaging\CommandCenter;

use Splp\Messaging\Contracts\SchemaRegistryInterface;
use Splp\Messaging\Exceptions\MessagingException;
use Cassandra\Cluster;
use Cassandra\Session;

/**
 * Schema Registry Manager
 * 
 * Manages routes and service information with Cassandra persistence
 */
class SchemaRegistryManager implements SchemaRegistryInterface
{
    private Cluster $cluster;
    private Session $session;
    private array $routes = [];
    private array $services = [];
    private string $keyspace;
    private bool $initialized = false;

    public function __construct(array $contactPoints, string $localDataCenter, string $keyspace)
    {
        $this->keyspace = $keyspace;
        $this->initializeCluster($contactPoints, $localDataCenter);
    }

    /**
     * Initialize schema registry
     */
    public function initialize(): void
    {
        try {
            $this->session = $this->cluster->connect();
            $this->createKeyspace();
            $this->createTables();
            $this->loadRoutes();
            $this->loadServices();
            $this->initialized = true;
        } catch (\Exception $e) {
            throw new MessagingException('Failed to initialize Schema Registry: ' . $e->getMessage());
        }
    }

    /**
     * Register a route
     */
    public function registerRoute(array $routeConfig): void
    {
        if (!$this->initialized) {
            throw new MessagingException('Schema Registry not initialized');
        }

        try {
            $statement = $this->session->prepare(
                "INSERT INTO {$this->keyspace}.routes 
                 (route_id, source_publisher, target_topic, service_name, enabled, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $this->session->execute($statement, [
                'arguments' => [
                    $routeConfig['routeId'],
                    $routeConfig['sourcePublisher'],
                    $routeConfig['targetTopic'],
                    $routeConfig['serviceInfo']['serviceName'],
                    $routeConfig['enabled'],
                    $routeConfig['createdAt'],
                    $routeConfig['updatedAt']
                ]
            ]);

            // Update in-memory cache
            $this->routes[$routeConfig['sourcePublisher']] = $routeConfig;
            
            error_log("Route registered: {$routeConfig['routeId']}");
        } catch (\Exception $e) {
            throw new MessagingException('Failed to register route: ' . $e->getMessage());
        }
    }

    /**
     * Get route by publisher name
     */
    public function getRoute(string $publisherName): ?array
    {
        return $this->routes[$publisherName] ?? null;
    }

    /**
     * Get all routes
     */
    public function getAllRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Update route status
     */
    public function updateRouteStatus(string $publisherName, bool $enabled): void
    {
        if (!isset($this->routes[$publisherName])) {
            throw new MessagingException("Route not found for publisher: {$publisherName}");
        }

        $statement = $this->session->prepare(
            "UPDATE {$this->keyspace}.routes SET enabled = ?, updated_at = ? WHERE source_publisher = ?"
        );

        $this->session->execute($statement, [
            'arguments' => [$enabled, new \DateTime(), $publisherName]
        ]);

        $this->routes[$publisherName]['enabled'] = $enabled;
        $this->routes[$publisherName]['updatedAt'] = new \DateTime();
    }

    /**
     * Delete route
     */
    public function deleteRoute(string $publisherName): void
    {
        if (!isset($this->routes[$publisherName])) {
            throw new MessagingException("Route not found for publisher: {$publisherName}");
        }

        $statement = $this->session->prepare(
            "DELETE FROM {$this->keyspace}.routes WHERE source_publisher = ?"
        );

        $this->session->execute($statement, [
            'arguments' => [$publisherName]
        ]);

        unset($this->routes[$publisherName]);
    }

    /**
     * Register service information
     */
    public function registerService(array $serviceInfo): void
    {
        if (!$this->initialized) {
            throw new MessagingException('Schema Registry not initialized');
        }

        try {
            $statement = $this->session->prepare(
                "INSERT INTO {$this->keyspace}.services 
                 (service_name, version, description, endpoint, tags, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $this->session->execute($statement, [
                'arguments' => [
                    $serviceInfo['serviceName'],
                    $serviceInfo['version'],
                    $serviceInfo['description'] ?? null,
                    $serviceInfo['endpoint'] ?? null,
                    $serviceInfo['tags'] ?? [],
                    $serviceInfo['createdAt'],
                    $serviceInfo['updatedAt']
                ]
            ]);

            // Update in-memory cache
            $this->services[$serviceInfo['serviceName']] = $serviceInfo;
            
            error_log("Service registered: {$serviceInfo['serviceName']}");
        } catch (\Exception $e) {
            throw new MessagingException('Failed to register service: ' . $e->getMessage());
        }
    }

    /**
     * Get service information
     */
    public function getService(string $serviceName): ?array
    {
        return $this->services[$serviceName] ?? null;
    }

    /**
     * Get all services
     */
    public function getAllServices(): array
    {
        return $this->services;
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->session) {
            $this->session->close();
        }
        
        if ($this->cluster) {
            $this->cluster->close();
        }
    }

    /**
     * Initialize Cassandra cluster
     */
    private function initializeCluster(array $contactPoints, string $localDataCenter): void
    {
        $this->cluster = \Cassandra::cluster()
            ->withContactPoints($contactPoints)
            ->withDefaultConsistency(\Cassandra::CONSISTENCY_ONE)
            ->build();
    }

    /**
     * Create keyspace if not exists
     */
    private function createKeyspace(): void
    {
        $this->session->execute(
            "CREATE KEYSPACE IF NOT EXISTS {$this->keyspace} 
             WITH REPLICATION = {
                 'class': 'SimpleStrategy',
                 'replication_factor': 1
             } AND DURABLE_WRITES = true"
        );
    }

    /**
     * Create tables if not exist
     */
    private function createTables(): void
    {
        // Routes table
        $this->session->execute(
            "CREATE TABLE IF NOT EXISTS {$this->keyspace}.routes (
                route_id text PRIMARY KEY,
                source_publisher text,
                target_topic text,
                service_name text,
                enabled boolean,
                created_at timestamp,
                updated_at timestamp
            ) WITH default_time_to_live = 604800"
        );

        // Services table
        $this->session->execute(
            "CREATE TABLE IF NOT EXISTS {$this->keyspace}.services (
                service_name text PRIMARY KEY,
                version text,
                description text,
                endpoint text,
                tags set<text>,
                created_at timestamp,
                updated_at timestamp
            ) WITH default_time_to_live = 604800"
        );

        // Create index for publisher lookups
        $this->session->execute(
            "CREATE INDEX IF NOT EXISTS ON {$this->keyspace}.routes (source_publisher)"
        );
    }

    /**
     * Load routes from Cassandra
     */
    private function loadRoutes(): void
    {
        $result = $this->session->execute("SELECT * FROM {$this->keyspace}.routes");
        
        foreach ($result as $row) {
            $this->routes[$row['source_publisher']] = [
                'routeId' => $row['route_id'],
                'sourcePublisher' => $row['source_publisher'],
                'targetTopic' => $row['target_topic'],
                'serviceName' => $row['service_name'],
                'enabled' => $row['enabled'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
        }
    }

    /**
     * Load services from Cassandra
     */
    private function loadServices(): void
    {
        $result = $this->session->execute("SELECT * FROM {$this->keyspace}.services");
        
        foreach ($result as $row) {
            $this->services[$row['service_name']] = [
                'serviceName' => $row['service_name'],
                'version' => $row['version'],
                'description' => $row['description'],
                'endpoint' => $row['endpoint'],
                'tags' => $row['tags'] ? iterator_to_array($row['tags']) : [],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
        }
    }
}
