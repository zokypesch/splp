import { createCipheriv, createDecipheriv, randomBytes } from 'crypto';
import type { EncryptedMessage } from '../../types/index.js';

const ALGORITHM = 'aes-256-gcm';
const IV_LENGTH = 12; // Changed from 16 to 12 for .NET AesGcm compatibility (96 bits)
const TAG_LENGTH = 16;

/**
 * Encrypts a JSON payload using AES-256-GCM
 * @param payload - The data to encrypt
 * @param encryptionKey - 32-byte hex string for AES-256
 * @param requestId - The request ID (not encrypted, used for correlation)
 * @returns Encrypted message with IV and auth tag
 */
export function encryptPayload<T>(
  payload: T,
  encryptionKey: string,
  requestId: string
): EncryptedMessage {
  // Convert hex key to buffer
  const key = Buffer.from(encryptionKey, 'hex');

  if (key.length !== 32) {
    throw new Error('Encryption key must be 32 bytes (64 hex characters) for AES-256');
  }

  // Generate random IV
  const iv = randomBytes(IV_LENGTH);

  // Convert payload to JSON string
  const payloadString = JSON.stringify(payload);

  // Create cipher
  const cipher = createCipheriv(ALGORITHM, key, iv);

  // Encrypt the data
  let encrypted = cipher.update(payloadString, 'utf8', 'hex');
  encrypted += cipher.final('hex');

  // Get authentication tag
  const tag = cipher.getAuthTag();

  return {
    request_id: requestId, // Not encrypted for tracing
    data: encrypted,
    iv: iv.toString('hex'),
    tag: tag.toString('hex'),
  };
}

/**
 * Decrypts an encrypted message using AES-256-GCM
 * @param encryptedMessage - The encrypted message
 * @param encryptionKey - 32-byte hex string for AES-256
 * @returns Decrypted payload
 */
export function decryptPayload<T>(
  encryptedMessage: EncryptedMessage,
  encryptionKey: string
): { requestId: string; payload: T } {
  // Convert hex key to buffer
  const key = Buffer.from(encryptionKey, 'hex');

  if (key.length !== 32) {
    throw new Error('Encryption key must be 32 bytes (64 hex characters) for AES-256');
  }

  // Convert IV and tag from hex
  const iv = Buffer.from(encryptedMessage.iv, 'hex');
  const tag = Buffer.from(encryptedMessage.tag, 'hex');

  // Create decipher
  const decipher = createDecipheriv(ALGORITHM, key, iv);
  decipher.setAuthTag(tag);

  // Decrypt the data
  let decrypted = decipher.update(encryptedMessage.data, 'hex', 'utf8');
  decrypted += decipher.final('utf8');

  // Parse JSON payload
  const payload = JSON.parse(decrypted) as T;

  return {
    requestId: encryptedMessage.request_id,
    payload,
  };
}

/**
 * Generates a random encryption key for AES-256
 * @returns 32-byte hex string
 */
export function generateEncryptionKey(): string {
  return randomBytes(32).toString('hex');
}
