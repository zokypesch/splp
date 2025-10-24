/**
 * Example: Publisher using Command Center
 *
 * Instead of sending directly to worker topics,
 * publishers send to command-center-inbox and specify their worker_name.
 * Command Center routes the message to the correct topic.
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateRequestId, generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['10.70.1.23:9092'],
  clientId: 'calc-publisher-client',
  groupId: 'publisher-group',
};

const encryptionKey = process.env.ENCRYPTION_KEY || 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d';

interface CalculateRequest {
  operation: 'add' | 'subtract' | 'multiply' | 'divide';
  a: number;
  b: number;
}

async function main() {
  console.log('Starting Publisher with Command Center...\n');

  // Connect to Kafka
  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();

  console.log('Publisher connected\n');

  try {
    // Example 1: Send calculation request via Command Center
    const requestId = generateRequestId();
    const payload: CalculateRequest = {
      operation: 'add',
      a: 42,
      b: 8,
    };

    console.log('Sending request:', payload);
    console.log('Request ID:', requestId);

    // Encrypt payload
    const encrypted = encryptPayload(payload, encryptionKey, requestId);

    // Create message for Command Center
    const commandCenterMessage = {
      request_id: requestId,
      worker_name: 'calc-publisher', // This identifies which route to use
      data: encrypted.data,
      iv: encrypted.iv,
      tag: encrypted.tag,
    };

    // Send to Command Center inbox topic
    await kafka.sendMessage(
      'command-center-inbox',
      JSON.stringify(commandCenterMessage),
      requestId
    );

    console.log('Message sent to Command Center');
    console.log('Command Center will route to target topic based on worker_name\n');

    // Example 2: Send another request
    const requestId2 = generateRequestId();
    const payload2: CalculateRequest = {
      operation: 'multiply',
      a: 7,
      b: 6,
    };

    console.log('Sending second request:', payload2);
    console.log('Request ID:', requestId2);

    const encrypted2 = encryptPayload(payload2, encryptionKey, requestId2);

    const commandCenterMessage2 = {
      request_id: requestId2,
      worker_name: 'calc-publisher',
      data: encrypted2.data,
      iv: encrypted2.iv,
      tag: encrypted2.tag,
    };

    await kafka.sendMessage(
      'command-center-inbox',
      JSON.stringify(commandCenterMessage2),
      requestId2
    );

    console.log('Second message sent to Command Center\n');

    // Wait a bit before closing
    await new Promise((resolve) => setTimeout(resolve, 2000));
  } catch (error) {
    console.error('Error:', error);
  } finally {
    await kafka.disconnect();
    console.log('Publisher disconnected');
  }
}

main().catch((error) => {
  console.error('Publisher error:', error);
  process.exit(1);
});
