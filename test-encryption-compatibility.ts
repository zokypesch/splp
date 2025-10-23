/**
 * Test encryption compatibility between TypeScript and .NET
 */
import { encryptPayload, decryptPayload, generateEncryptionKey } from './splp-bun/src/lib/crypto/encryption.js';

const testData = {
  registrationId: 'REG-001',
  nik: '3171234567890123',
  fullName: 'Test User',
  dateOfBirth: '1990-01-01',
  address: 'Jakarta',
  assistanceType: 'bansos',
  requestedAmount: 1000000,
};

// Use a fixed key for testing
const encryptionKey = generateEncryptionKey();
console.log('Encryption Key:', encryptionKey);
console.log('Key Length:', encryptionKey.length, 'hex chars =', encryptionKey.length / 2, 'bytes');

const requestId = 'test-001';

// Encrypt
console.log('\n--- Encrypting ---');
const encrypted = encryptPayload(testData, encryptionKey, requestId);
console.log('IV Length:', encrypted.iv.length, 'hex chars =', encrypted.iv.length / 2, 'bytes');
console.log('Tag Length:', encrypted.tag.length, 'hex chars =', encrypted.tag.length / 2, 'bytes');
console.log('Data Length:', encrypted.data.length, 'hex chars');
console.log('\nEncrypted Message:');
console.log(JSON.stringify(encrypted, null, 2));

// Decrypt
console.log('\n--- Decrypting ---');
const decrypted = decryptPayload(encrypted, encryptionKey);
console.log('Request ID:', decrypted.requestId);
console.log('Payload:', decrypted.payload);

// Verify
const match = JSON.stringify(testData) === JSON.stringify(decrypted.payload);
console.log('\n✓ Encryption/Decryption:', match ? 'SUCCESS' : 'FAILED');

if (match) {
  console.log('\n✅ IV is now 12 bytes - compatible with .NET AesGcm!');
} else {
  console.error('\n❌ Data mismatch!');
  process.exit(1);
}
