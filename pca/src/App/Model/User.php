<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace App\Model;

use PDO;

class User extends Model
{
    protected static function table(): string
    {
        return 'users';
    }

    /**
     * Find a user by email address.
     */
    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a user by email verification token.
     */
    public static function findByVerifyToken(string $token): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email_verify_token = :token');
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find a user by slug (role-agnostic).
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE slug = :slug');
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Generate a slug from a name — no uniqueness check required.
     */
    public static function generateSlug(string $name): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        return $slug === '' ? 'user' : $slug;
    }

    /**
     * Create a new user.
     */
    public static function createUser(array $data): int
    {
        return self::insert($data);
    }

    /**
     * Verify a user's email.
     */
    public static function verifyEmail(int $userId): bool
    {
        return self::update($userId, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verify_token' => null,
        ]);
    }

    /**
     * Get all users for a given admin account.
     */
    public static function findByAdmin(int $adminId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE admin_id = :aid ORDER BY created_at DESC');
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Increment failed login attempts.
     */
    public static function incrementFailedAttempts(int $userId): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id'
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Reset failed login attempts.
     */
    public static function resetFailedAttempts(int $userId): bool
    {
        return self::update($userId, [
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);
    }

    /**
     * Lock a user account temporarily.
     */
    public static function lockAccount(int $userId, int $minutes = 15): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE users SET is_locked = 1, locked_until = NOW() + (:mins * INTERVAL \'1 minute\') WHERE id = :id'
        );
        $stmt->bindValue(':mins', $minutes, PDO::PARAM_INT);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Unlock a user account.
     */
    public static function unlockAccount(int $userId): bool
    {
        return self::update($userId, [
            'is_locked'   => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Update last login timestamp.
     */
    public static function updateLastLogin(int $userId): bool
    {
        return self::update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update a user's password hash.
     */
    public static function updatePassword(int $userId, string $passwordHash): bool
    {
        return self::update($userId, [
            'password_hash' => $passwordHash,
        ]);
    }
}
