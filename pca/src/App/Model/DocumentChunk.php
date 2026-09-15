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
 * DocumentChunk model — individual text chunks with embeddings for RAG.
 *
 * Each chunk belongs to a document and carries the OpenAI embedding vector
 * stored as a pgvector VECTOR(1536) column with an HNSW index for cosine similarity search.
 */
class DocumentChunk extends Model
{
    protected static function table(): string
    {
        return 'document_chunks';
    }

    /**
     * Insert a batch of chunks for a document in a single transaction.
     *
     * Embeddings are cast to pgvector VECTOR(1536) via ::vector cast.
     *
     * @param  int       $adminId
     * @param  int       $chatbotId
     * @param  int       $documentId
     * @param  array[]   $chunks  Array of ['chunk_index' => int, 'chunk_text' => string, 'embedding' => string (JSON array text like "[0.1,-0.2,...]")]
     */
    public static function insertBatch(int $adminId, int $chatbotId, int $documentId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }

        $db = self::db();
        $db->beginTransaction();

        try {
            $values = [];
            $params = [];

            foreach ($chunks as $i => $chunk) {
                $idx           = $chunk['chunk_index'];
                $text          = $chunk['chunk_text'];
                $embeddingJson = $chunk['embedding']; // JSON array string, cast to vector

                $ai = ":aid_{$i}";
                $ci = ":cid_{$i}";
                $di = ":did_{$i}";
                $xi = ":idx_{$i}";
                $tiText = ":text_{$i}";
                $ei = ":emb_{$i}";

                // Cast JSON array string to pgvector VECTOR(1536) type
                $values[] = "({$ai}, {$ci}, {$di}, {$xi}, {$tiText}, {$ei}::vector)";
                $params[$ai] = [$adminId, PDO::PARAM_INT];
                $params[$ci] = [$chatbotId, PDO::PARAM_INT];
                $params[$di] = [$documentId, PDO::PARAM_INT];
                $params[$xi] = [$idx, PDO::PARAM_INT];
                $params[$tiText] = [$text, PDO::PARAM_STR];
                $params[$ei] = [$embeddingJson, PDO::PARAM_STR];
            }

            $sql = 'INSERT INTO document_chunks (admin_id, chatbot_id, document_id, chunk_index, chunk_text, embedding) VALUES '
                 . implode(', ', $values);

            $stmt = $db->prepare($sql);
            foreach ($params as $key => [$value, $type]) {
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete all chunks for a given document.
     */
    public static function deleteByDocument(int $documentId): bool
    {
        $stmt = self::db()->prepare(
            'DELETE FROM document_chunks WHERE document_id = :did'
        );
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Count chunks for a document.
     */
    public static function countByDocument(int $documentId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM document_chunks WHERE document_id = :did'
        );
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Perform vector similarity search across chunks for a chatbot using pgvector.
     *
     * Uses the <=> cosine distance operator backed by an HNSW index.
     *
     * @param  int    $adminId
     * @param  int    $chatbotId
     * @param  string $queryEmbeddingJson  JSON array string (e.g. "[0.002345, -0.015678, ...]") cast to vector
     * @param  int    $topK
     * @return array  Array of ['chunk_text' => string, 'score' => float, 'id' => int]
     *                score = cosine similarity (1 - distance), higher is more similar
     */
    public static function searchByEmbedding(int $adminId, int $chatbotId, string $queryEmbeddingJson, int $topK = 5): array
    {
        $sql = 'SELECT id, chunk_text, embedding <=> ?::vector AS distance '
             . 'FROM document_chunks '
             . 'WHERE admin_id = ? AND chatbot_id = ? '
             . 'ORDER BY embedding <=> ?::vector '
             . 'LIMIT ?';

        $stmt = self::db()->prepare($sql);
        $stmt->execute([$queryEmbeddingJson, $adminId, $chatbotId, $queryEmbeddingJson, $topK]);

        $results = [];
        while ($row = $stmt->fetch()) {
            $distance = (float) $row['distance'];
            // Convert cosine distance to similarity: sim = 1 - distance (distance is 0 for identical, 1 for orthogonal)
            $score = 1.0 - $distance;

            $results[] = [
                'chunk_text' => $row['chunk_text'],
                'score'      => round($score, 6),
                'id'         => (int) $row['id'],
            ];
        }

        // Results are already ordered by distance ASC (closest first)
        return $results;
    }
}
