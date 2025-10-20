#!/usr/bin/env bun

import { randomBytes } from 'crypto';

// Generate 32 bytes (256 bits) for AES-256
const key = randomBytes(32).toString('hex');

console.log('Generated Encryption Key (64 hex characters):');
console.log(key);
console.log('');
console.log('Export this key:');
console.log(`export ENCRYPTION_KEY="${key}"`);
