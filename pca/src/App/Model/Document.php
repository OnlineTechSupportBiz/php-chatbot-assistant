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
 * Document model — admin-scoped document tracked through the training pipeline.
 *
 * Schema columns:
 *   id, admin_id, chatbot_id, filename, original_name, mime_type, file_path,
 *   file_size, status (enum: uploaded|parsing|parsed|chunking|embedding|indexed|failed),
 *   error_message, llamacloud_job_id, llamacloud_status, parsed_text, chunk_count,
 *   uploaded_by, created_at, updated_at
 */
class Document extends Model
{
    protected static function table(): string
    {
        return 'documents';
    }

    /**
     * Create a new document record.
     */
    public static function create(array $data): int|false
    {
        $data['filename'] = $data['filename'] ?? basename($data['stored_path'] ?? '');
        return self::insert($data);
    }

    /**
     * Find all documents for a chatbot, newest first.
     */
    public static function findByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid
             ORDER BY created_at DESC'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Check if a document with the same original name already exists
     * for the given admin + chatbot, and return the full row.
     */
    public static function findByOriginalName(int $adminId, int $chatbotId, string $originalName): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid AND original_name = :name'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $originalName, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Look up the stored retrieval strategy for a document by its original name.
     *
     * Returns null if the document hasn't been trained yet (no strategy recorded),
     * or if no document with that name exists for this chatbot.
     *
     * @return array{strategy: string, document_id: int}|null
     */
    public static function findStrategyByOriginalName(int $chatbotId, string $originalName): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT document_id, strategy FROM document_strategies
             WHERE chatbot_id = :cid AND original_name = :name
             LIMIT 1'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $originalName, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all stored strategies for a chatbot as a map of document_id → strategy.
     *
     * @return array<int, string>  e.g. [1 => 'page_index', 2 => 'traditional_rag']
     */
    public static function findStrategiesByChatbot(int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT document_id, strategy FROM document_strategies
             WHERE chatbot_id = :cid'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();

        $map = [];
        while ($row = $stmt->fetch()) {
            $map[(int) $row['document_id']] = $row['strategy'];
        }
        return $map;
    }

    /**
     * Record or update the retrieval strategy used to train a document.
     *
     * Uses INSERT … ON CONFLICT (document_id is UNIQUE) so calling this
     * repeatedly for the same document is safe.
     */
    public static function recordStrategy(int $adminId, int $chatbotId, int $documentId, string $originalName, string $strategy): void
    {
        $stmt = self::db()->prepare(
            'INSERT INTO document_strategies (admin_id, chatbot_id, document_id, original_name, strategy)
             VALUES (:aid, :cid, :did, :name, :strategy)
             ON CONFLICT (document_id)
             DO UPDATE SET strategy = :strategy2'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $originalName, PDO::PARAM_STR);
        $stmt->bindValue(':strategy', $strategy, PDO::PARAM_STR);
        $stmt->bindValue(':strategy2', $strategy, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Check if a document with the same original name already exists
     * for the given admin + chatbot.
     */
    public static function existsByOriginalName(int $adminId, int $chatbotId, string $originalName): bool
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid AND original_name = :name'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $originalName, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Count how many documents are fully indexed for a chatbot.
     */
    public static function countIndexedByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid AND status = :status'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'indexed', PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count all documents for an admin (dashboard stats).
     */
    public static function countByAdmin(int $adminId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM documents WHERE admin_id = :aid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Document count scoped to chatbots created by a specific user.
     */
    public static function countByUser(int $userId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM documents d
             INNER JOIN chatbots c ON c.id = d.chatbot_id
             WHERE c.created_by = :uid'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count total chunks across all indexed documents for a chatbot.
     */
    public static function totalChunksByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(chunk_count), 0) FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid AND status = :status'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'indexed', PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Update document status.
     */
    public static function patchStatus(int $documentId, string $status): bool
    {
        return self::update($documentId, ['status' => $status]);
    }

    /**
     * Save parsed text and mark as parsed.
     */
    public static function saveParsedText(int $documentId, string $parsedText): bool
    {
        return self::update($documentId, [
            'status'      => 'parsed',
            'parsed_text' => $parsedText,
        ]);
    }

    /**
     * Mark document as indexed (training complete).
     *
     * For traditional RAG: counts chunks from document_chunks.
     */
    public static function markIndexed(int $documentId): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE documents
             SET status = :status, chunk_count = (
                 SELECT COUNT(*) FROM document_chunks WHERE document_id = :did
             )
             WHERE id = :id'
        );
        $stmt->bindValue(':status', 'indexed', PDO::PARAM_STR);
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mark document as indexed for PageIndex strategy.
     *
     * Counts page index nodes instead of vector chunks.
     */
    public static function markPageIndexed(int $documentId): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE documents
             SET status = :status, chunk_count = (
                 SELECT COUNT(*) FROM document_page_index WHERE document_id = :did
             )
             WHERE id = :id'
        );
        $stmt->bindValue(':status', 'indexed', PDO::PARAM_STR);
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Total file storage size (in bytes) for all documents of a chatbot.
     */
    public static function totalFileSizeByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(file_size), 0) FROM documents
             WHERE admin_id = :aid AND chatbot_id = :cid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Estimated vector database size (in bytes) for a chatbot.
     * Each embedding is 1536 float32 values = 6144 bytes.
     */
    public static function vectorStorageByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(pg_column_size(embedding)), 0) FROM document_chunks
             WHERE admin_id = :aid AND chatbot_id = :cid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete a document by ID, along with its chunks and file.
     */
    public static function delete(int $documentId): bool
    {
        // Fetch file path first (caller typically handles the file)
        // Delete chunks
        $stmt = self::db()->prepare('DELETE FROM document_chunks WHERE document_id = :did');
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete page index nodes (if using PageIndex strategy)
        $stmt = self::db()->prepare('DELETE FROM document_page_index WHERE document_id = :did');
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete document record
        $stmt = self::db()->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
