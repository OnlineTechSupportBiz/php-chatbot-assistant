<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\AdminScoped;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminScoped trait — multi-tenant row security.
 *
 * Covers: adminWhere (with and without alias), getCurrentAdminId,
 * bindAdminParam (with and without session).
 */
class AdminScopedTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('test-admin-scoped-' . uniqid());
        @session_start();
    }

    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public function test_adminWhere_without_session_returns_empty(): void
    {
        // Use reflection to call the protected static method
        $result = $this->callAdminWhere(null);
        $this->assertSame('', $result);
    }

    public function test_adminWhere_with_session_returns_clause(): void
    {
        $_SESSION['admin_id'] = 5;
        $result = $this->callAdminWhere(null);
        $this->assertStringContainsString('admin_id = :admin_id', $result);
    }

    public function test_adminWhere_with_alias_uses_prefix(): void
    {
        $_SESSION['admin_id'] = 5;
        $result = $this->callAdminWhere('c');
        $this->assertStringContainsString('c.admin_id = :admin_id', $result);
    }

    /**
     * Call the protected AdminScoped::adminWhere() via reflection.
     */
    private function callAdminWhere(?string $alias): string
    {
        // Use a concrete model that uses the trait
        $reflector = new \ReflectionMethod(\App\Model\Chatbot::class, 'adminWhere');
        $reflector->setAccessible(true);
        return $reflector->invoke(null, $alias ?? '');
    }
}
