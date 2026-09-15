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

namespace App\Service;

use App\Model\Document;
use App\Model\DocumentChunk;

/**
 * TraditionalRagStrategy — vector embedding similarity search.
 *
 * This is the existing RAG flow: embed the user's question, search via pgvector
 * cosine distance, return the top chunk texts as context.
 *
 * The implementation is extracted from ChatController::processQuery
 * and unchanged in behaviour.
 */
class TraditionalRagStrategy implements RetrievalStrategy
{
    private OpenAIClient $openai;

    public function __construct(OpenAIClient $openai)
    {
        $this->openai = $openai;
    }

    public function retrieve(int $adminId, int $chatbotId, string $query, array $chatbot, array $options = []): RetrievalResult
    {
        // Check for indexed documents
        $indexedDocCount = Document::countIndexedByChatbot($adminId, $chatbotId);
        if ($indexedDocCount <= 0) {
            return new RetrievalResult();
        }

        // Embed the user's question as a JSON array string for pgvector ::vector cast
        $queryEmbedding = $this->openai->embedAsJsonArray($query);

        // Search for relevant chunks via pgvector cosine distance (<=> operator)
        $topK = (int) ($options['top_k'] ?? 5);
        $topChunks = DocumentChunk::searchByEmbedding($adminId, $chatbotId, $queryEmbedding, $topK);

        if (empty($topChunks)) {
            return new RetrievalResult();
        }

        $chunksUsed = count($topChunks);
        $ragContext = implode("\n\n---\n\n", array_map(
            fn($c) => $c['chunk_text'],
            $topChunks
        ));

        return new RetrievalResult(
            context: $ragContext,
            source: 'traditional_rag',
            chunksUsed: $chunksUsed,
            metadata: ['scores' => array_map(fn($c) => $c['score'], $topChunks)]
        );
    }
}
