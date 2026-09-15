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
 * Base model — every Model extends this.
 *
 * Provides shared DB access and basic CRUD helpers.
 * All tables reside in the 'chatbot_assistant' schema (set via search_path).
 */
abstract class Model
{
    use AdminScoped;

    protected static function db(): PDO
    {
        return \getDb();
    }

    /**
     * Get the table name for this model.
     * Override in child classes.
     */
    protected static function table(): string
    {
        $class = static::class;
        $parts = explode('\\', $class);
        $name  = end($parts);
        // Pluralize: simple convention — add 's'
        return strtolower($name) . 's';
    }

    /**
     * Find a row by its primary key.
     */
    public static function find(int $id): ?array
    {
        $table = static::table();
        $stmt  = self::db()->prepare("SELECT * FROM {$table} WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find all rows, optionally scoped to admin.
     */
    public static function findAll(string $orderBy = 'created_at DESC'): array
    {
        $table = static::table();
        $sql   = "SELECT * FROM {$table} WHERE 1=1 " . self::adminWhere();
        $sql  .= " ORDER BY {$orderBy}";

        $stmt = self::db()->prepare($sql);
        self::bindAdminParam($stmt);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Insert a row and return the new ID.
     */
    public static function insert(array $data): int
    {
        $table = static::table();
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = self::db()->prepare($sql);

        foreach ($data as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(":{$key}", $value, $type);
        }

        $stmt->execute();
        return (int) self::db()->lastInsertId();
    }

    /**
     * Update a row by ID.
     */
    public static function update(int $id, array $data): bool
    {
        $table = static::table();
        $set = '';
        foreach (array_keys($data) as $key) {
            $set .= "{$key} = :{$key}, ";
        }
        $set = rtrim($set, ', ');

        $sql = "UPDATE {$table} SET {$set} WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        foreach ($data as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(":{$key}", $value, $type);
        }

        return $stmt->execute();
    }

    /**
     * Delete a row by ID, scoped to admin.
     */
    public static function delete(int $id): bool
    {
        $table = static::table();
        $sql   = "DELETE FROM {$table} WHERE id = :id " . self::adminWhere();
        $stmt  = self::db()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        self::bindAdminParam($stmt);
        return $stmt->execute();
    }

    /**
     * Count rows, optionally scoped to admin.
     */
    public static function count(): int
    {
        $table = static::table();
        $sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1 " . self::adminWhere();
        $stmt  = self::db()->prepare($sql);
        self::bindAdminParam($stmt);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
