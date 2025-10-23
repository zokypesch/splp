/**
 * Kemensos - Result Aggregation Service
 * Receives verification results from all government agencies
 * Aggregates and makes final decision on social assistance eligibility
 */

import { KafkaWrapper } from '../../splp-bun/src/lib/kafka/kafka-wrapper.js';
import { decryptPayload } from '../../splp-bun/src/lib/crypto/encryption.js';
import { generateEncryptionKey } from '../../splp-bun/src/index.js';
import type { KafkaConfig, EncryptedMessage } from '../../splp-bun/src/types/index.js';

// Configuration
const kafkaConfig: KafkaConfig = {
  brokers: ['10.70.1.23:9092'],
  clientId: 'kemensos-aggregation',
  groupId: 'service-2-group',
};

const encryptionKey = process.env.ENCRYPTION_KEY || generateEncryptionKey();
console.log("using key: ", encryptionKey);

// Base interface for all verification results
interface BaseVerificationResult {
  registrationId: string;
  nik: string;
  fullName: string;
  dateOfBirth: string;
  address: string;
  assistanceType: string;
  requestedAmount: number;
  processedBy: string;
  verifiedAt: string;
  [key: string]: any;
}

// Dukcapil - Population Data
interface DukcapilResult extends BaseVerificationResult {
  nikStatus: 'valid' | 'invalid' | 'blocked';
  dataMatch: boolean;
  familyMembers: number;
  addressVerified: boolean;
  notes?: string;
}

// BPJS TK - Employment
interface BPJSTKResult extends BaseVerificationResult {
  employmentStatus: 'active' | 'inactive' | 'not_registered';
  employerName?: string;
  monthlySalary?: number;
  contributionMonths: number;
  lastContribution?: string;
  notes?: string;
}

// BPJS Kesehatan - Health
interface BPJSKesehatanResult extends BaseVerificationResult {
  membershipStatus: 'active' | 'inactive' | 'not_registered';
  membershipClass: '1' | '2' | '3' | 'PBI' | null;
  hasChronicIllness: boolean;
  chronicIllnessList?: string[];
  lastCheckup?: string;
  healthRiskLevel: 'low' | 'medium' | 'high';
  notes?: string;
}

// Bank Indonesia - Financial
interface BankIndonesiaResult extends BaseVerificationResult {
  hasBankAccount: boolean;
  numberOfAccounts: number;
  totalSavings: number;
  hasActiveLoans: boolean;
  loanAmount?: number;
  creditScore: number;
  financialStatus: 'eligible' | 'review_needed' | 'not_eligible';
  notes?: string;
}

type VerificationResult = DukcapilResult | BPJSTKResult | BPJSKesehatanResult | BankIndonesiaResult;

