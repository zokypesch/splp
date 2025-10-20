import { randomUUID } from 'crypto';

/**
 * Generates a unique request ID using UUID v4
 * @returns A unique request ID string
 */
export function generateRequestId(): string {
  return randomUUID();
}

/**
 * Validates if a string is a valid UUID
 * @param requestId - The request ID to validate
 * @returns True if valid UUID, false otherwise
 */
export function isValidRequestId(requestId: string): boolean {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
  return uuidRegex.test(requestId);
}
