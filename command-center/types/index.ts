/**
 * Command Center Types
 * Defines schema registry, routing, and metadata logging
 */

export interface ServiceInfo {
  serviceName: string;
  version: string;
  description?: string;
  endpoint?: string;
  tags?: string[];
  createdAt: Date;
  updatedAt: Date;
}

export interface RouteConfig {
  routeId: string;
  sourcePublisher: string; // Publisher/worker name that sends to command center
  targetTopic: string; // Target topic to route to
  serviceInfo: ServiceInfo;
  enabled: boolean;
  createdAt: Date;
  updatedAt: Date;
}

export interface SchemaRegistry {
  routes: Map<string, RouteConfig>; // key: sourcePublisher
  services: Map<string, ServiceInfo>; // key: serviceName
}

export interface RouteMetadata {
  request_id: string;
  worker_name: string; // Publisher name
  timestamp: Date;
  source_topic: string; // Topic message came from
  target_topic: string; // Topic routed to
  route_id: string;
  message_type: 'request' | 'response';
  success: boolean;
  error?: string;
  processing_time_ms?: number;
}

export interface CommandCenterConfig {
  kafka: {
    brokers: string[];
    clientId: string;
    groupId: string;
  };
  cassandra: {
    contactPoints: string[];
    localDataCenter: string;
    keyspace: string;
  };
  encryption: {
    encryptionKey: string;
  };
  commandCenter: {
    inboxTopic: string; // Topic where publishers send to command center
    enableAutoRouting: boolean;
    defaultTimeout: number;
  };
}

export interface IncomingMessage {
  request_id: string;
  worker_name: string; // Publisher identifier
  data: string; // Encrypted payload
  iv: string;
  tag: string;
}

export interface RoutedMessage {
  request_id: string;
  worker_name: string;
  source_topic: string;
  data: string;
  iv: string;
  tag: string;
  replyTopic?: string;
}