async function main() {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('🏛️  KEMENSOS - Agregasi Hasil Verifikasi');
  console.log('    Social Assistance Result Aggregation Service');
  console.log('═══════════════════════════════════════════════════════════\n');

  const kafka = new KafkaWrapper(kafkaConfig);
  await kafka.connectConsumer();

  console.log('✓ Kemensos Aggregation terhubung ke Kafka');
  console.log('✓ Listening on topic: service-2-topic');
  console.log('✓ Akan menerima hasil verifikasi dari SEMUA instansi:');
  console.log('  - Dukcapil (Data Kependudukan)');
  console.log('  - BPJS Ketenagakerjaan (Status Pekerjaan)');
  console.log('  - BPJS Kesehatan (Status Kesehatan)');
  console.log('  - Bank Indonesia (Data Finansial)');
  console.log('');
  console.log('Expected: 4 hasil verifikasi untuk setiap pengajuan\n');

  // Subscribe to service-2-topic (Command Center routes here)
  await kafka.subscribe(['service-2-topic'], async ({ topic, message }) => {
    const startTime = Date.now();

    try {
      if (!message.value) return;

      const messageValue = message.value.toString();
      console.log('═'.repeat(60));
      console.log('📬 FINAL MESSAGE RECEIVED');
      console.log('═'.repeat(60));

      // Parse and decrypt
      const encryptedMsg: EncryptedMessage = JSON.parse(messageValue);
      const { requestId, payload } = decryptPayload<VerificationResult>(
        encryptedMsg,
        encryptionKey
      );

      console.log('');
      console.log('📋 Data Pemohon Bantuan:');
      console.log('  Request ID:', requestId);
      console.log('  Registration ID:', payload.registrationId);
      console.log('  NIK:', payload.nik);
      console.log('  Nama:', payload.fullName);
      console.log('  Tanggal Lahir:', payload.dateOfBirth);
      console.log('  Alamat:', payload.address);
      console.log('  Jenis Bantuan:', payload.assistanceType);
      console.log('  Jumlah Diminta: Rp', payload.requestedAmount.toLocaleString('id-ID'));
      console.log('');
      console.log('📥 Hasil Verifikasi dari:', payload.processedBy.toUpperCase());
      console.log('  Waktu Verifikasi:', new Date(payload.verifiedAt).toLocaleString('id-ID'));

      // Handle different verification result types
      if ('nikStatus' in payload) {
        // Dukcapil - Population Data
        console.log('  🏛️  Jenis: VERIFIKASI DATA KEPENDUDUKAN (DUKCAPIL)');
        console.log('  ✅ Status NIK:', payload.nikStatus.toUpperCase());
        console.log('  ✅ Kesesuaian Data:', payload.dataMatch ? 'COCOK' : 'TIDAK COCOK');
        console.log('  ✅ Jumlah Anggota Keluarga:', payload.familyMembers);
        console.log('  ✅ Alamat Terverifikasi:', payload.addressVerified ? 'YA' : 'TIDAK');
      } else if ('employmentStatus' in payload) {
        // BPJS TK - Employment
        console.log('  🏢 Jenis: VERIFIKASI KETENAGAKERJAAN (BPJS TK)');
        console.log('  ✅ Status Kepesertaan:', payload.employmentStatus.toUpperCase());
        if (payload.employerName) {
          console.log('  ✅ Nama Perusahaan:', payload.employerName);
        }
        if (payload.monthlySalary) {
          console.log('  ✅ Gaji Bulanan: Rp', payload.monthlySalary.toLocaleString('id-ID'));
        }
        console.log('  ✅ Lama Iuran:', payload.contributionMonths, 'bulan');
        if (payload.lastContribution) {
          console.log('  ✅ Iuran Terakhir:', payload.lastContribution);
        }
      } else if ('membershipStatus' in payload) {
        // BPJS Kesehatan - Health
        console.log('  🏥 Jenis: VERIFIKASI KESEHATAN (BPJS KESEHATAN)');
        console.log('  ✅ Status Kepesertaan:', payload.membershipStatus.toUpperCase());
        if (payload.membershipClass) {
          console.log('  ✅ Kelas Peserta:', payload.membershipClass);
        }
        console.log('  ✅ Penyakit Kronis:', payload.hasChronicIllness ? 'ADA' : 'TIDAK ADA');
        if (payload.chronicIllnessList && payload.chronicIllnessList.length > 0) {
          console.log('  ✅ Daftar Penyakit:', payload.chronicIllnessList.join(', '));
        }
        if (payload.lastCheckup) {
          console.log('  ✅ Pemeriksaan Terakhir:', payload.lastCheckup);
        }
        console.log('  ✅ Tingkat Risiko Kesehatan:', payload.healthRiskLevel.toUpperCase());
      } else if ('financialStatus' in payload) {
        // Bank Indonesia - Financial
        console.log('  🏦 Jenis: VERIFIKASI FINANSIAL (BANK INDONESIA)');
        console.log('  ✅ Memiliki Rekening Bank:', payload.hasBankAccount ? 'YA' : 'TIDAK');
        if (payload.hasBankAccount) {
          console.log('  ✅ Jumlah Rekening:', payload.numberOfAccounts);
          console.log('  ✅ Total Tabungan: Rp', payload.totalSavings.toLocaleString('id-ID'));
          console.log('  ✅ Pinjaman Aktif:', payload.hasActiveLoans ? 'ADA' : 'TIDAK ADA');
          if (payload.loanAmount) {
            console.log('  ✅ Jumlah Pinjaman: Rp', payload.loanAmount.toLocaleString('id-ID'));
          }
          console.log('  ✅ Skor Kredit:', payload.creditScore);
        }
        console.log('  ✅ Status Kelayakan Finansial:', payload.financialStatus.toUpperCase());
      }

      if (payload.notes) {
        console.log('  📋 Catatan:', payload.notes);
      }

      console.log('');

      // Final processing
      console.log('🔄 Memproses hasil verifikasi...');
      await new Promise((resolve) => setTimeout(resolve, 500));

      // Determine eligibility based on verification type
      let isEligible = false;
      let eligibilityReason = '';

      if ('nikStatus' in payload) {
        // Dukcapil check
        isEligible = payload.nikStatus === 'valid' && payload.dataMatch && payload.addressVerified;
        eligibilityReason = isEligible
          ? '✅ Data kependudukan valid dan terverifikasi'
          : '❌ Data kependudukan tidak memenuhi syarat';
      } else if ('employmentStatus' in payload) {
        // BPJS TK check - unemployed or inactive eligible
        isEligible = payload.employmentStatus === 'inactive' || payload.employmentStatus === 'not_registered';
        eligibilityReason = isEligible
          ? '✅ Status pekerjaan memenuhi syarat (tidak bekerja/tidak aktif)'
          : '❌ Memiliki pekerjaan aktif dengan gaji tetap';
      } else if ('membershipStatus' in payload) {
        // BPJS Kesehatan check
        isEligible = true; // Health status doesn't disqualify, but may affect priority
        eligibilityReason = payload.hasChronicIllness
          ? '⚠️  Memiliki penyakit kronis - prioritas tinggi'
          : '✅ Kondisi kesehatan memenuhi syarat';
      } else if ('financialStatus' in payload) {
        // Bank Indonesia check
        isEligible = payload.financialStatus === 'eligible';
        eligibilityReason = isEligible
          ? '✅ Status finansial memenuhi syarat'
          : payload.financialStatus === 'review_needed'
          ? '⚠️  Status finansial memerlukan peninjauan'
          : '❌ Status finansial tidak memenuhi syarat';
      }

      console.log('');
      console.log('📊 HASIL EVALUASI:');
      console.log('  ' + eligibilityReason);
      console.log('');

      const duration = Date.now() - startTime;
      console.log(`⏱️  Waktu pemrosesan: ${duration}ms`);
      console.log('');
      console.log('🎉 VERIFIKASI SELESAI!');
      console.log(`   Kemensos → CC → ${payload.processedBy.toUpperCase()} → CC → Kemensos (Agregasi)`);
      console.log('═'.repeat(60));
      console.log('');

    } catch (error) {
      console.error('❌ Error processing message:', error);
    }
  });

  console.log('Kemensos Aggregation menunggu hasil verifikasi...');
  console.log('Press Ctrl+C to exit\n');

  // Graceful shutdown
  process.on('SIGINT', async () => {
    console.log('\n\nShutting down Kemensos Aggregation...');
    await kafka.disconnect();
    process.exit(0);
  });
}

main().catch((error) => {
  console.error('Kemensos Aggregation error:', error);
  process.exit(1);
});
