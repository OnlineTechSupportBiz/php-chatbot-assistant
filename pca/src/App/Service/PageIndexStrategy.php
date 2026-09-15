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
use App\Model\DocumentPageIndexNode;

/**
 * PageIndexStrategy — vectorless, LLM-reasoning-based document retrieval.
 *
 * Instead of chunking and embedding documents, PageIndex parses documents into
 * a hierarchical tree of sections (like a table of contents). At query time,
 * the LLM receives the tree outline (headings only) and navigates through it
 * to identify which sections are relevant to the user's question. The content
 * of those sections is then fetched and returned as context.
 *
 * This mimics how a human skims a table of contents to find the right section.
 */
class PageIndexStrategy implements RetrievalStrategy
{
    private OpenAIClient $openai;

    public function __construct(OpenAIClient $openai)
    {
        $this->openai = $openai;
    }

    public function retrieve(int $adminId, int $chatbotId, string $query, array $chatbot, array $options = []): RetrievalResult
    {
        // Check if there are any page-indexed documents
        $indexedDocCount = DocumentPageIndexNode::countIndexedDocumentsByChatbot($adminId, $chatbotId);
        if ($indexedDocCount <= 0) {
            return new RetrievalResult();
        }

        // 1. Fetch the tree outline (headings only, no content to minimise tokens)
        $outlineRows = DocumentPageIndexNode::getOutlineByChatbot($adminId, $chatbotId);
        if (empty($outlineRows)) {
            return new RetrievalResult();
        }

        // 2. Build the hierarchical tree and render as a text outline
        $tree = DocumentPageIndexNode::buildTree($outlineRows);
        $outlineText = DocumentPageIndexNode::renderOutline($tree);

        // 3. Ask the LLM to navigate the tree and select relevant sections
        $selectedNodeIds = $this->selectRelevantSections($query, $outlineText, $chatbot, $options);

        if (empty($selectedNodeIds)) {
            return new RetrievalResult();
        }

        // 4. Fetch the full content of selected nodes
        $contentRows = DocumentPageIndexNode::getContentByIds($adminId, $chatbotId, $selectedNodeIds);

        if (empty($contentRows)) {
            return new RetrievalResult();
        }

        // 5. Format the context
        $contextParts = [];
        foreach ($contentRows as $row) {
            $heading = $row['heading'] ?? '';
            $content = $row['content'] ?? '';
            if ($content === '') {
                continue;
            }
            $contextParts[] = $heading ? "## {$heading}\n\n{$content}" : $content;
        }

        if (empty($contextParts)) {
            return new RetrievalResult();
        }

        $ragContext = implode("\n\n---\n\n", $contextParts);

        return new RetrievalResult(
            context: $ragContext,
            source: 'page_index',
            chunksUsed: count($selectedNodeIds),
            metadata: ['node_ids' => $selectedNodeIds]
        );
    }

    /**
     * Use the LLM to navigate a document outline and select relevant section IDs.
     *
     * @param  string $query       The user's question
     * @param  string $outlineText Rendered outline text with [id:N] markers
     * @param  array  $chatbot     Chatbot config
     * @param  array  $options     Options (model override, etc.)
     * @return int[]               Selected node IDs
     */
    private function selectRelevantSections(string $query, string $outlineText, array $chatbot, array $options): array
    {
        $modelConfig = !empty($chatbot['model_config']) ? json_decode($chatbot['model_config'], true) : [];
        $model = $modelConfig['model'] ?? 'gpt-4o-mini';
        $maxNodes = (int) ($options['max_nodes'] ?? 5);

        $systemPrompt = <<<PROMPT
You are a document retrieval assistant. Your job is to navigate a document's table
of contents to find the sections most relevant to the user's question.

Below is the document outline. Each entry has an [id:N] marker identifying the section.
The outline is hierarchical — indentation shows parent-child relationships.

Read the user's question and mentally simulate how a human would skim this table of
contents to find the answer. Then return ONLY a JSON array of the most relevant
section IDs that would contain the answer.

Rules:
- Return a JSON array of integers, e.g. [123, 456, 789]
- Return an empty array [] if NO section seems relevant
- Do NOT include parent sections that have no content of their own
- Prefer leaf/section nodes over root nodes
- Return at most {$maxNodes} IDs
- Return ONLY the JSON array, no other text or explanation

Document Outline:
{$outlineText}
PROMPT;

        try {
            $chatResult = $this->openai->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "User question: {$query}"],
            ], [
                'model'       => $model,
                'temperature' => 0.1,
                'max_tokens'  => 500,
            ]);
            $reply = $chatResult['content'];

            $reply = trim($reply);

            // Try to extract a JSON array from the response
            if (preg_match('/\[[\d,\s]*\]/', $reply, $matches)) {
                $ids = json_decode($matches[0], true);
                if (is_array($ids)) {
                    return array_map('intval', array_slice($ids, 0, $maxNodes));
                }
            }

            // Fallback: try parsing the whole reply as JSON
            $ids = json_decode($reply, true);
            if (is_array($ids)) {
                return array_map('intval', array_slice($ids, 0, $maxNodes));
            }
        } catch (\Throwable $e) {
            // API failure — silently return empty
        }

        return [];
    }
}
