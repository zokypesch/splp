using System;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using SplpNet.Models;

namespace SplpNet.Crypto;

/// <summary>
/// AES-256-GCM encryption service for payload encryption
/// </summary>
public static class EncryptionService
{
    private const int KeyLength = 32; // 32 bytes = 256 bits
    private const int IvLength = 12;  // 12 bytes IV (required by .NET AesGcm - 96 bits)
    private const int TagLength = 16; // 16 bytes tag (GCM default)

    /// <summary>
    /// Encrypts a JSON payload using AES-256-GCM
    /// </summary>
    public static EncryptedMessage EncryptPayload<T>(T payload, string encryptionKeyHex, string requestId)
    {
        // Convert hex key to bytes
        byte[] key = HexToBytes(encryptionKeyHex);
        if (key.Length != KeyLength)
            throw new ArgumentException("Encryption key must be 32 bytes (64 hex characters) for AES-256");

        // Generate random IV
        byte[] iv = new byte[IvLength];
        using (var rng = RandomNumberGenerator.Create())
        {
            rng.GetBytes(iv);
        }

        // Convert payload to JSON string
        string json = JsonSerializer.Serialize(payload);
        byte[] plaintext = Encoding.UTF8.GetBytes(json);

        // Output buffers
        byte[] ciphertext = new byte[plaintext.Length];
        byte[] tag = new byte[TagLength];

        using (var aesGcm = new AesGcm(key, TagLength))
        {
            aesGcm.Encrypt(iv, plaintext, ciphertext, tag);
        }

        return new EncryptedMessage
        {
            RequestId = requestId, // Not encrypted for tracing
            Data = BytesToHex(ciphertext),
            Iv = BytesToHex(iv),
            Tag = BytesToHex(tag)
        };
    }

    /// <summary>
    /// Decrypts an encrypted message using AES-256-GCM
    /// </summary>
    public static (string RequestId, T Payload) DecryptPayload<T>(EncryptedMessage encrypted, string encryptionKeyHex)
    {
        // Convert hex key to buffer
        byte[] key = HexToBytes(encryptionKeyHex);
        if (key.Length != KeyLength)
            throw new ArgumentException("Encryption key must be 32 bytes (64 hex characters) for AES-256");

        // Convert IV and tag from hex
        byte[] iv = HexToBytes(encrypted.Iv);
        byte[] tag = HexToBytes(encrypted.Tag);
        byte[] ciphertext = HexToBytes(encrypted.Data);

        byte[] plaintext = new byte[ciphertext.Length];

        using (var aesGcm = new AesGcm(key, TagLength))
        {
            aesGcm.Decrypt(iv, ciphertext, tag, plaintext);
        }

        // Parse JSON payload
        string json = Encoding.UTF8.GetString(plaintext);
        T payload = JsonSerializer.Deserialize<T>(json)!;

        return (encrypted.RequestId, payload);
    }

    /// <summary>
    /// Generates a random 32-byte encryption key (hex)
    /// </summary>
    public static string GenerateEncryptionKey()
    {
        byte[] key = new byte[KeyLength];
        using (var rng = RandomNumberGenerator.Create())
        {
            rng.GetBytes(key);
        }
        return BytesToHex(key);
    }

    /// <summary>
    /// Converts byte array to hex string (compatible with older .NET versions)
    /// </summary>
    private static string BytesToHex(byte[] bytes)
    {
        return BitConverter.ToString(bytes).Replace("-", "").ToLower();
    }

    /// <summary>
    /// Converts hex string to byte array (compatible with older .NET versions)
    /// </summary>
    private static byte[] HexToBytes(string hex)
    {
        var bytes = new byte[hex.Length / 2];
        for (int i = 0; i < bytes.Length; i++)
        {
            bytes[i] = Convert.ToByte(hex.Substring(i * 2, 2), 16);
        }
        return bytes;
    }
}
