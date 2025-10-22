/**
 * Health Check System
 * Provides monitoring and health status for all components
 */

export interface HealthStatus {
  status: 'healthy' | 'unhealthy' | 'degraded';
  timestamp: Date;
  checks: Record<string, ComponentHealth>;
  uptime: number;
  version: string;
}

export interface ComponentHealth {
  status: 'healthy' | 'unhealthy' | 'degraded';
  message?: string;
  responseTime?: number;
  lastChecked: Date;
  metadata?: Record<string, any>;
}

export interface HealthCheckConfig {
  checkInterval: number;     // How often to run checks (ms)
  timeout: number;          // Timeout for individual checks (ms)
  criticalChecks: string[]; // Checks that must pass for healthy status
}

export class HealthChecker {
  private checks: Map<string, () => Promise<ComponentHealth>> = new Map();
  private config: HealthCheckConfig;
  private startTime: number;
  private intervalId?: NodeJS.Timeout;

  constructor(config: HealthCheckConfig) {
    this.config = config;
    this.startTime = Date.now();
  }

  /**
   * Register a health check function
   */
  registerCheck(
    name: string, 
    checkFn: () => Promise<ComponentHealth>
  ): void {
    this.checks.set(name, checkFn);
  }

  /**
   * Start periodic health checking
   */
  start(): void {
    this.intervalId = setInterval(async () => {
      await this.runChecks();
    }, this.config.checkInterval);
  }

  /**
   * Stop periodic health checking
   */
  stop(): void {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = undefined;
    }
  }

  /**
   * Get current health status
   */
  async getHealthStatus(): Promise<HealthStatus> {
    const checks: Record<string, ComponentHealth> = {};
    
    // Run all checks
    for (const [name, checkFn] of this.checks) {
      try {
        checks[name] = await Promise.race([
          checkFn(),
          this.timeoutPromise()
        ]);
      } catch (error) {
        checks[name] = {
          status: 'unhealthy',
          message: error instanceof Error ? error.message : 'Unknown error',
          lastChecked: new Date()
        };
      }
    }

    // Determine overall status
    const status = this.determineOverallStatus(checks);

    return {
      status,
      timestamp: new Date(),
      checks,
      uptime: Date.now() - this.startTime,
      version: process.env.npm_package_version || '1.0.0'
    };
  }

  private async runChecks(): Promise<void> {
    try {
      const health = await this.getHealthStatus();
      
      if (health.status === 'unhealthy') {
        console.warn('Health check failed:', health);
      }
    } catch (error) {
      console.error('Health check error:', error);
    }
  }

  private timeoutPromise(): Promise<ComponentHealth> {
    return new Promise((_, reject) => {
      setTimeout(() => {
        reject(new Error('Health check timeout'));
      }, this.config.timeout);
    });
  }

  private determineOverallStatus(checks: Record<string, ComponentHealth>): 'healthy' | 'unhealthy' | 'degraded' {
    const criticalFailed = this.config.criticalChecks.some(
      name => checks[name]?.status === 'unhealthy'
    );

    if (criticalFailed) {
      return 'unhealthy';
    }

    const unhealthyCount = Object.values(checks).filter(
      check => check.status === 'unhealthy'
    ).length;

    const totalChecks = Object.keys(checks).length;
    const unhealthyRatio = unhealthyCount / totalChecks;

    if (unhealthyRatio > 0.5) {
      return 'unhealthy';
    } else if (unhealthyRatio > 0.2) {
      return 'degraded';
    }

    return 'healthy';
  }
}

/**
 * Built-in health checks
 */
export class BuiltInHealthChecks {
  /**
   * Kafka connectivity check
   */
  static async kafkaCheck(kafkaWrapper: any): Promise<ComponentHealth> {
    const startTime = Date.now();
    
    try {
      // Try to get metadata
      await kafkaWrapper.getMetadata();
      
      return {
        status: 'healthy',
        responseTime: Date.now() - startTime,
        lastChecked: new Date(),
        metadata: {
          brokers: kafkaWrapper.getBrokers?.() || 'unknown'
        }
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: error instanceof Error ? error.message : 'Kafka connection failed',
        responseTime: Date.now() - startTime,
        lastChecked: new Date()
      };
    }
  }

  /**
   * Cassandra connectivity check
   */
  static async cassandraCheck(cassandraLogger: any): Promise<ComponentHealth> {
    const startTime = Date.now();
    
    try {
      // Try to execute a simple query
      await cassandraLogger.execute('SELECT now() FROM system.local');
      
      return {
        status: 'healthy',
        responseTime: Date.now() - startTime,
        lastChecked: new Date(),
        metadata: {
          keyspace: cassandraLogger.getKeyspace?.() || 'unknown'
        }
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: error instanceof Error ? error.message : 'Cassandra connection failed',
        responseTime: Date.now() - startTime,
        lastChecked: new Date()
      };
    }
  }

  /**
   * Memory usage check
   */
  static async memoryCheck(): Promise<ComponentHealth> {
    const memUsage = process.memoryUsage();
    const heapUsedMB = memUsage.heapUsed / 1024 / 1024;
    const heapTotalMB = memUsage.heapTotal / 1024 / 1024;
    const usagePercent = (heapUsedMB / heapTotalMB) * 100;

    let status: 'healthy' | 'unhealthy' | 'degraded' = 'healthy';
    let message: string | undefined;

    if (usagePercent > 90) {
      status = 'unhealthy';
      message = 'Memory usage critical';
    } else if (usagePercent > 80) {
      status = 'degraded';
      message = 'Memory usage high';
    }

    return {
      status,
      message,
      lastChecked: new Date(),
      metadata: {
        heapUsedMB: Math.round(heapUsedMB),
        heapTotalMB: Math.round(heapTotalMB),
        usagePercent: Math.round(usagePercent)
      }
    };
  }
}
