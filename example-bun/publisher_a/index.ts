/**
 * Publisher A
 * Sends initial request to Command Center for Service 1A (Inventory Check)
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateRequestId, generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['localhost:9092'],
  clientId: 'publisher-a-client',
  groupId: 'publisher-a-group',
};

const encryptionKey = process.env.ENCRYPTION_KEY || generateEncryptionKey();

interface InitialRequest {
  orderId: string;
  userId: string;
  amount: number;
  items: string[];
}

async function main() {
  console.log('========================================');
  console.log('Publisher A (Service 1A - Inventory)');
  console.log('========================================\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();

  console.log('✓ Publisher A connected to Kafka\n');

  try {
    // Create sample order
    const requestId = generateRequestId();
    const payload: InitialRequest = {
      orderId: `ORD-A-${Date.now()}`,
      userId: 'user-67890',
      amount: 459.99,
      items: ['monitor', 'webcam', 'microphone', 'headphones'],
    };

    console.log('Creating new order request for Inventory Check:');
    console.log('  Request ID:', requestId);
    console.log('  Order:', JSON.stringify(payload, null, 2));
    console.log('');

    // Encrypt payload
    const encrypted = encryptPayload(payload, encryptionKey, requestId);

    // Create message for Command Center
    const message = {
      request_id: requestId,
      worker_name: 'initial-publisher', // Routes to service-1-topic (ALL Service 1 variants receive)
      data: encrypted.data,
      iv: encrypted.iv,
      tag: encrypted.tag,
    };

    // Send to Command Center
    console.log('Sending to Command Center...');
    await kafka.sendMessage(
      'command-center-inbox',
      JSON.stringify(message),
      requestId
    );

    console.log('✓ Message sent to Command Center');
    console.log('  → Command Center routes to service-1-topic');
    console.log('  → ALL Service 1 variants (1, 1A, 1B, 1C) receive the SAME message');
    console.log('  → Each processes in parallel and sends to service_2');
    console.log('  → service_2 receives 4 different results\n');

    // Wait a bit for processing
    console.log('Waiting for services to process...\n');
    await new Promise((resolve) => setTimeout(resolve, 5000));

  } catch (error) {
    console.error('❌ Error:', error);
  } finally {
    await kafka.disconnect();
    console.log('Publisher A disconnected');
  }
}

main().catch((error) => {
  console.error('Publisher A error:', error);
  process.exit(1);
});
