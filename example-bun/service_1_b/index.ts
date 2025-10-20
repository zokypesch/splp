/**
 * BPJS Kesehatan (Health Insurance)
 * Verifies health insurance status and medical history
 * Checks active membership, health conditions, and medical treatment history
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload, decryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig, EncryptedMessage } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['localhost:9092'],
  clientId: 'bpjs-kesehatan-service',
  groupId: 'service-1b-group', // UNIQUE GROUP
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

interface BPJSKesehatanVerificationResult {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
  processedBy: string;
  membershipStatus: 'active' | 'inactive' | 'not_registered';
  membershipClass: '1' | '2' | '3' | 'PBI' | null;
  hasChronicIllness: boolean;
  chronicIllnessList?: string[];
  lastCheckup?: string;
  healthRiskLevel: 'low' | 'medium' | 'high';
  verifiedAt: string;
  notes?: string;
}

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏥 BPJS KESEHATAN');
  console.log('    Health Insurance & Medical History Verification');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();
  await kafka.connectConsumer();

  console.log('✓ BPJS Kesehatan terhubung ke Kafka');
  console.log('✓ Listening on topic: service-1-topic (group: service-1b-group)');
  console.log('✓ Siap memverifikasi data kesehatan\n');

  // Subscribe to service-1-topic (SAME TOPIC as other Service 1 variants)
  await kafka.subscribe(['service-1-topic'], async ({ topic, message }) => {
    const startTime = Date.now();

    try {
      if (!message.value) return;

      const messageValue = message.value.toString();
      console.log('─'.repeat(60));
      console.log('📥 [BPJS KESEHATAN] Menerima data dari Command Center');

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

      // Verify health insurance data
      console.log('🔄 Memverifikasi data kesehatan...');
      await new Promise((resolve) => setTimeout(resolve, 1200)); // Simulate verification

      // Simulate health check
      const isRegistered = Math.random() > 0.2; // 80% chance registered
      const hasChronicIllness = Math.random() > 0.7; // 30% chance has chronic illness
      const membershipClasses: ('1' | '2' | '3' | 'PBI')[] = ['1', '2', '3', 'PBI'];
      const membershipClass = isRegistered ? membershipClasses[Math.floor(Math.random() * 4)] : null;

      const chronicIllnesses = ['Diabetes', 'Hipertensi', 'Asma', 'Jantung'];
      const selectedIllnesses = hasChronicIllness
        ? [chronicIllnesses[Math.floor(Math.random() * chronicIllnesses.length)]]
        : [];

      const healthRiskLevel = hasChronicIllness ? 'high' : isRegistered ? 'low' : 'medium';

      const processedData: BPJSKesehatanVerificationResult = {
        ...payload,
        processedBy: 'bpjs-kesehatan',
        membershipStatus: isRegistered ? 'active' : 'not_registered',
        membershipClass: membershipClass,
        hasChronicIllness: hasChronicIllness,
        chronicIllnessList: selectedIllnesses.length > 0 ? selectedIllnesses : undefined,
        lastCheckup: isRegistered ? new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] : undefined,
        healthRiskLevel: healthRiskLevel,
        verifiedAt: new Date().toISOString(),
        notes: isRegistered
          ? `Peserta aktif kelas ${membershipClass}${hasChronicIllness ? ', memiliki penyakit kronis' : ''}`
          : 'Tidak terdaftar sebagai peserta BPJS Kesehatan',
      };

      console.log('  ✅ Status Kepesertaan:', processedData.membershipStatus.toUpperCase());
      if (isRegistered) {
        console.log('  ✅ Kelas Peserta:', membershipClass);
        console.log('  ✅ Penyakit Kronis:', hasChronicIllness ? 'ADA' : 'TIDAK ADA');
        if (hasChronicIllness && selectedIllnesses.length > 0) {
          console.log('  ✅ Daftar Penyakit:', selectedIllnesses.join(', '));
        }
        console.log('  ✅ Pemeriksaan Terakhir:', processedData.lastCheckup);
        console.log('  ✅ Tingkat Risiko Kesehatan:', healthRiskLevel.toUpperCase());
      }
      console.log('  📋 Catatan:', processedData.notes);
      console.log('  🏢 Diproses oleh: BPJS KESEHATAN');
      console.log('');

      // Encrypt processed data
      const encrypted = encryptPayload(processedData, encryptionKey, requestId);

      // Send back to Command Center for routing to service_2
      const outgoingMessage = {
        request_id: requestId,
        worker_name: 'service-1b-publisher',
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

  console.log('BPJS Kesehatan menunggu data verifikasi...');
  console.log('Press Ctrl+C to exit\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down BPJS Kesehatan...');
    await kafka.disconnect();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error('BPJS Kesehatan error:', error);
  process.exit(1);
});
