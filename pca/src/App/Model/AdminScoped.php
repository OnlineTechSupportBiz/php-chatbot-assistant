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

/**
 * AdminScoped trait — defense-in-depth for multi-tenant row security.
 *
 * Models that use this trait get automatic admin_id filtering on
 * findBy/findAll/delete queries.  The admin_id is bound from the
 * authenticated user's session, never from client input.
 */
trait AdminScoped
{
    /**
     * Build a WHERE clause fragment scoped to an admin_id.
     *
     * @param  string $alias Table alias (e.g. 'c' for chatbots)
     * @return string        "AND c.admin_id = :admin_id" or empty string
     */
    protected static function adminWhere(string $alias = ''): string
    {
        $adminId = self::getCurrentAdminId();
        if ($adminId === null) {
            // Super-admin or unauthenticated — no scoping
            return '';
        }
        $prefix = $alias ? "{$alias}." : '';
        return "AND {$prefix}admin_id = :admin_id";
    }

    /**
     * Get the current admin ID from the session.
     */
    protected static function getCurrentAdminId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return $_SESSION['admin_id'] ?? null;
    }

    /**
     * Bind admin_id param to a statement if scoping is active.
     */
    protected static function bindAdminParam(\PDOStatement $stmt): void
    {
        $adminId = self::getCurrentAdminId();
        if ($adminId !== null) {
            $stmt->bindValue(':admin_id', $adminId, \PDO::PARAM_INT);
        }
    }
}
