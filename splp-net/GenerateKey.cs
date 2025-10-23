using System;
using System.Security.Cryptography;

class Program
{
    static void Main(string[] args)
    {
        // Generate 32 random bytes for AES-256
        var keyBytes = new byte[32];
        using (var rng = RandomNumberGenerator.Create())
        {
            rng.GetBytes(keyBytes);
        }
        
        // Convert to hex string
        var key = BitConverter.ToString(keyBytes).Replace("-", "").ToLower();
        
        Console.WriteLine("=".PadRight(60, '='));
        Console.WriteLine("SplpNet Encryption Key Generator");
        Console.WriteLine("=".PadRight(60, '='));
        Console.WriteLine();
        Console.WriteLine($"Generated AES-256 Key: {key}");
        Console.WriteLine();
        Console.WriteLine("Set this as environment variable:");
        Console.WriteLine($"ENCRYPTION_KEY={key}");
        Console.WriteLine();
        Console.WriteLine("PowerShell:");
        Console.WriteLine($"$env:ENCRYPTION_KEY = \"{key}\"");
        Console.WriteLine();
        Console.WriteLine("CMD:");
        Console.WriteLine($"set ENCRYPTION_KEY={key}");
        Console.WriteLine();
        Console.WriteLine("⚠️  IMPORTANT: All services must use the same key!");
        Console.WriteLine("=".PadRight(60, '='));
    }
}
