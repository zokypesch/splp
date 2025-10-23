# Encryption Compatibility Fix

## Problem
The .NET Service1 was throwing an error when trying to decrypt messages from the Bun publisher:

```
System.ArgumentException: The specified nonce is not a valid size for this algorithm. (Parameter 'nonce')
```

## Root Cause
There was a mismatch in the IV/nonce size between the TypeScript and .NET implementations:

- **TypeScript (original)**: 16 bytes (128 bits)
- **.NET AesGcm requirement**: 12 bytes (96 bits)

The .NET `AesGcm` class only accepts nonce sizes of 12 bytes, which is the standard for AES-GCM encryption.

## Solution
Changed the IV length in both implementations to use the standard 12-byte nonce:

### Files Modified

1. **`/splp-bun/src/lib/crypto/encryption.ts`** (line 5)
   - Changed: `const IV_LENGTH = 16;`
   - To: `const IV_LENGTH = 12; // Changed from 16 to 12 for .NET AesGcm compatibility (96 bits)`

2. **`/splp-net/Crypto/EncryptionService.cs`** (line 15)
   - Changed: `private const int IvLength = 16;  // 16 bytes IV`
   - To: `private const int IvLength = 12;  // 12 bytes IV (required by .NET AesGcm - 96 bits)`

## Verification
The encryption/decryption now works correctly:
- ✅ IV: 12 bytes (24 hex characters)
- ✅ Tag: 16 bytes (32 hex characters)
- ✅ Key: 32 bytes (64 hex characters)

## Impact
- Messages encrypted by Bun/TypeScript services can now be decrypted by .NET services
- Messages encrypted by .NET services can now be decrypted by Bun/TypeScript services
- Full interoperability between example-bun and example-net implementations

## Testing
Run the test script to verify:
```bash
cd /mnt/d/project/splp
bun run test-encryption-compatibility.ts
```

Expected output: "✅ IV is now 12 bytes - compatible with .NET AesGcm!"

## Note
12 bytes (96 bits) is the standard and recommended nonce size for AES-GCM encryption across all implementations.
