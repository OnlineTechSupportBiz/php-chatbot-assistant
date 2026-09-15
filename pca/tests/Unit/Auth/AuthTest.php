<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Auth;
use App\Auth\Session;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Auth — authorization / role-checking helper.
 *
 * Covers: requireRole, requireAdmin, requirePermission (admin bypass,
 * widget permissions), requireSameAdmin.
 *
 * Note: requireAuth() and deny() call exit() — they can't be tested
 * directly without process isolation. We test the non-exit paths and
 * the permission-checking logic that doesn't terminate.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('test-auth-' . uniqid());
        @session_start();
    }

    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    // ── requireRole() ───────────────────────────────────────────────────────

    public function test_requireRole_with_valid_role_does_not_throw(): void
    {
        $user = ['role' => 'user'];
        // This should not call exit() — if it does, the test fails
        Auth::requireRole($user, ['user', 'admin']);
        $this->assertTrue(true);
    }

    public function test_requireRole_with_admin_role_allows_admin_access(): void
    {
        $user = ['role' => 'admin'];
        Auth::requireRole($user, ['admin']);
        $this->assertTrue(true);
    }

    // ── requirePermission() ─────────────────────────────────────────────────

    public function test_requirePermission_admin_bypasses(): void
    {
        $user = ['id' => 1, 'role' => 'admin'];
        // Should return without checking UserPermission
        Auth::requirePermission($user, 'manage_chatbots');
        $this->assertTrue(true);
    }

    public function test_requirePermission_non_widget_bypasses_for_all(): void
    {
        // Per code: page-level permissions (non-widget_*) are no longer enforced
        $user = ['id' => 1, 'role' => 'user'];
        Auth::requirePermission($user, 'access_dashboard');
        $this->assertTrue(true);
    }

    // ── requireSameAdmin() ──────────────────────────────────────────────────

    public function test_requireSameAdmin_admin_bypasses(): void
    {
        $user = ['id' => 1, 'admin_id' => 5, 'role' => 'admin'];
        $resource = ['admin_id' => 99]; // Different admin, but admin role bypasses
        Auth::requireSameAdmin($user, $resource);
        $this->assertTrue(true);
    }

    public function test_requireSameAdmin_matching_admin_allows(): void
    {
        $user = ['id' => 1, 'admin_id' => 5, 'role' => 'user'];
        $resource = ['admin_id' => 5];
        Auth::requireSameAdmin($user, $resource);
        $this->assertTrue(true);
    }

    // ── requireAuth() ───────────────────────────────────────────────────────

    public function test_requireAuth_returns_user_array_when_authenticated(): void
    {
        Session::login([
            'id' => 1, 'admin_id' => 2, 'role' => 'user',
            'name' => 'Test', 'email' => 'test@test.com',
            'brand_name' => 'My Brand', 'company_name' => 'My Co',
        ]);

        $_SERVER['REQUEST_URI'] = '/dashboard';

        $user = Auth::requireAuth();

        $this->assertSame(1, $user['id']);
        $this->assertSame(2, $user['admin_id']);
        $this->assertSame('user', $user['role']);
        $this->assertSame('Test', $user['name']);
        $this->assertSame('test@test.com', $user['email']);
        $this->assertSame('My Brand', $user['brand_name']);
        $this->assertSame('My Co', $user['company_name']);
    }
}
