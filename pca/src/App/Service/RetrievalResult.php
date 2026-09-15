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
 * RetrievalResult — value object returned by any RetrievalStrategy.
 *
 * Carries the context text to inject into the system prompt, plus metadata
 * about how the retrieval was performed.
 */
class RetrievalResult
{
    private string $context;
    private string $source;       // 'traditional_rag' or 'page_index'
    private int    $chunksUsed;
    private array  $metadata;     // Strategy-specific details (node IDs, scores, etc.)

    public function __construct(
        string $context = '',
        string $source = 'llm_only',
        int    $chunksUsed = 0,
        array  $metadata = []
    ) {
        $this->context    = $context;
        $this->source     = $source;
        $this->chunksUsed = $chunksUsed;
        $this->metadata   = $metadata;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getChunksUsed(): int
    {
        return $this->chunksUsed;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function hasContext(): bool
    {
        return $this->context !== '';
    }
}
