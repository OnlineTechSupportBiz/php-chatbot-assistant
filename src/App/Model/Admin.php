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
 * Represents an admin/owner account (tenant).
 *
 * After schema flattening (migration 023), the admins table no longer exists.
 * Admin/tenant records live in the users table with role='admin'.
 * The tenant-level columns are: company_name (was admins.name), slug, is_active.
 */
class Admin extends Model
{
    protected static function table(): string
    {
        return 'users';
    }

    /**
     * Find an admin/tenant by its slug.
     */
    public static function findBySlug(string $slug): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE slug = :slug AND role = \'admin\'');
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find an admin/tenant by company name (for registration uniqueness check).
     */
    public static function findByName(string $name): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE company_name = :name AND role = \'admin\'');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find an admin/tenant by its users.id.
     * Overrides base Model::find() to ensure role = 'admin'.
     */
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = :id AND role = \'admin\'');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find all admin/tenant accounts (no admin_id scoping).
     * Overrides the base scoped findAll().
     */
    public static function findAll(string $orderBy = 'created_at DESC'): array
    {
        $sql = "SELECT * FROM users WHERE role = 'admin' ORDER BY {$orderBy}";
        $stmt = self::db()->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Generate a unique slug from a name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        $base = $slug;

        $i = 1;
        while (self::findBySlug($slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Create a new admin/tenant user account and return its ID.
     *
     * Creates a user with role='admin', company_name, slug, is_active=1.
     * The admin_id is set to self (self-referencing FK).
     */
    public static function create(string $companyName): int
    {
        $slug = self::generateSlug($companyName);
        $db = self::db();

        // Defer the self-referencing FK check so we can insert with a
        // placeholder admin_id, then set it to the new row's own id.
        $db->exec("SET CONSTRAINTS fk_users_admin DEFERRED");

        $stmt = $db->prepare(
            'INSERT INTO users (company_name, slug, is_active, role, admin_id, email, password_hash, name, created_at)
             VALUES (:company_name, :slug, 1, \'admin\', 0, :email, :password_hash, :name, NOW())'
        );
        $stmt->bindValue(':company_name', $companyName, PDO::PARAM_STR);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':email', 'admin@' . $slug . '.placeholder', PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), PDO::PARAM_STR);
        $stmt->bindValue(':name', $companyName, PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int) $db->lastInsertId();

        // Set admin_id = self (self-referencing FK)
        $fixStmt = $db->prepare('UPDATE users SET admin_id = id WHERE id = :id');
        $fixStmt->bindValue(':id', $newId, PDO::PARAM_INT);
        $fixStmt->execute();

        // Re-enable immediate FK checking — should pass since admin_id = id now
        $db->exec("SET CONSTRAINTS fk_users_admin IMMEDIATE");

        return $newId;
    }

    /**
     * Get all users belonging to this admin/tenant account.
     */
    public static function users(int $adminId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE admin_id = :aid ORDER BY created_at DESC');
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── API Key Management ─────────────────────────────────────────────────

    /**
     * Get API keys for an admin/tenant account.
     * Reads from the admin user's own record (admin_id = self).
     */
    public static function getApiKeys(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT openai_api_key, llamacloud_api_key FROM users WHERE id = :id'
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: ['openai_api_key' => null, 'llamacloud_api_key' => null];
    }

    /**
     * Upsert API keys for a user account.
     * Writes to the user's own record.
     */
    public static function setApiKeys(int $userId, array $keys): bool
    {
        $update = [];
        $binds  = [':id' => $userId];
        foreach (['openai_api_key', 'llamacloud_api_key'] as $col) {
            if (array_key_exists($col, $keys)) {
                $update[] = "{$col} = :{$col}";
                $binds[":{$col}"] = $keys[$col];
            }
        }
        if (empty($update)) {
            return false;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $update) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($binds);
    }

    /**
     * Check if an admin account has all required API keys configured.
     */
    public static function hasRequiredApiKeys(int $adminId): bool
    {
        $keys = self::getApiKeys($adminId);
        return !empty($keys['openai_api_key']) && !empty($keys['llamacloud_api_key']);
    }

    // ── Brand Name ────────────────────────────────────────────────────────

    /**
     * Get the configured brand name for display in the UI.
     *
     * When $adminId is provided, returns that admin's brand_name.
     * Otherwise, returns the brand_name of the first admin (for public pages)
     * or the default 'Chatbot Assistant'.
     */
    public static function getBrandName(?int $adminId = null): string
    {
        if ($adminId !== null) {
            $stmt = self::db()->prepare(
                "SELECT brand_name FROM users WHERE id = :id AND role = 'admin'"
            );
            $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $name = $stmt->fetchColumn();
            return ($name !== false && $name !== '') ? (string) $name : 'Chatbot Assistant';
        }

        // Fallback: first admin's brand_name
        $stmt = self::db()->query(
            "SELECT brand_name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1"
        );
        $name = $stmt->fetchColumn();
        return ($name !== false && $name !== '') ? (string) $name : 'Chatbot Assistant';
    }

    /**
     * Update the brand_name for an admin account.
     */
    public static function setBrandName(int $adminId, string $brandName): bool
    {
        $stmt = self::db()->prepare(
            "UPDATE users SET brand_name = :brand_name WHERE id = :id AND role = 'admin'"
        );
        $stmt->bindValue(':brand_name', $brandName, PDO::PARAM_STR);
        $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
