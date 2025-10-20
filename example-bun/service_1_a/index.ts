/**
 * BPJS Ketenagakerjaan (BPJS TK)
 * Verifies employment status and worker contributions
 * Checks active employment, salary data, and contribution history
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload, decryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig, EncryptedMessage } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['localhost:9092'],
  clientId: 'bpjs-tk-service',
  groupId: 'service-1a-group', // UNIQUE GROUP
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

interface BPJSTKVerificationResult {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
  processedBy: string;
  employmentStatus: 'active' | 'inactive' | 'not_registered';
  employerName?: string;
  monthlySalary?: number;
  contributionMonths: number;
  lastContribution?: string;
  verifiedAt: string;
  notes?: string;
}

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏢 BPJS KETENAGAKERJAAN');
  console.log('    Employment & Worker Contribution Verification');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();
  await kafka.connectConsumer();

  console.log('✓ BPJS TK terhubung ke Kafka');
  console.log('✓ Listening on topic: service-1-topic (group: service-1a-group)');
  console.log('✓ Siap memverifikasi data ketenagakerjaan\n');

  // Subscribe to service-1-topic (SAME TOPIC as other Service 1 variants)
  await kafka.subscribe(['service-1-topic'], async ({ topic, message }) => {
    const startTime = Date.now();

    try {
      if (!message.value) return;

      const messageValue = message.value.toString();
      console.log('─'.repeat(60));
      console.log('📥 [BPJS TK] Menerima data dari Command Center');

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
      console.log('');

      // Verify employment data
      console.log('🔄 Memverifikasi data ketenagakerjaan...');
      await new Promise((resolve) => setTimeout(resolve, 800)); // Simulate verification

      // Simulate employment check
      const isEmployed = Math.random() > 0.3; // 70% chance employed
      const contributionMonths = isEmployed ? Math.floor(Math.random() * 60) + 6 : 0;
      const monthlySalary = isEmployed ? Math.floor(Math.random() * 8000000) + 3000000 : undefined;

      const processedData: BPJSTKVerificationResult = {
        ...payload,
        processedBy: 'bpjs-tk',
        employmentStatus: isEmployed ? 'active' : 'not_registered',
        employerName: isEmployed ? 'PT. Maju Jaya Indonesia' : undefined,
        monthlySalary: monthlySalary,
        contributionMonths: contributionMonths,
        lastContribution: isEmployed ? new Date().toISOString().split('T')[0] : undefined,
        verifiedAt: new Date().toISOString(),
        notes: isEmployed
          ? `Peserta aktif dengan ${contributionMonths} bulan iuran`
          : 'Tidak terdaftar sebagai peserta BPJS Ketenagakerjaan',
      };

      console.log('  ✅ Status Kepesertaan:', processedData.employmentStatus.toUpperCase());
      if (isEmployed) {
        console.log('  ✅ Nama Perusahaan:', processedData.employerName);
        console.log('  ✅ Gaji Bulanan: Rp', monthlySalary?.toLocaleString('id-ID'));
        console.log('  ✅ Lama Iuran:', contributionMonths, 'bulan');
        console.log('  ✅ Iuran Terakhir:', processedData.lastContribution);
      }
      console.log('  📋 Catatan:', processedData.notes);
      console.log('  🏢 Diproses oleh: BPJS KETENAGAKERJAAN');
      console.log('');

      // Encrypt processed data
      const encrypted = encryptPayload(processedData, encryptionKey, requestId);

      // Send back to Command Center for routing to service_2
      const outgoingMessage = {
        request_id: requestId,
        worker_name: 'service-1a-publisher',
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

  console.log('BPJS TK menunggu data verifikasi...');
  console.log('Press Ctrl+C to exit\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down BPJS TK...');
    await kafka.disconnect();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error('BPJS TK error:', error);
  process.exit(1);
});
