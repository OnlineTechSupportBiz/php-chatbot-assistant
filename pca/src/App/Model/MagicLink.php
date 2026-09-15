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

/**
 * Magic link tokens for password-less login.
 */
class MagicLink extends Model
{
    protected static function table(): string
    {
        return 'magic_links';
    }

    /**
     * Find a valid (unexpired, unused) token.
     */
    public static function findValidToken(string $token): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM magic_links
             WHERE token = :token
               AND expires_at > NOW()
               AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new magic link token.
     */
    public static function createToken(string $email, int $adminId, string $token, string $expiresAt): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO magic_links (email, admin_id, token, expires_at, created_at)
             VALUES (:email, :admin_id, :token, :expires_at, NOW())'
        );
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
        $stmt->execute();
        return (int) self::db()->lastInsertId();
    }

    /**
     * Mark a token as used.
     */
    public static function markUsed(int $id): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE magic_links SET used_at = NOW() WHERE id = :id AND used_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Check if a recent unexpired token already exists for this email (rate-limit).
     */
    public static function hasRecentToken(string $email): bool
    {
        $stmt = self::db()->prepare(
            'SELECT id FROM magic_links
             WHERE email = :email
               AND expires_at > NOW()
               AND used_at IS NULL
               AND created_at > NOW() - INTERVAL '60 seconds'
             LIMIT 1'
        );
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetch();
    }

    /**
     * Prune expired tokens.
     */
    public static function pruneExpired(): int
    {
        $stmt = self::db()->prepare(
            'DELETE FROM magic_links WHERE expires_at <= NOW()'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
