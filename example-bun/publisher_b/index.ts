/**
 * Publisher B
 * Sends initial request to Command Center for Service 1B (Fraud Detection)
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateRequestId, generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['10.70.1.23:9092'], // Match with main publisher configuration
  clientId: 'publisher-b-client',
  groupId: 'publisher-b-group',
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
  console.log('Publisher B (Service 1B - Fraud Detection)');
  console.log('========================================\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();

  console.log('✓ Publisher B connected to Kafka\n');

  try {
    // Create sample order with high amount (triggers fraud checks)
    const requestId = generateRequestId();
    const payload: InitialRequest = {
      orderId: `ORD-B-${Date.now()}`,
      userId: 'user-99999',
      amount: 7599.99, // High amount - may trigger fraud warnings
      items: ['smartphone', 'tablet', 'smartwatch', 'laptop'],
    };

    console.log('Creating new order request for Fraud Detection:');
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
    console.log('  → service_2 receives 4 different results (high amount triggers fraud warning)\n');

    // Wait a bit for processing
    console.log('Waiting for services to process...\n');
    await new Promise((resolve) => setTimeout(resolve, 5000));

  } catch (error) {
    console.error('❌ Error:', error);
  } finally {
    await kafka.disconnect();
    console.log('Publisher B disconnected');
  }
}

main().catch((error) => {
  console.error('Publisher B error:', error);
  process.exit(1);
});
