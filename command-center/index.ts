/**
 * Command Center Main Entry Point
 * Starts the command center service and provides CLI for route management
 */

import { CommandCenter } from './lib/command-center.js';
import type { CommandCenterConfig, RouteConfig, ServiceInfo } from './types/index.js';
import { generateEncryptionKey } from '../splp-bun/src/index.js';

// Configuration
const config: CommandCenterConfig = {
  kafka: {
    brokers: ['localhost:9092'],
    clientId: 'command-center',
    groupId: 'command-center-group',
  },
  cassandra: {
    contactPoints: ['localhost'],
    localDataCenter: 'datacenter1',
    keyspace: 'command_center',
  },
  encryption: {
    encryptionKey: process.env.ENCRYPTION_KEY || generateEncryptionKey(),
  },
  commandCenter: {
    inboxTopic: 'command-center-inbox',
    enableAutoRouting: true,
    defaultTimeout: 30000,
  },
};

async function main() {
  console.log('='.repeat(60));
  console.log('Command Center Service');
  console.log('='.repeat(60));

  const commandCenter = new CommandCenter(config);

  // Initialize
  await commandCenter.initialize();

  // Register some example routes
  await registerExampleRoutes(commandCenter);

  // Start routing
  await commandCenter.start();

  console.log('\nCommand Center is running...');
  console.log('Press Ctrl+C to shutdown\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down Command Center...');
    await commandCenter.shutdown();
    process.exit(0);
  });
}

/**
 * Register example routes (in production, these would be loaded from config or API)
 */
async function registerExampleRoutes(commandCenter: CommandCenter) {
  const registry = commandCenter.getSchemaRegistry();

  console.log('\nRegistering example routes...');

  // Example Service 1: Calculator Service
  const calculatorService: ServiceInfo = {
    serviceName: 'calculator-service',
    version: '1.0.0',
    description: 'Mathematical calculation service',
    endpoint: 'http://localhost:3001',
    tags: ['math', 'calculator'],
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  const calculatorRoute: RouteConfig = {
    routeId: 'route-calc-001',
    sourcePublisher: 'calc-publisher',
    targetTopic: 'calculate',
    serviceInfo: calculatorService,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(calculatorRoute);

  // Example Service 2: User Service
  const userService: ServiceInfo = {
    serviceName: 'user-service',
    version: '1.0.0',
    description: 'User management service',
    endpoint: 'http://localhost:3002',
    tags: ['user', 'management'],
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  const userRoute: RouteConfig = {
    routeId: 'route-user-001',
    sourcePublisher: 'user-publisher',
    targetTopic: 'get-user',
    serviceInfo: userService,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(userRoute);

  console.log('✓ Registered routes:');
  console.log('  - calc-publisher -> calculate');
  console.log('  - user-publisher -> get-user');
  console.log('');
}

// Only run main() if this file is executed directly (not imported)
if (import.meta.url === `file://${process.argv[1]}`) {
  main().catch((error) => {
    console.error('Command Center error:', error);
    process.exit(1);
  });
}

// Export for use as a library
export { CommandCenter } from './lib/command-center.js';
export { SchemaRegistryManager } from './lib/schema-registry.js';
export { MetadataLogger } from './lib/metadata-logger.js';
export * from './types/index.js';
