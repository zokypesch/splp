// Main exports for SplpNet library
// This file provides easy access to all public APIs

// Re-export main classes
global using SplpNet.RequestReply;
global using SplpNet.Models;
global using SplpNet.Crypto;
global using SplpNet.Utils;
global using SplpNet.Kafka;
global using SplpNet.Logging;

namespace SplpNet
{
    /// <summary>
    /// Main entry point for SplpNet library
    /// Provides factory methods and utilities
    /// </summary>
    public static class SplpNetFactory
    {
        /// <summary>
        /// Creates a new MessagingClient instance
        /// </summary>
        /// <param name="config">Messaging configuration</param>
        /// <param name="logger">Optional logger</param>
        /// <returns>MessagingClient instance</returns>
        public static MessagingClient CreateMessagingClient(MessagingConfig config, Microsoft.Extensions.Logging.ILoggerFactory? loggerFactory = null)
        {
            return new MessagingClient(config, loggerFactory);
        }

        /// <summary>
        /// Generates a new encryption key for AES-256
        /// </summary>
        /// <returns>64-character hex string</returns>
        public static string GenerateEncryptionKey()
        {
            return EncryptionService.GenerateEncryptionKey();
        }

        /// <summary>
        /// Generates a new request ID
        /// </summary>
        /// <returns>UUID string</returns>
        public static string GenerateRequestId()
        {
            return RequestIdGenerator.GenerateRequestId();
        }
    }
}
