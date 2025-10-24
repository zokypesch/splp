/**
 * Kemensos (Ministry of Social Affairs)
 * Submits social assistance verification request to Command Center
 * Command Center routes to all verification services
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateRequestId, generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['10.70.1.23:9092'],
  clientId: 'kemensos-publisher',
  groupId: 'kemensos-group',
};

const encryptionKey = process.env.ENCRYPTION_KEY || 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7d';
console.log("using key: ", encryptionKey);

interface BansosCitizenRequest {
  registrationId: string;
  nik: string;              // Nomor Induk Kependudukan
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;   // Type of social assistance requested
  requestedAmount: number;  // Amount of assistance
}

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏛️  KEMENSOS - Kementerian Sosial RI');
  console.log('    Social Assistance Verification System');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();

  console.log('✓ Kemensos connected to Kafka\n');

  try {
    // Create social assistance verification request
    const requestId = generateRequestId();
    const payload: BansosCitizenRequest = {
      registrationId: `BANSOS-${Date.now()}`,
      nik: '3174012501850001',
      fullName: 'Hamba Allah',
      dateOfBirth: '1985-01-25',
      address: 'Jl. Merdeka No. 123, Jakarta Pusat',
      assistanceType: 'XXXX', // Program Keluarga Harapan
      requestedAmount: 1111111111, // Rp 3.000.000
    };

    console.log('📋 Pengajuan Bantuan Sosial:');
    console.log('  Request ID:', requestId);
    console.log('  Registration ID:', payload.registrationId);
    console.log('  NIK:', payload.nik);
    console.log('  Nama Lengkap:', payload.fullName);
    console.log('  Tanggal Lahir:', payload.dateOfBirth);
    console.log('  Alamat:', payload.address);
    console.log('  Jenis Bantuan:', payload.assistanceType);
    console.log('  Nominal: Rp', payload.requestedAmount.toLocaleString('id-ID'));
    console.log('');

    // Encrypt payload
    const encrypted = encryptPayload(payload, encryptionKey, requestId);
   
    // Create message for Command Center
    const message = {
      request_id: requestId,
      worker_name: 'initial-publisher', // Routes to all verification services
      data: encrypted.data,
      iv: encrypted.iv,
      tag: encrypted.tag,
    };

    // Send to Command Center
    console.log('📤 Mengirim ke Command Center untuk verifikasi...');
    await kafka.sendMessage(
      'command-center-inbox',
      JSON.stringify(message),
      requestId
    );

    console.log('✓ Data terkirim ke Command Center');
    console.log('  → Akan diverifikasi oleh:');
    console.log('     • Dukcapil (Data Kependudukan)');
    console.log('     • BPJS Ketenagakerjaan (Status Pekerjaan)');
    console.log('     • BPJS Kesehatan (Status Kesehatan)');
    console.log('     • Bank Indonesia (Data Finansial)');
    console.log('');

    // Wait a bit for processing
    console.log('Waiting for services to process...\n');
    await new Promise((resolve) => setTimeout(resolve, 5000));

  } catch (error) {
    console.error('❌ Error:', error);
  } finally {
    await kafka.disconnect();
    console.log('Publisher disconnected');
  }
}

main().catch((error) => {
  console.error('Publisher error:', error);
  process.exit(1);
});
