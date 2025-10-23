/**
 * Bank Indonesia
 * Verifies financial status and banking information
 * Checks account ownership, savings balance, and loan history
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { encryptPayload, decryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig, EncryptedMessage } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['10.70.1.23:9092'], // Match with publisher configuration
  clientId: 'bank-indonesia-service',
  groupId: 'service-1c-group', // UNIQUE GROUP
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

interface BankIndonesiaVerificationResult {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
  processedBy: string;
  hasBankAccount: boolean;
  numberOfAccounts: number;
  totalSavings: number;
  hasActiveLoans: boolean;
  loanAmount?: number;
  creditScore: number;
  financialStatus: 'eligible' | 'review_needed' | 'not_eligible';
  verifiedAt: string;
  notes?: string;
}

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏦 BANK INDONESIA');
  console.log('    Financial Status & Banking Information Verification');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectProducer();
  await kafka.connectConsumer();

  console.log('✓ Bank Indonesia terhubung ke Kafka');
  console.log('✓ Listening on topic: service-1-topic (group: service-1c-group)');
  console.log('✓ Siap memverifikasi data finansial\n');

  // Subscribe to service-1-topic (SAME TOPIC as other Service 1 variants)
  await kafka.subscribe(['service-1-topic'], async ({ topic, message }) => {
    const startTime = Date.now();

    try {
      if (!message.value) return;

      const messageValue = message.value.toString();
      console.log('─'.repeat(60));
      console.log('📥 [BANK INDONESIA] Menerima data dari Command Center');

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
      console.log('  Bantuan Diminta: Rp', payload.requestedAmount.toLocaleString('id-ID'));
      console.log('');

      // Verify financial data
      console.log('🔄 Memverifikasi data finansial...');
      await new Promise((resolve) => setTimeout(resolve, 900)); // Simulate verification

      // Simulate financial check
      const hasBankAccount = Math.random() > 0.1; // 90% have bank account
      const numberOfAccounts = hasBankAccount ? Math.floor(Math.random() * 3) + 1 : 0;
      const totalSavings = hasBankAccount
        ? Math.floor(Math.random() * 15000000) // Rp 0 - 15 juta
        : 0;
      const hasActiveLoans = hasBankAccount && Math.random() > 0.7; // 30% have loans
      const loanAmount = hasActiveLoans ? Math.floor(Math.random() * 20000000) + 5000000 : undefined;

      // Calculate credit score (300-850)
      let creditScore = 650;
      if (totalSavings > 10000000) creditScore += 100;
      else if (totalSavings > 5000000) creditScore += 50;
      if (hasActiveLoans) creditScore -= 50;
      if (!hasBankAccount) creditScore = 500;

      // Determine financial eligibility
      let financialStatus: 'eligible' | 'review_needed' | 'not_eligible';
      if (totalSavings > 5000000) {
        financialStatus = 'not_eligible'; // Too much savings
      } else if (totalSavings > 2000000 || hasActiveLoans) {
        financialStatus = 'review_needed'; // Moderate savings or has loans
      } else {
        financialStatus = 'eligible'; // Low savings, eligible
      }

      const processedData: BankIndonesiaVerificationResult = {
        ...payload,
        processedBy: 'bank-indonesia',
        hasBankAccount: hasBankAccount,
        numberOfAccounts: numberOfAccounts,
        totalSavings: totalSavings,
        hasActiveLoans: hasActiveLoans,
        loanAmount: loanAmount,
        creditScore: creditScore,
        financialStatus: financialStatus,
        verifiedAt: new Date().toISOString(),
        notes:
          financialStatus === 'eligible'
            ? 'Status finansial memenuhi syarat bantuan sosial'
            : financialStatus === 'review_needed'
            ? 'Status finansial memerlukan peninjauan lebih lanjut'
            : 'Tabungan melebihi batas kelayakan penerima bantuan',
      };

      console.log('  ✅ Memiliki Rekening Bank:', hasBankAccount ? 'YA' : 'TIDAK');
      if (hasBankAccount) {
        console.log('  ✅ Jumlah Rekening:', numberOfAccounts);
        console.log('  ✅ Total Tabungan: Rp', totalSavings.toLocaleString('id-ID'));
        console.log('  ✅ Pinjaman Aktif:', hasActiveLoans ? 'ADA' : 'TIDAK ADA');
        if (hasActiveLoans && loanAmount) {
          console.log('  ✅ Jumlah Pinjaman: Rp', loanAmount.toLocaleString('id-ID'));
        }
        console.log('  ✅ Skor Kredit:', creditScore);
      }
      console.log('  ✅ Status Kelayakan Finansial:', financialStatus.toUpperCase());
      console.log('  📋 Catatan:', processedData.notes);
      console.log('  🏢 Diproses oleh: BANK INDONESIA');
      console.log('');

      // Encrypt processed data
      const encrypted = encryptPayload(processedData, encryptionKey, requestId);

      // Send back to Command Center for routing to service_2
      const outgoingMessage = {
        request_id: requestId,
        worker_name: 'service-1c-publisher',
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

  console.log('Bank Indonesia menunggu data verifikasi...');
  console.log('Press Ctrl+C to exit\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down Bank Indonesia...');
    await kafka.disconnect();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error('Bank Indonesia error:', error);
  process.exit(1);
});
