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
 * Quick Answer model — exact-trigger-based canned responses.
 *
 * Schema columns:
 *   id, admin_id, chatbot_id, trigger, answer, priority (INT),
 *   is_active (TINYINT), created_at, updated_at
 *
 * Priority is managed via drag-to-reorder from the UI. The front-end
 * sends an ordered list of IDs and this model re-assigns priority
 * values based on position (0 = highest).
 */
class QuickAnswer extends Model
{
    protected static function table(): string
    {
        return 'quick_answers';
    }

    /**
     * Find all quick answers for a chatbot, ordered by priority (highest first).
     */
    public static function findByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM quick_answers
             WHERE admin_id = :aid AND chatbot_id = :cid
             ORDER BY priority DESC, trigger ASC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find only active quick answers for a chatbot, ordered by priority (highest first).
     */
    public static function findActiveByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM quick_answers
             WHERE admin_id = :aid AND chatbot_id = :cid AND is_active = 1
             ORDER BY priority DESC, trigger ASC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a quick answer that matches the exact trigger text.
     *
     * Returns the highest-priority match, null if none.
     */
    public static function findByTrigger(int $adminId, int $chatbotId, string $trigger): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM quick_answers
             WHERE admin_id = :aid AND chatbot_id = :cid
               AND trigger = :trg AND is_active = 1
             ORDER BY priority DESC
             LIMIT 1'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':trg', $trigger, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Try to match a user message against active quick answers (trigger-based exact match).
     *
     * Performs a case-insensitive trimmed comparison against the `trigger` column.
     * Returns the highest-priority matching answer, or null.
     */
    public static function matchMessage(int $adminId, int $chatbotId, string $message): ?array
    {
        $msg = mb_strtolower(trim($message));

        $stmt = self::db()->prepare(
            'SELECT * FROM quick_answers
             WHERE admin_id = :aid AND chatbot_id = :cid
               AND LOWER(TRIM(trigger)) = :msg
               AND is_active = 1
             ORDER BY priority DESC
             LIMIT 1'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':msg', $msg, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Count quick answers for a chatbot.
     */
    public static function countByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM quick_answers
             WHERE admin_id = :aid AND chatbot_id = :cid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count all quick answers for an admin (dashboard stats).
     */
    public static function countByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM quick_answers WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Reorder quick answers based on an ordered list of IDs.
     *
     * Priority is assigned by position in the array (0 = highest).
     * Only answers belonging to the specified admin+chatbot are affected.
     *
     * @param int   $adminId
     * @param int   $chatbotId
     * @param int[] $ids  Ordered array of quick answer IDs
     */
    public static function reorder(int $adminId, int $chatbotId, array $ids): void
    {
        $stmt = self::db()->prepare(
            'UPDATE quick_answers SET priority = :priority
             WHERE id = :id AND admin_id = :aid AND chatbot_id = :cid'
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue(':priority', count($ids) - $i, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
            $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}
