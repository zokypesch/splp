/**
 * Retry Mechanism with Exponential Backoff
 * Provides automatic retry for transient failures
 */

export interface RetryConfig {
  maxAttempts: number;        // Maximum number of retry attempts
  baseDelay: number;         // Base delay in milliseconds
  maxDelay: number;          // Maximum delay in milliseconds
  backoffMultiplier: number; // Exponential backoff multiplier
  jitter: boolean;          // Add random jitter to prevent thundering herd
}

export class RetryManager {
  private config: RetryConfig;

  constructor(config: RetryConfig) {
    this.config = config;
  }

  /**
   * Execute function with retry logic
   */
  async execute<T>(
    fn: () => Promise<T>,
    isRetryableError?: (error: Error) => boolean
  ): Promise<T> {
    let lastError: Error;
    
    for (let attempt = 1; attempt <= this.config.maxAttempts; attempt++) {
      try {
        return await fn();
      } catch (error) {
        lastError = error as Error;
        
        // Check if error is retryable
        if (isRetryableError && !isRetryableError(lastError)) {
          throw lastError;
        }
        
        // Don't retry on last attempt
        if (attempt === this.config.maxAttempts) {
          throw lastError;
        }
        
        // Calculate delay with exponential backoff
        const delay = this.calculateDelay(attempt);
        console.log(`Attempt ${attempt} failed, retrying in ${delay}ms...`);
        
        await this.sleep(delay);
      }
    }
    
    throw lastError!;
  }

  private calculateDelay(attempt: number): number {
    const exponentialDelay = this.config.baseDelay * 
      Math.pow(this.config.backoffMultiplier, attempt - 1);
    
    const cappedDelay = Math.min(exponentialDelay, this.config.maxDelay);
    
    if (this.config.jitter) {
      // Add random jitter (±25%)
      const jitterRange = cappedDelay * 0.25;
      const jitter = (Math.random() - 0.5) * 2 * jitterRange;
      return Math.max(0, cappedDelay + jitter);
    }
    
    return cappedDelay;
  }

  private sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

/**
 * Default retry configurations for common scenarios
 */
export const RetryConfigs = {
  // Fast retry for transient network issues
  FAST: {
    maxAttempts: 3,
    baseDelay: 100,
    maxDelay: 1000,
    backoffMultiplier: 2,
    jitter: true
  },
  
  // Standard retry for general operations
  STANDARD: {
    maxAttempts: 5,
    baseDelay: 500,
    maxDelay: 5000,
    backoffMultiplier: 2,
    jitter: true
  },
  
  // Slow retry for heavy operations
  SLOW: {
    maxAttempts: 3,
    baseDelay: 2000,
    maxDelay: 10000,
    backoffMultiplier: 2,
    jitter: true
  }
} as const;
