<?php

declare(strict_types=1);

namespace Splp\Messaging\Tests;

use PHPUnit\Framework\TestCase;
use Splp\Messaging\Core\MessagingClient;
use Splp\Messaging\Core\Crypto\EncryptionService;
use Splp\Messaging\Core\Utils\RequestIdGenerator;
use Splp\Messaging\Core\Utils\CircuitBreaker;
use Splp\Messaging\Core\Utils\RetryManager;
use Splp\Messaging\Exceptions\EncryptionException;
use Splp\Messaging\Exceptions\MessagingException;

/**
 * Unit Tests for SPLP Messaging Library
 */
class MessagingClientTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'kafka' => [
                'brokers' => ['localhost:9092'],
                'clientId' => 'test-client',
                'groupId' => 'test-group'
            ],
            'cassandra' => [
                'contactPoints' => ['localhost'],
                'localDataCenter' => 'datacenter1',
                'keyspace' => 'test_keyspace'
            ],
            'encryption' => [
                'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
            ]
        ];
    }

    public function testMessagingClientCreation(): void
    {
        $client = new MessagingClient($this->config);
        $this->assertInstanceOf(MessagingClient::class, $client);
    }

    public function testInvalidConfigThrowsException(): void
    {
        $this->expectException(MessagingException::class);
        new MessagingClient([]);
    }
}

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $encryption;

    protected function setUp(): void
    {
        $this->encryption = new EncryptionService([
            'encryptionKey' => '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'
        ]);
    }

    public function testEncryptDecryptPayload(): void
    {
        $payload = ['test' => 'data', 'number' => 123];
        $requestId = RequestIdGenerator::generate();

        $encrypted = $this->encryption->encryptPayload($payload, $requestId);
        $this->assertArrayHasKey('request_id', $encrypted);
        $this->assertArrayHasKey('data', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);

        $decrypted = $this->encryption->decryptPayload($encrypted);
        $this->assertEquals($requestId, $decrypted['requestId']);
        $this->assertEquals($payload, $decrypted['payload']);
    }

    public function testInvalidEncryptionKey(): void
    {
        $this->expectException(EncryptionException::class);
        new EncryptionService(['encryptionKey' => 'invalid-key']);
    }

    public function testGenerateEncryptionKey(): void
    {
        $key = EncryptionService::generateEncryptionKey();
        $this->assertEquals(64, strlen($key));
        $this->assertTrue(ctype_xdigit($key));
    }
}

class RequestIdGeneratorTest extends TestCase
{
    public function testGenerateRequestId(): void
    {
        $requestId = RequestIdGenerator::generate();
        $this->assertIsString($requestId);
        $this->assertEquals(36, strlen($requestId));
        $this->assertTrue(RequestIdGenerator::isValid($requestId));
    }

    public function testIsValidRequestId(): void
    {
        $validId = RequestIdGenerator::generate();
        $this->assertTrue(RequestIdGenerator::isValid($validId));

        $invalidId = 'invalid-uuid';
        $this->assertFalse(RequestIdGenerator::isValid($invalidId));
    }
}

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->circuitBreaker = new CircuitBreaker([
            'failureThreshold' => 3,
            'recoveryTimeout' => 1000,
            'monitoringPeriod' => 5000
        ]);
    }

    public function testCircuitBreakerClosedState(): void
    {
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testCircuitBreakerSuccess(): void
    {
        $result = $this->circuitBreaker->execute(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
    }

    public function testCircuitBreakerFailure(): void
    {
        $this->expectException(\Exception::class);

        $this->circuitBreaker->execute(function () {
            throw new \Exception('Test failure');
        });

        $this->assertEquals(1, $this->circuitBreaker->getFailureCount());
    }

    public function testCircuitBreakerOpensAfterThreshold(): void
    {
        // Cause failures to reach threshold
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \Exception('Test failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('OPEN', $this->circuitBreaker->getState());
        $this->assertEquals(3, $this->circuitBreaker->getFailureCount());
    }
}

class RetryManagerTest extends TestCase
{
    private RetryManager $retryManager;

    protected function setUp(): void
    {
        $this->retryManager = new RetryManager([
            'maxAttempts' => 3,
            'baseDelay' => 100,
            'maxDelay' => 1000,
            'backoffMultiplier' => 2,
            'jitter' => false
        ]);
    }

    public function testRetryManagerSuccess(): void
    {
        $result = $this->retryManager->execute(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    public function testRetryManagerFailure(): void
    {
        $this->expectException(\Exception::class);

        $this->retryManager->execute(function () {
            throw new \Exception('Test failure');
        });
    }

    public function testRetryManagerWithRetryableError(): void
    {
        $attempts = 0;
        
        $this->expectException(\Exception::class);

        $this->retryManager->execute(function () use (&$attempts) {
            $attempts++;
            throw new \Exception('Retryable error');
        }, function ($error) {
            return true; // Always retryable
        });

        $this->assertEquals(3, $attempts); // Should retry 3 times
    }

    public function testRetryManagerWithNonRetryableError(): void
    {
        $attempts = 0;
        
        $this->expectException(\Exception::class);

        $this->retryManager->execute(function () use (&$attempts) {
            $attempts++;
            throw new \Exception('Non-retryable error');
        }, function ($error) {
            return false; // Never retryable
        });

        $this->assertEquals(1, $attempts); // Should not retry
    }
}

/**
 * Integration Tests
 */
class IntegrationTest extends TestCase
{
    public function testEndToEndMessaging(): void
    {
        // This would test the complete flow with real Kafka and Cassandra
        // For now, we'll skip this test in CI/CD
        $this->markTestSkipped('Integration test requires running Kafka and Cassandra');
    }
}
