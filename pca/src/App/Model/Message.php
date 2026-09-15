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
 * Message model — individual messages within a conversation.
 *
 * Records each turn (user question + assistant response) along with
 * metadata like token usage, response time, and source (RAG, quick_answer, etc.).
 */
class Message extends Model
{
    protected static function table(): string
    {
        return 'messages';
    }

    /**
     * Create a new message.
     *
     * @param  int         $adminId
     * @param  int         $chatbotId
     * @param  int         $conversationId
     * @param  string      $role       'user' or 'assistant'
     * @param  string      $content    Message text
     * @param  string|null $source     'rag', 'quick_answer', 'llm_only', 'blocked'
     * @param  int|null    $tokensUsed
     * @param  int|null    $responseTimeMs
     * @param  string|null $model      Model name used (e.g. 'gpt-4o-mini')
     * @return int  Inserted message ID
     */
    public static function create(
        int $adminId,
        int $chatbotId,
        int $conversationId,
        string $visitorSessionId,
        string $role,
        string $content,
        ?int $tokensUsed = null,
        ?int $responseTimeMs = null,
        ?string $source = null
    ): int {
        return self::insert([
            'admin_id'          => $adminId,
            'chatbot_id'         => $chatbotId,
            'conversation_id'    => $conversationId,
            'visitor_session_id' => $visitorSessionId,
            'role'               => $role,
            'content'            => $content,
            'tokens_used'        => $tokensUsed,
            'response_time_ms'   => $responseTimeMs,
            'source'             => $source,
        ]);
    }

    /**
     * Get all messages for a conversation, ordered chronologically.
     */
    public static function findByConversation(int $conversationId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM messages WHERE conversation_id = :cid ORDER BY id ASC'
        );
        $stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Total message count for an admin.
     */
    public static function countByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM messages WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Message count scoped to chatbots created by a specific user.
     */
    public static function countByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM messages m
             INNER JOIN chatbots c ON c.id = m.chatbot_id
             WHERE c.created_by = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Message counts per day for the last 7 days for an admin.
     * Returns an array of ['date' => 'YYYY-MM-DD', 'count' => N].
     */
    public static function last7DaysByAdmin(int $adminId): array
    {
        return self::chartByAdmin($adminId, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    }

    /**
     * Message counts per day for the last 7 days, scoped to a user's chatbots.
     */
    public static function last7DaysByUser(int $userId): array
    {
        return self::chartByUser($userId, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
    }

    /**
     * Message counts per day for a date range.
     * Returns an array of ['date' => 'YYYY-MM-DD', 'count' => N].
     */
    public static function chartByAdmin(int $adminId, string $from, string $to): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM messages
             WHERE admin_id = :aid AND created_at >= :from_date
               AND created_at < :to_date::date + 1
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $from);
        $stmt->bindValue(':to_date', $to);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Message counts per day for a date range, scoped to a user's chatbots.
     */
    public static function chartByUser(int $userId, string $from, string $to): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE(m.created_at) AS date, COUNT(*) AS count
             FROM messages m
             INNER JOIN chatbots c ON c.id = m.chatbot_id
             WHERE c.created_by = :uid AND m.created_at >= :from_date
               AND m.created_at < :to_date::date + 1
             GROUP BY DATE(m.created_at)
             ORDER BY date ASC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $from);
        $stmt->bindValue(':to_date', $to);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Source breakdown for an admin (rag, quick_answer, llm_only).
     */
    public static function sourceBreakdownByAdmin(int $adminId): array
    {
        $stmt = self::db()->prepare(
            "SELECT source, COUNT(*) AS count
             FROM messages
             WHERE admin_id = :aid AND source IS NOT NULL AND role = 'assistant'
             GROUP BY source
             ORDER BY count DESC"
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Source breakdown scoped to a user's chatbots.
     */
    public static function sourceBreakdownByUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            "SELECT m.source, COUNT(*) AS count
             FROM messages m
             INNER JOIN chatbots c ON c.id = m.chatbot_id
             WHERE c.created_by = :uid AND m.source IS NOT NULL AND m.role = 'assistant'
             GROUP BY m.source
             ORDER BY count DESC"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Average response time for a chatbot (in ms).
     */
    public static function avgResponseTimeByChatbot(int $chatbotId): ?float
    {
        $stmt = self::db()->prepare(
            "SELECT AVG(response_time_ms) FROM messages
             WHERE chatbot_id = :cid AND response_time_ms IS NOT NULL AND role = 'assistant'"
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (float) $val : null;
    }

    /**
     * Total tokens used by an admin.
     */
    public static function totalTokensByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(tokens_used), 0) FROM messages WHERE admin_id = :aid AND tokens_used IS NOT NULL'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Total tokens used, scoped to a user's chatbots.
     */
    public static function totalTokensByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(m.tokens_used), 0)
             FROM messages m
             INNER JOIN chatbots c ON c.id = m.chatbot_id
             WHERE c.created_by = :uid AND m.tokens_used IS NOT NULL'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Average response time across all chatbots for an admin (in ms).
     */
    public static function avgResponseTimeByAdmin(int $adminId): ?float
    {
        $stmt = self::db()->prepare(
            "SELECT AVG(response_time_ms) FROM messages
             WHERE admin_id = :aid AND response_time_ms IS NOT NULL AND role = 'assistant'"
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (float) $val : null;
    }

    /**
     * Average response time scoped to a user's chatbots (in ms).
     */
    public static function avgResponseTimeByUser(int $userId): ?float
    {
        $stmt = self::db()->prepare(
            "SELECT AVG(m.response_time_ms)
             FROM messages m
             INNER JOIN chatbots c ON c.id = m.chatbot_id
             WHERE c.created_by = :uid AND m.response_time_ms IS NOT NULL AND m.role = 'assistant'"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (float) $val : null;
    }

    /**
     * Count messages for a specific chatbot.
     */
    public static function countByChatbot(int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM messages WHERE chatbot_id = :cid'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get the last N messages for a conversation (for context window assembly).
     */
    public static function getRecentByConversation(int $conversationId, int $limit = 20): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM messages WHERE conversation_id = :cid ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll());
    }
}
