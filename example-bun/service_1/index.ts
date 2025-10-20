/**
 * Dukcapil - Direktorat Jenderal Kependudukan dan Pencatatan Sipil
 * Verifies citizen identity and population data
 * Checks NIK validity, family data, and address verification
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload, decryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig, EncryptedMessage } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['localhost:9092'],
  clientId: 'dukcapil-service',
  groupId: 'service-1-group',
};

const encryptionKey = process.env.ENCRYPTION_KEY || generateEncryptionKey();

interface BansosCitizenRequest {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
}

interface DukcapilVerificationResult {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
  processedBy: string;
  nikStatus: 'valid' | 'invalid' | 'blocked';
  dataMatch: boolean;
  familyMembers: number;
  addressVerified: boolean;
  verifiedAt: string;
  notes?: string;
}

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil');
  console.log('    Population Data Verification Service');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();
  await kafka.connectConsumer();

  console.log('✓ Dukcapil terhubung ke Kafka');
  console.log('✓ Listening on topic: service-1-topic (group: service-1-group)');
  console.log('✓ Siap memverifikasi data kependudukan\n');

  // Subscribe to service-1-topic (Command Center routes here)
  await kafka.subscribe(['service-1-topic'], async ({ topic, message }) => {
    const startTime = Date.now();

    try {
      if (!message.value) return;

      const messageValue = message.value.toString();
      console.log('─'.repeat(60));
      console.log('📥 [DUKCAPIL] Menerima data dari Command Center');

      // Parse and decrypt
      const encryptedMsg: EncryptedMessage = JSON.parse(messageValue);
      const { requestId, payload } = decryptPayload<BansosCitizenRequest>(
        encryptedMsg,
        encryptionKey
      );

      console.log('  Request ID:', requestId);
      console.log('  Registration ID:', payload.registrationId);
      console.log('  NIK:', payload.nik);
      console.log('  Nama:', payload.fullName);
      console.log('  Tanggal Lahir:', payload.dateOfBirth);
      console.log('  Alamat:', payload.address);
      console.log('');

      // Verify population data
      console.log('🔄 Memverifikasi data kependudukan...');
      await new Promise((resolve) => setTimeout(resolve, 1000)); // Simulate verification

      // Simulate NIK verification
      const nikValid = payload.nik.length === 16 && payload.nik.startsWith('317'); // Jakarta NIK
      const dataMatch = payload.fullName.length > 0;
      const familyMembers = Math.floor(Math.random() * 5) + 1; // 1-5 family members

      const processedData: DukcapilVerificationResult = {
        ...payload,
        processedBy: 'dukcapil',
        nikStatus: nikValid ? 'valid' : 'invalid',
        dataMatch: dataMatch,
        familyMembers: familyMembers,
        addressVerified: true,
        verifiedAt: new Date().toISOString(),
        notes: nikValid ? 'Data kependudukan terverifikasi' : 'NIK tidak valid',
      };

      console.log('  ✅ Status NIK:', processedData.nikStatus.toUpperCase());
      console.log('  ✅ Data Cocok:', dataMatch ? 'YA' : 'TIDAK');
      console.log('  ✅ Jumlah Anggota Keluarga:', familyMembers);
      console.log('  ✅ Alamat Terverifikasi:', processedData.addressVerified ? 'YA' : 'TIDAK');
      console.log('  📋 Catatan:', processedData.notes);
      console.log('  🏢 Diproses oleh: DUKCAPIL');
      console.log('');

      // Encrypt processed data
      const encrypted = encryptPayload(processedData, encryptionKey, requestId);

      // Send back to Command Center for routing to service_2
      const outgoingMessage = {
        request_id: requestId,
        worker_name: 'service-1-publisher', // This identifies routing: service_1 -> service_2
        data: encrypted.data,
        iv: encrypted.iv,
        tag: encrypted.tag,
      };

      console.log('📤 Mengirim hasil verifikasi ke Command Center...');
      await kafka.sendMessage(
        'command-center-inbox',
        JSON.stringify(outgoingMessage),
        requestId
      );

      const duration = Date.now() - startTime;
      console.log('✓ Hasil verifikasi terkirim ke Command Center');
      console.log(`  Waktu proses: ${duration}ms`);
      console.log('─'.repeat(60));
      console.log('');

    } catch (error) {
      console.error('❌ Error processing message:', error);
    }
  });

  console.log('Service 1 is running and waiting for messages...');
  console.log('Press Ctrl+C to exit\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down Service 1...');
    await kafka.disconnect();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error('Service 1 error:', error);
  process.exit(1);
});
