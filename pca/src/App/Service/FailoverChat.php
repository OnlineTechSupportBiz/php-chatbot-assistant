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
 * FailoverChat — tries each configured model in order, failing over to the next
 * on error (multi-model failover). Port of the JS port's FailoverChat class
 * (lib/openai/client.ts): each entry carries its own client (base URL + key)
 * and the model string it should call.
 */
class FailoverChat
{
    /** @var array<int, array{client: OpenAIClient, model: string, name: string}> */
    private array $entries;

    /** @param array<int, array{client: OpenAIClient, model: string, name: string}> $entries */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /**
     * The first configured client — used where a single OpenAI-compatible
     * client is needed (e.g. embeddings for retrieval strategies).
     */
    public function primaryClient(): OpenAIClient
    {
        if ($this->entries === []) {
            throw new \RuntimeException('No LLM providers configured');
        }
        return $this->entries[0]['client'];
    }

    /**
     * Run a chat completion, falling through the configured models in order.
     *
     * @param  array $messages  OpenAI message array
     * @param  array $overrides temperature / max_tokens / response_format etc.
     * @return array ['content' => string, 'total_tokens' => int, 'model_name' => string]
     *
     * @throws \RuntimeException when every entry fails (or none are configured)
     */
    public function chatCompletion(array $messages, array $overrides = []): array
    {
        $lastErr = new \RuntimeException('No LLM model configured');
        foreach ($this->entries as $entry) {
            try {
                $result = $entry['client']->chatCompletion($messages, array_merge($overrides, [
                    'model' => $entry['model'],
                ]));
                $result['model_name'] = $entry['name'];
                return $result;
            } catch (\Throwable $e) {
                $lastErr = $e;
            }
        }
        throw $lastErr;
    }
}