// Main exports
export { MessagingClient } from './lib/request-reply/messaging-client.js';
export { KafkaWrapper } from './lib/kafka/kafka-wrapper.js';
export { CassandraLogger } from './lib/logging/cassandra-logger.js';

// Utilities
export { generateRequestId, isValidRequestId } from './lib/utils/request-id.js';
export {
  encryptPayload,
  decryptPayload,
  generateEncryptionKey,
} from './lib/crypto/encryption.js';

// Types
export type {
  KafkaConfig,
  CassandraConfig,
  EncryptionConfig,
  MessagingConfig,
  RequestMessage,
  ResponseMessage,
  EncryptedMessage,
  LogEntry,
  RequestHandler,
  HandlerRegistry,
} from './types/index.js';
