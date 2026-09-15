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
 * Audit log model — records all mutating actions.
 *
 * Every create/update/delete of critical entities writes an immutable row
 * via ::log().  This is called from Controller actions, not from Models,
 * to keep the data layer simple.
 */
class AuditLog extends Model
{
    protected static function table(): string
    {
        return 'audit_logs';
    }

    /**
     * Write an audit log entry.
     */
    public static function log(
        ?int   $adminId,
        ?int   $userId,
        string $action,
        ?string $entityType = null,
        ?int   $entityId   = null,
        mixed  $oldValue   = null,
        mixed  $newValue   = null,
    ): int {
        return self::insert([
            'admin_id'    => $adminId,
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_value'   => $oldValue !== null ? json_encode($oldValue) : null,
            'new_value'   => $newValue !== null ? json_encode($newValue) : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    /**
     * Get audit logs for a specific user (not admin-wide).
     */
    public static function findByUser(
        int    $userId,
        string $action     = '',
        string $entityType = '',
        int    $page       = 1,
        int    $perPage    = 50,
    ): array {
        $conditions = ['user_id = :user_id'];
        $params     = [':user_id' => $userId];

        if ($action !== '') {
            $conditions[] = 'action = :action';
            $params[':action'] = $action;
        }
        if ($entityType !== '') {
            $conditions[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $entityType;
        }

        $where = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = self::db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$where}");
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $countStmt->bindValue($k, $v, $type);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        // Rows
        $sql = "SELECT * FROM audit_logs WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = self::db()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
        ];
    }

    /**
     * Get audit logs for an admin, with optional filters, paginated.
     */
    public static function findByAdmin(
        int    $adminId,
        string $action     = '',
        string $entityType = '',
        int    $page       = 1,
        int    $perPage    = 50,
    ): array {
        $conditions = ['admin_id = :admin_id'];
        $params     = [':admin_id' => $adminId];

        if ($action !== '') {
            $conditions[] = 'action = :action';
            $params[':action'] = $action;
        }
        if ($entityType !== '') {
            $conditions[] = 'entity_type = :entity_type';
            $params[':entity_type'] = $entityType;
        }

        $where = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        // Count
        $countStmt = self::db()->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$where}");
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $countStmt->bindValue($k, $v, $type);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        // Rows
        $sql = "SELECT * FROM audit_logs WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = self::db()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
        ];
    }
}
