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
 * Conversation model — tracks visitor chat sessions with a chatbot.
 *
 * Each conversation belongs to an admin + chatbot and is identified by
 * a visitor_session_id (UUID stored in the visitor's localStorage).
 */
class Conversation extends Model
{
    protected static function table(): string
    {
        return 'conversations';
    }

    /**
     * Find an existing conversation or create a new one.
     */
    public static function findOrCreate(int $adminId, int $chatbotId, string $visitorSessionId, ?string $visitorIp = null): array
    {
        $existing = self::findBySession($chatbotId, $visitorSessionId);
        if ($existing !== null) {
            return $existing;
        }

        $id = self::insert([
            'admin_id'           => $adminId,
            'chatbot_id'         => $chatbotId,
            'visitor_session_id' => $visitorSessionId,
            'visitor_ip'         => $visitorIp,
            'message_count'      => 0,
            'first_message_at'   => date('Y-m-d H:i:s'),
            'last_message_at'    => date('Y-m-d H:i:s'),
        ]);

        $row = self::find($id);
        return $row ?? [];
    }

    /**
     * Find a conversation by chatbot + visitor session ID.
     */
    public static function findBySession(int $chatbotId, string $visitorSessionId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM conversations WHERE chatbot_id = :cid AND visitor_session_id = :vsid'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':vsid', $visitorSessionId, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    /**
     * Find all conversations for a chatbot.
     */
    public static function findByChatbot(int $chatbotId, int $limit = 50): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM conversations WHERE chatbot_id = :cid ORDER BY last_message_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count conversations for an admin.
     */
    public static function countByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM conversations WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Conversation count scoped to chatbots created by a specific user.
     */
    public static function countByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM conversations cv
             INNER JOIN chatbots c ON c.id = cv.chatbot_id
             WHERE c.created_by = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count unique visitors (unique visitor_session_id) for an admin.
     */
    public static function uniqueVisitorsByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(DISTINCT visitor_session_id) FROM conversations WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Unique visitors scoped to a user's chatbots.
     */
    public static function uniqueVisitorsByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(DISTINCT cv.visitor_session_id)
             FROM conversations cv
             INNER JOIN chatbots c ON c.id = cv.chatbot_id
             WHERE c.created_by = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Per-chatbot conversation counts for an admin.
     * Returns ['chatbot_name' => string, 'count' => int].
     */
    public static function perChatbotByAdmin(int $adminId): array
    {
        $stmt = self::db()->prepare(
            'SELECT cb.id AS chatbot_id,
                    cb.name AS chatbot_name,
                    COUNT(DISTINCT c.id) AS count,
                    cb.daily_token_budget,
                    COALESCE(SUM(CASE WHEN m.created_at >= CURRENT_DATE THEN m.tokens_used ELSE 0 END), 0) AS tokens_today
             FROM conversations c
             JOIN chatbots cb ON cb.id = c.chatbot_id
             LEFT JOIN messages m ON m.conversation_id = c.id
             WHERE c.admin_id = :aid
             GROUP BY cb.id, cb.name, cb.daily_token_budget
             ORDER BY count DESC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Per-chatbot conversation counts scoped to a user's chatbots.
     */
    public static function perChatbotByUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT cb.id AS chatbot_id,
                    cb.name AS chatbot_name,
                    COUNT(DISTINCT c.id) AS count,
                    cb.daily_token_budget,
                    COALESCE(SUM(CASE WHEN m.created_at >= CURRENT_DATE THEN m.tokens_used ELSE 0 END), 0) AS tokens_today
             FROM conversations c
             JOIN chatbots cb ON cb.id = c.chatbot_id
             LEFT JOIN messages m ON m.conversation_id = c.id
             WHERE cb.created_by = :uid
             GROUP BY cb.id, cb.name, cb.daily_token_budget
             ORDER BY count DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count conversations for a specific chatbot.
     */
    public static function countByChatbot(int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM conversations WHERE chatbot_id = :cid'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count user messages from a session within the last N minutes.
     *
     * Returns the number of user-role messages from this visitor session
     * in the specified time window. Used for per-session rate limiting.
     *
     * Always returns 0 when no messages exist (never null).
     */
    public static function countRecentBySession(int $chatbotId, string $visitorSessionId, int $withinMinutes = 1): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM messages
             WHERE chatbot_id = :cid
               AND visitor_session_id = :vsid
               AND role = :role
               AND created_at >= NOW() - (:mins * INTERVAL \'1 minute\')'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':vsid', $visitorSessionId, PDO::PARAM_STR);
        $stmt->bindValue(':role', 'user', PDO::PARAM_STR);
        $stmt->bindValue(':mins', $withinMinutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Sum all tokens_used today for a given chatbot.
     *
     * Queries the messages table (where tokens_used is recorded per turn)
     * and returns the total token spend since midnight. Scoped per chatbot
     * so one chatbot exhausting its budget doesn't block other chatbots
     * belonging to the same admin account.
     *
     * Always returns 0 when no tokens have been recorded (never null).
     */
    public static function sumTokensUsedToday(int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(tokens_used), 0) FROM messages
             WHERE chatbot_id = :cid
               AND tokens_used IS NOT NULL
               AND DATE(created_at) = CURRENT_DATE'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Increment message count and update last_message_at.
     */
    public static function touch(int $id): void
    {
        $stmt = self::db()->prepare(
            'UPDATE conversations SET message_count = message_count + 1, last_message_at = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Get recent conversations for a chatbot (limited, with last message).
     *
     * @return array[] Each row includes the conversation fields plus last_message_content.
     */
    public static function getRecentWithMessages(int $chatbotId, int $limit = 10): array
    {
        $stmt = self::db()->prepare(
            'SELECT c.*, m.content AS last_message_content, m.role AS last_message_role
             FROM conversations c
             LEFT JOIN messages m ON m.id = (
                 SELECT id FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
             )
             WHERE c.chatbot_id = :cid
             ORDER BY c.last_message_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Rate a conversation (1-5 stars).
     *
     * Strips all non-digit characters from the input via preg_replace.
     * Returns false if the result is blank or outside 1-5.
     */
    public static function rate(int $id, string $rawInput): bool
    {
        $clean = preg_replace('/[^0-9]/', '', $rawInput);
        if ($clean === '') {
            return false;
        }
        $rating = (int) $clean;
        if ($rating < 1 || $rating > 5) {
            return false;
        }
        return self::update($id, ['rating' => $rating]);
    }
}
