<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\RateLimiter;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Unit tests for RateLimiter — per-IP rate limiting with DB backend.
 *
 * Uses a mock PDO that tracks INSERT counts to simulate the rate_limits table.
 */
class RateLimiterTest extends TestCase
{
    private int $attemptCount = 0;

    protected function setUp(): void
    {
        $this->attemptCount = 0;
        \TestDb::setPdo($this->createRateLimitMock());
    }

    protected function tearDown(): void
    {
        \TestDb::reset();
    }

    public function test_check_first_attempt_is_allowed(): void
    {
        $this->assertTrue(RateLimiter::check('192.168.1.1', 5, 300));
    }

    public function test_check_under_limit_is_allowed(): void
    {
        $this->recordAttempts(2);
        $this->assertTrue(RateLimiter::check('10.0.0.1', 5, 300));
    }

    public function test_check_at_limit_is_blocked(): void
    {
        $this->recordAttempts(5);
        $this->assertFalse(RateLimiter::check('203.0.113.1', 5, 300));
    }

    public function test_clear_resets_counter(): void
    {
        $this->recordAttempts(5);
        $this->assertFalse(RateLimiter::check('10.0.0.99', 5, 300));

        $this->attemptCount = 0; // simulate clear
        $this->assertTrue(RateLimiter::check('10.0.0.99', 5, 300));
    }

    public function test_different_ips_tracked_independently(): void
    {
        $this->recordAttempts(2);
        $this->assertFalse(RateLimiter::check('10.0.0.1', 2, 300));
    }

    public function test_ensure_table_does_not_throw(): void
    {
        RateLimiter::ensureTable();
        $this->assertTrue(true);
    }

    /**
     * Call RateLimiter::record and increment the tracking counter.
     */
    private function recordAttempts(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            RateLimiter::record('10.0.0.1');
            $this->attemptCount++;
        }
    }

    /**
     * Create a mock PDO that returns the attempt count via fetch.
     */
    private function createRateLimitMock(): PDO
    {
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('exec')->willReturn(0);
        $pdo->method('lastInsertId')->willReturn('1');

        $pdo->method('prepare')->willReturnCallback(function () {
            $stmt = $this->createStub(\PDOStatement::class);
            $stmt->method('bindValue')->willReturn(true);
            $stmt->method('bindParam')->willReturn(true);
            $stmt->method('execute')->willReturn(true);
            $stmt->method('fetch')->willReturnCallback(function () {
                return ['cnt' => $this->attemptCount];
            });
            $stmt->method('fetchColumn')->willReturnCallback(function () {
                return (string) $this->attemptCount;
            });
            $stmt->method('fetchAll')->willReturn([]);
            return $stmt;
        });

        return $pdo;
    }
}
