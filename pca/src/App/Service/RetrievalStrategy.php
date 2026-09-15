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
 * RetrievalStrategy — interface for document retrieval strategies.
 *
 * Each strategy knows how to take a user's query and the chatbot context,
 * then return relevant document content.
 */
interface RetrievalStrategy
{
    /**
     * Retrieve relevant context for a user query.
     *
     * @param  int    $adminId    Admin (tenant) scope
     * @param  int    $chatbotId  Chatbot scope
     * @param  string $query      The user's message
     * @param  array  $chatbot    Full chatbot row (for config reads)
     * @param  array  $options    Optional strategy-specific overrides
     * @return RetrievalResult
     */
    public function retrieve(int $adminId, int $chatbotId, string $query, array $chatbot, array $options = []): RetrievalResult;
}
