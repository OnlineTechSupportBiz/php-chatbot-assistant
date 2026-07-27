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
 * Chatbot model — multi-admin chatbot CRUD.
 *
 * Each chatbot belongs to an admin account, has a widget_token for public embedding,
 * and stores its system prompt + model/styling config as JSON.
 */
class Chatbot extends Model
{
    protected static function table(): string
    {
        return 'chatbots';
    }

    /**
     * Find all chatbots for a given admin account.
     */
    public static function findByAdmin(int $adminId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM chatbots WHERE admin_id = :aid ORDER BY created_at DESC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find all chatbots created by a specific user.
     */
    public static function findByUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM chatbots WHERE created_by = :uid ORDER BY created_at DESC'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find only active chatbots for an admin.
     */
    public static function findActiveByAdmin(int $adminId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM chatbots WHERE admin_id = :aid AND status = :status ORDER BY created_at DESC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count chatbots for an admin.
     */
    public static function countByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM chatbots WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count chatbots created by a specific user.
     */
    public static function countByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM chatbots WHERE created_by = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Create a new chatbot and return its ID.
     *
     * Expected keys: admin_id, name
     * Optional: industry, system_prompt, model_config (array), styling (array)
     */
    public static function createChatbot(array $data): int
    {
        $data['widget_token'] = self::generateWidgetToken();
        if (isset($data['model_config']) && is_array($data['model_config'])) {
            $data['model_config'] = json_encode($data['model_config']);
        }
        if (isset($data['styling']) && is_array($data['styling'])) {
            $data['styling'] = json_encode($data['styling']);
        }
        return self::insert($data);
    }

    /**
     * Update a chatbot, encoding JSON fields.
     */
    public static function updateChatbot(int $id, array $data): bool
    {
        if (isset($data['model_config']) && is_array($data['model_config'])) {
            $data['model_config'] = json_encode($data['model_config']);
        }
        if (isset($data['styling']) && is_array($data['styling'])) {
            $data['styling'] = json_encode($data['styling']);
        }
        return self::update($id, $data);
    }

    /**
     * Clone an existing chatbot with a new name and a fresh widget token.
     *
     * Copies: admin_id, created_by, industry, system_prompt, model_config,
     * styling, status, lead_capture_enabled, daily_token_budget, rate_limit_per_session.
     * Does NOT copy: id, widget_token, created_at, updated_at.
     *
     * Returns the new chatbot ID.
     */
    public static function cloneChatbot(int $sourceId, string $newName): int
    {
        $source = self::find($sourceId);
        if (!$source) {
            throw new \RuntimeException('Source chatbot not found');
        }

        $cloneData = [
            'admin_id'             => (int) $source['admin_id'],
            'created_by'           => (int) $source['created_by'],
            'name'                 => $newName,
            'industry'             => $source['industry'],
            'system_prompt'        => $source['system_prompt'],
            'model_config'         => $source['model_config'],   // already JSON string
            'styling'              => $source['styling'],        // already JSON string
            'status'               => $source['status'] ?? 'active',
            'lead_capture_enabled' => (int) ($source['lead_capture_enabled'] ?? 0),
            'daily_token_budget'      => $source['daily_token_budget'],
            'rate_limit_per_session'  => $source['rate_limit_per_session'],
            'allowed_domains'              => $source['allowed_domains'] ?? null,
            'max_message_length'           => $source['max_message_length'] ?? null,
            'max_messages_per_conversation' => $source['max_messages_per_conversation'] ?? null,
            'retrieval_strategy'           => $source['retrieval_strategy'] ?? 'traditional_rag',
        ];

        return self::createChatbot($cloneData);
    }

    /**
     * Generate a unique widget token.
     */
    private static function generateWidgetToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Find a chatbot by its widget token (public endpoint).
     */
    public static function findByWidgetToken(string $token): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM chatbots WHERE widget_token = :token AND status = :status'
        );
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
}
