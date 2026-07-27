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
 * Lead model — captured visitor lead information from chatbot conversations.
 *
 * Schema columns:
 *   id, admin_id, chatbot_id, conversation_id, visitor_session_id,
 *   name, email, phone, captured_at
 */
class Lead extends Model
{
    protected static function table(): string
    {
        return 'leads';
    }

    /**
     * Find all leads for a chatbot, newest first.
     */
    public static function findByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM leads
             WHERE admin_id = :aid AND chatbot_id = :cid
             ORDER BY captured_at DESC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a lead by conversation (one lead per conversation).
     */
    public static function findByConversation(int $conversationId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM leads WHERE conversation_id = :cid LIMIT 1'
        );
        $stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Count leads for a chatbot.
     */
    public static function countByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM leads
             WHERE admin_id = :aid AND chatbot_id = :cid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Create a new lead record.
     */
    public static function createLead(array $data): int
    {
        return self::insert($data);
    }
}
