<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\Session;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Session — session management helper.
 *
 * Covers: start, destroy, login, logout, isAuthenticated,
 * userId, adminId, userRole, CSRF tokens, flash messages,
 * get/set helpers.
 *
 * Note: These tests manipulate $_SESSION directly and
 * use output buffering to suppress header-related output.
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Destroy any lingering session state
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('test-' . uniqid());
        @session_start();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    // ── start() ─────────────────────────────────────────────────────────────

    public function test_start_does_not_crash(): void
    {
        // Just verify it doesn't throw when session is already active
        Session::start(['lifetime' => 120]);
        $this->assertTrue(true); // Reached without error
    }

    // ── login / logout / isAuthenticated ────────────────────────────────────

    public function test_login_sets_session_variables(): void
    {
        $user = [
            'id'           => 42,
            'admin_id'     => 7,
            'role'         => 'user',
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'brand_name'   => 'Acme Corp',
            'company_name' => 'Acme Corp',
            'timezone'     => 'America/Denver',
        ];

        Session::login($user);

        $this->assertSame(42, $_SESSION['user_id']);
        $this->assertSame(7, $_SESSION['admin_id']);
        $this->assertSame('user', $_SESSION['user_role']);
        $this->assertSame('Alice', $_SESSION['user_name']);
        $this->assertSame('alice@example.com', $_SESSION['user_email']);
        $this->assertSame('Acme Corp', $_SESSION['brand_name']);
        $this->assertSame('America/Denver', $_SESSION['timezone']);
        $this->assertArrayHasKey('_last_regenerated', $_SESSION);
        $this->assertGreaterThan(time() - 5, $_SESSION['_last_regenerated']);
    }

    public function test_login_without_brand_name_uses_default(): void
    {
        Session::login([
            'id'       => 1,
            'admin_id' => 1,
            'role'     => 'user',
            'name'     => 'Test',
            'email'    => 'test@test.com',
        ]);

        $this->assertSame('Chatbot Assistant', $_SESSION['brand_name']);
    }

    public function test_isAuthenticated_returns_true_after_login(): void
    {
        Session::login([
            'id' => 1, 'admin_id' => 1, 'role' => 'user',
            'name' => 'T', 'email' => 't@t.com',
        ]);
        $this->assertTrue(Session::isAuthenticated());
    }

    public function test_isAuthenticated_returns_false_by_default(): void
    {
        // Clear session
        $_SESSION = [];
        $this->assertFalse(Session::isAuthenticated());
    }

    public function test_logout_clears_session(): void
    {
        Session::login([
            'id' => 1, 'admin_id' => 1, 'role' => 'user',
            'name' => 'T', 'email' => 't@t.com',
        ]);
        $this->assertTrue(Session::isAuthenticated());

        Session::logout();
        $this->assertFalse(Session::isAuthenticated());
    }

    // ── userId / adminId / userRole ─────────────────────────────────────────

    public function test_userId_returns_null_when_not_logged_in(): void
    {
        $_SESSION = [];
        $this->assertNull(Session::userId());
    }

    public function test_userId_returns_id_after_login(): void
    {
        Session::login([
            'id' => 99, 'admin_id' => 5, 'role' => 'admin',
            'name' => 'Admin', 'email' => 'admin@test.com',
        ]);
        $this->assertSame(99, Session::userId());
        $this->assertSame(5, Session::adminId());
        $this->assertSame('admin', Session::userRole());
    }

    // ── CSRF tokens ─────────────────────────────────────────────────────────

    public function test_csrfToken_generates_and_persists(): void
    {
        $token1 = Session::csrfToken();
        $this->assertIsString($token1);
        $this->assertGreaterThan(20, strlen($token1));

        // Second call should return the same token
        $token2 = Session::csrfToken();
        $this->assertSame($token1, $token2);
    }

    public function test_validateCsrf_valid_token(): void
    {
        $token = Session::csrfToken();
        $this->assertTrue(Session::validateCsrf($token));
    }

    public function test_validateCsrf_invalid_token(): void
    {
        $this->assertFalse(Session::validateCsrf('invalid-token'));
    }

    public function test_validateCsrf_empty_token(): void
    {
        $this->assertFalse(Session::validateCsrf(''));
    }

    // ── Flash messages ──────────────────────────────────────────────────────

    public function test_flash_sets_and_getFlash_retrieves(): void
    {
        Session::flash('success', 'Operation completed!');
        $this->assertSame('Operation completed!', Session::getFlash('success'));
    }

    public function test_getFlash_clears_after_read(): void
    {
        Session::flash('error', 'Something went wrong');
        Session::getFlash('error');
        $this->assertNull(Session::getFlash('error'));
    }

    public function test_getFlash_unknown_key_returns_null(): void
    {
        $this->assertNull(Session::getFlash('nonexistent'));
    }

    public function test_flash_array_message(): void
    {
        $messages = ['First error', 'Second error'];
        Session::flash('errors', $messages);
        $this->assertSame($messages, Session::getFlash('errors'));
    }

    // ── Generic get/set ─────────────────────────────────────────────────────

    public function test_set_and_get(): void
    {
        Session::set('custom_key', 'custom_value');
        $this->assertSame('custom_value', Session::get('custom_key'));
    }

    public function test_get_with_default(): void
    {
        $this->assertSame('default', Session::get('missing', 'default'));
    }

    public function test_get_returns_null_for_missing(): void
    {
        $this->assertNull(Session::get('missing'));
    }

    // ── Edge cases ──────────────────────────────────────────────────────────

    public function test_remember_me_sets_flag(): void
    {
        Session::login([
            'id' => 1, 'admin_id' => 1, 'role' => 'user',
            'name' => 'T', 'email' => 't@t.com',
        ], remember: true);

        $this->assertTrue($_SESSION['_remember_me']);
    }

    public function test_login_without_remember_does_not_set_flag(): void
    {
        Session::login([
            'id' => 1, 'admin_id' => 1, 'role' => 'user',
            'name' => 'T', 'email' => 't@t.com',
        ], remember: false);

        $this->assertArrayNotHasKey('_remember_me', $_SESSION);
    }

    // ── Periodic session ID rotation ─────────────────────────────────────────

    public function test_start_rotates_session_id_after_interval(): void
    {
        // End the setUp session so start() doesn't bail early
        session_write_close();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // Create a session with a stale rotation timestamp
        $oldId = 'pre-rotate-' . bin2hex(random_bytes(8));
        session_id($oldId);
        session_start();
        $_SESSION['_created'] = time() - 7200;
        $_SESSION['_last_regenerated'] = time() - 1800; // 30 min ago
        session_write_close();

        // start() should detect the stale timestamp and rotate
        Session::start(['lifetime' => 120, 'rotation_interval' => 1]);

        // Session ID must have changed
        $this->assertNotSame($oldId, session_id());
        // Timestamp must be recent
        $this->assertGreaterThan(time() - 5, $_SESSION['_last_regenerated']);
    }

    public function test_start_does_not_rotate_before_interval(): void
    {
        session_write_close();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // Create a session with a fresh rotation timestamp
        $sessionId = 'no-rotate-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();
        $_SESSION['_created'] = time() - 60;
        $_SESSION['_last_regenerated'] = time() - 10; // 10 seconds ago
        session_write_close();

        Session::start(['lifetime' => 120, 'rotation_interval' => 30]);

        // Session ID should stay the same
        $this->assertSame($sessionId, session_id());
    }
}
