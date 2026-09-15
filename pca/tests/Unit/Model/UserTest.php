<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    use \PdoMocker;

    protected function setUp(): void
    {
        \TestDb::reset();
    }

    protected function tearDown(): void
    {
        \TestDb::reset();
    }

    public function test_findByEmail_returns_user(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'email' => 'test@example.com', 'name' => 'Test', 'admin_id' => 1, 'role' => 'user'],
        ]));

        $user = User::findByEmail('test@example.com');
        $this->assertIsArray($user);
        $this->assertSame(1, $user['id']);
    }

    public function test_findByEmail_returns_null_when_not_found(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertNull(User::findByEmail('missing@example.com'));
    }

    public function test_findByVerifyToken(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 2, 'email_verify_token' => 'token123'],
        ]));

        $user = User::findByVerifyToken('token123');
        $this->assertIsArray($user);
    }

    public function test_findBySlug(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 3, 'slug' => 'john-doe'],
        ]));

        $user = User::findBySlug('john-doe');
        $this->assertIsArray($user);
    }

    public function test_generateSlug_creates_valid_slug(): void
    {
        $this->assertSame('john-doe', User::generateSlug('John Doe'));
        $this->assertSame('hello-world', User::generateSlug('Hello World!'));
        $this->assertSame('test', User::generateSlug('  Test  '));
        $this->assertSame('user', User::generateSlug(''));
        $this->assertSame('a-b-c', User::generateSlug('A B C'));
    }

    public function test_createUser_returns_id(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '99']));
        $this->assertSame(99, User::createUser(['name' => 'New User', 'email' => 'new@test.com', 'admin_id' => 1]));
    }

    public function test_verifyEmail(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::verifyEmail(1));
    }

    public function test_incrementFailedAttempts(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::incrementFailedAttempts(1));
    }

    public function test_resetFailedAttempts(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::resetFailedAttempts(1));
    }

    public function test_lockAccount(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::lockAccount(1, 15));
    }

    public function test_unlockAccount(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::unlockAccount(1));
    }

    public function test_updateLastLogin(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::updateLastLogin(1));
    }

    public function test_updatePassword(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(User::updatePassword(1, 'hash-value'));
    }

    public function test_findByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'email' => 'u1@test.com'],
                ['id' => 2, 'email' => 'u2@test.com'],
            ],
        ]));

        $users = User::findByAdmin(1);
        $this->assertCount(2, $users);
    }
}
