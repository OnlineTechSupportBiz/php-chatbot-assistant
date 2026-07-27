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

namespace App\Model;

use PDO;
use RuntimeException;

/**
 * UserPermission — CRUD for the user_permissions table.
 *
 * Each row is a boolean toggle (widget_chat, widget_quick_answers) scoped to
 * a user (each user is their own company). Admin/oversight users can edit
 * any user's permissions; regular users see their own.
 */
class UserPermission
{
    /**
     * Return the table name.
     */
    public static function table(): string
    {
        return 'user_permissions';
    }

    /**
     * List all known permission keys.
     * Only endpoint toggles are active (page-level permissions were removed).
     */
    public static function allPermissions(): array
    {
        return [
            'widget_chat',
            'widget_quick_answers',
        ];
    }

    /**
     * Return endpoint-toggling permissions with user-facing labels.
     * Keys must be a subset of allPermissions().
     */
    public static function endpointToggles(): array
    {
        return [
            'widget_chat'          => 'Chatbot Widget (public chat API)',
            'widget_quick_answers' => 'Quick Answers Widget (public search API)',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Queries
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get all permissions for a user as [key => enabled].
     */
    public static function getByUser(int $userId): array
    {
        $pdo = \getDb();
        $stmt = $pdo->prepare(
            'SELECT permission, enabled FROM ' . self::table() . ' WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['permission']] = (int) $row['enabled'];
        }
        return $result;
    }

    /**
     * Check if a specific permission is enabled for a user (default false).
     */
    public static function has(int $userId, string $permission): bool
    {
        $pdo = \getDb();
        $stmt = $pdo->prepare(
            'SELECT enabled FROM ' . self::table() . ' WHERE user_id = ? AND permission = ?'
        );
        $stmt->execute([$userId, $permission]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false && (int) $row['enabled'] === 1;
    }

    /**
     * Bulk upsert permissions for a user.
     *
     * @param int   $userId
     * @param array $perms  Associative array of [permission => 0|1].
     */
    public static function bulkSetForUser(int $userId, array $perms): void
    {
        $pdo = \getDb();
        $pdo->beginTransaction();
        try {
            // user_permissions has a unique constraint on (user_id, permission)
            $upsert = $pdo->prepare(
                'INSERT INTO ' . self::table() . ' (user_id, permission, enabled)
                 VALUES (?, ?, ?)
                 ON CONFLICT (user_id, permission) DO UPDATE SET enabled = EXCLUDED.enabled'
            );
            foreach ($perms as $key => $value) {
                $enabled = $value ? 1 : 0;
                $upsert->execute([$userId, $key, $enabled]);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to set permissions: ' . $e->getMessage());
        }
    }

    /**
     * Delete all permissions for a user (when user is deleted).
     */
    public static function deleteByUser(int $userId): void
    {
        $pdo = \getDb();
        $stmt = $pdo->prepare('DELETE FROM ' . self::table() . ' WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}
