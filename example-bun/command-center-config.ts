/**
 * Command Center Configuration for Example Chain
 * Configures routes for: Publisher → Service 1 → Service 2
 */

import { CommandCenter } from '../command-center/index.js';
import type { CommandCenterConfig, RouteConfig, ServiceInfo } from '../command-center/types/index.js';
import { generateEncryptionKey } from '../splp-bun/src/index.js';

// Configuration
const config: CommandCenterConfig = {
  kafka: {
    brokers: ['10.70.1.23:9092'],
    clientId: 'command-center-example',
    groupId: 'command-center-example-group',
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
  console.log('═'.repeat(70));
  console.log('🏛️  COMMAND CENTER - Sistem Verifikasi Bantuan Sosial');
  console.log('    Social Assistance Verification System');
  console.log('═'.repeat(70));
  console.log('');

  const commandCenter = new CommandCenter(config);

  // Initialize
  await commandCenter.initialize();

  // Register routes for the chain
  await registerChainRoutes(commandCenter);

  // Start routing
  await commandCenter.start();

  console.log('Command Center aktif...');
  console.log('');
  console.log('📋 Configured Routes:');
  console.log('  1. Kemensos          → service-1-topic   (Routing ke SEMUA layanan verifikasi)');
  console.log('  2. Dukcapil          → service-2-topic   (Hasil verifikasi ke Kemensos)');
  console.log('  3. BPJS TK           → service-2-topic   (Hasil verifikasi ke Kemensos)');
  console.log('  4. BPJS Kesehatan    → service-2-topic   (Hasil verifikasi ke Kemensos)');
  console.log('  5. Bank Indonesia    → service-2-topic   (Hasil verifikasi ke Kemensos)');
  console.log('');
  console.log('🔄 Message Flow (Parallel Processing):');
  console.log('                      ┌──> Dukcapil (Kependudukan)      ──┐');
  console.log('                      ├──> BPJS TK (Ketenagakerjaan)     ──┤');
  console.log('  Kemensos → CC ──────┼──> BPJS Kesehatan (Kesehatan)   ──┼──> CC → Kemensos');
  console.log('  (1 pengajuan)       └──> Bank Indonesia (Finansial)   ──┘     (4 hasil)');
  console.log('                        (SEMUA menerima data yang sama)');
  console.log('');
  console.log('Press Ctrl+C to shutdown');
  console.log('');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down Command Center...');
    await commandCenter.shutdown();
    process.exit(0);
  });
}

/**
 * Register routes for the verification chain
 */
async function registerChainRoutes(commandCenter: CommandCenter) {
  const registry = commandCenter.getSchemaRegistry();

  console.log('📝 Mendaftarkan routes verifikasi...\n');

  // Verification Services Info
  const verificationServicesInfo: ServiceInfo = {
    serviceName: 'bansos-verification-services',
    version: '1.0.0',
    description: 'Layanan verifikasi bantuan sosial multi-instansi',
    endpoint: 'http://localhost:3001',
    tags: ['bansos', 'verification', 'government'],
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  // Route 1: Kemensos → All Verification Services
  const route1: RouteConfig = {
    routeId: 'route-kemensos-to-verification',
    sourcePublisher: 'initial-publisher',      // From Kemensos
    targetTopic: 'service-1-topic',            // To all verification services
    serviceInfo: verificationServicesInfo,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(route1);
  console.log('✓ Route 1: Kemensos → service-1-topic (All verification services)');

  // Kemensos Result Aggregation Info
  const kemensosAggregationInfo: ServiceInfo = {
    serviceName: 'kemensos-result-aggregation',
    version: '1.0.0',
    description: 'Agregasi hasil verifikasi dari semua instansi',
    endpoint: 'http://localhost:3002',
    tags: ['bansos', 'aggregation', 'kemensos'],
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  // Route 2: Dukcapil → Kemensos
  const route2: RouteConfig = {
    routeId: 'route-dukcapil-to-kemensos',
    sourcePublisher: 'service-1-publisher',    // From Dukcapil
    targetTopic: 'service-2-topic',            // To Kemensos aggregation
    serviceInfo: kemensosAggregationInfo,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(route2);
  console.log('✓ Route 2: Dukcapil → service-2-topic (Kemensos)');

  // Route 3: BPJS TK → Kemensos
  const route3: RouteConfig = {
    routeId: 'route-bpjstk-to-kemensos',
    sourcePublisher: 'service-1a-publisher',   // From BPJS TK
    targetTopic: 'service-2-topic',
    serviceInfo: kemensosAggregationInfo,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(route3);
  console.log('✓ Route 3: BPJS TK → service-2-topic (Kemensos)');

  // Route 4: BPJS Kesehatan → Kemensos
  const route4: RouteConfig = {
    routeId: 'route-bpjskes-to-kemensos',
    sourcePublisher: 'service-1b-publisher',   // From BPJS Kesehatan
    targetTopic: 'service-2-topic',
    serviceInfo: kemensosAggregationInfo,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(route4);
  console.log('✓ Route 4: BPJS Kesehatan → service-2-topic (Kemensos)');

  // Route 5: Bank Indonesia → Kemensos
  const route5: RouteConfig = {
    routeId: 'route-bi-to-kemensos',
    sourcePublisher: 'service-1c-publisher',   // From Bank Indonesia
    targetTopic: 'service-2-topic',
    serviceInfo: kemensosAggregationInfo,
    enabled: true,
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  await registry.registerRoute(route5);
  console.log('✓ Route 5: Bank Indonesia → service-2-topic (Kemensos)');
  console.log('');
}

// Run the command center
main().catch((error) => {
  console.error('Command Center error:', error);
  process.exit(1);
});
