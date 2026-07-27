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

/**
 * RetrievalEngine — selects and runs the appropriate retrieval strategy for a chatbot.
 *
 * Reads the chatbot's `retrieval_strategy` setting and delegates to the correct
 * implementation. This is the single entry point for all document retrieval,
 * keeping the rest of the app strategy-agnostic.
 */
class RetrievalEngine
{
    private OpenAIClient $openai;

    /** @var array<string, RetrievalStrategy> Cached strategy instances */
    private array $strategies = [];

    public function __construct(OpenAIClient $openai)
    {
        $this->openai = $openai;
    }

    /**
     * Retrieve context for a user query using the chatbot's configured strategy.
     *
     * @param  int    $adminId    Admin scope
     * @param  array  $chatbot    Full chatbot row (must include retrieval_strategy)
     * @param  string $query      The user's question
     * @param  array  $options    Optional overrides passed to the strategy
     * @return RetrievalResult
     */
    public function retrieve(int $adminId, array $chatbot, string $query, array $options = []): RetrievalResult
    {
        $chatbotId = (int) $chatbot['id'];
        $strategy = $this->getStrategy($chatbot);

        return $strategy->retrieve($adminId, $chatbotId, $query, $chatbot, $options);
    }

    /**
     * Get the appropriate strategy for a chatbot, caching instances.
     */
    private function getStrategy(array $chatbot): RetrievalStrategy
    {
        $strategy = $chatbot['retrieval_strategy'] ?? 'traditional_rag';

        if (!isset($this->strategies[$strategy])) {
            $this->strategies[$strategy] = match ($strategy) {
                'page_index'      => new PageIndexStrategy($this->openai),
                'traditional_rag' => new TraditionalRagStrategy($this->openai),
                default            => new TraditionalRagStrategy($this->openai),
            };
        }

        return $this->strategies[$strategy];
    }
}
