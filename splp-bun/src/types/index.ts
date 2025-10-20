export interface KafkaConfig {
  brokers: string[];
  clientId: string;
  groupId?: string;
}

export interface CassandraConfig {
  contactPoints: string[];
  localDataCenter: string;
  keyspace: string;
}

export interface EncryptionConfig {
  encryptionKey: string; // 32-byte hex string for AES-256
}

export interface MessagingConfig {
  kafka: KafkaConfig;
  cassandra: CassandraConfig;
  encryption: EncryptionConfig;
}

export interface RequestMessage<T = any> {
  request_id: string;
  payload: T;
  timestamp: number;
}

export interface ResponseMessage<T = any> {
  request_id: string;
  payload: T;
  timestamp: number;
  success: boolean;
  error?: string;
}

export interface EncryptedMessage {
  request_id: string; // Not encrypted
  data: string; // Encrypted payload
  iv: string; // Initialization vector for AES-GCM
  tag: string; // Authentication tag for AES-GCM
}

export interface LogEntry {
  request_id: string;
  timestamp: Date;
  type: 'request' | 'response';
  topic: string;
  payload: any;
  success?: boolean;
  error?: string;
  duration_ms?: number;
}

export type RequestHandler<TRequest = any, TResponse = any> = (
  requestId: string,
  payload: TRequest
) => Promise<TResponse>;

export interface HandlerRegistry {
  [topic: string]: RequestHandler;
}
