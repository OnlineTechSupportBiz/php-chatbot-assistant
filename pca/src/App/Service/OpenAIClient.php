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
 * OpenAIClient — HTTP wrapper for OpenAI API (embeddings and chat).
 *
 * Currently focused on text-embedding-3-small for RAG embedding.
 */
class OpenAIClient
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';
    private string $embeddingModel = 'text-embedding-3-small';

    public function __construct(string $apiKey, string $baseUrl = '')
    {
        $this->apiKey = $apiKey;
        if ($baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '/');
        }
    }

    /**
     * Override the embeddings model id (e.g. text-embedding-3-large on a
     * self-hosted provider). Affects embedBatch/embed/embedBatchAsJsonArray.
     */
    public function setEmbeddingModel(string $model): void
    {
        $model = trim($model);
        if ($model !== '') {
            $this->embeddingModel = $model;
        }
    }

    /**
     * Resolve the embedding endpoint/key/model, applying the JS port's fallback
     * chain: an empty embedding_* falls back to the LLM provider, then to the
     * OpenAI defaults — so one self-hosted endpoint can serve both.
     */
    public static function resolveEmbeddingConfig(array $settings): array
    {
        $llmBase = self::validateProviderBaseUrl($settings['llm_base_url'] ?? null);
        return [
            'base_url' => self::validateProviderBaseUrl($settings['embedding_base_url'] ?? null)
                ?? $llmBase
                ?? 'https://api.openai.com/v1',
            'api_key'  => trim((string) ($settings['embedding_api_key'] ?? ''))
                ?: (string) ($settings['openai_api_key'] ?? ''),
            'model'    => trim((string) ($settings['embedding_model'] ?? ''))
                ?: 'text-embedding-3-small',
        ];
    }

    /**
     * Provider base URLs come from the tenant's own settings and are fetched
     * server-side, so an unvalidated value turns the app into a request
     * forwarder to anything the host can reach: cloud metadata
     * (169.254.169.254), loopback, or any RFC1918 address. Only a public
     * http(s) host is accepted; private / link-local / loopback targets are
     * refused. Null means "no custom URL configured".
     *
     * Port of the JS port's validateProviderBaseUrl (lib/openai/client.ts).
     */
    public static function validateProviderBaseUrl(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        $parts = parse_url($value);
        if ($parts === false || empty($parts['host']) || !isset($parts['scheme'])
            || !in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new \RuntimeException('Provider URL must be a valid absolute http(s) URL (e.g. https://api.openai.com/v1).');
        }
        $host = strtolower($parts['host']);
        if (self::isBlockedHost($host)) {
            throw new \RuntimeException('Provider URL must point at a public host (loopback and private addresses are not allowed).');
        }
        return rtrim($value, '/');
    }

    private static function isBlockedHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            return true;
        }
        // IPv4: loopback, link-local, RFC1918, 0.0.0.0/8, this-network.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // IPv6 literal: refuse the private/reserved ranges the same way.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        // Bare IP embedded in an IPv6-mapped form or with a weird suffix.
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $host) || str_contains($host, ':')) {
            return true;
        }
        return false;
    }

    /**
     * Build a chat client from the user's ordered LLM model list, prioritising
     * the selected model (matched by name) and failing over through the rest in
     * order. Falls back to a single legacy client when the list is empty (the
     * pre-model-list behaviour, driven by openai_api_key / llm_base_url).
     *
     * Port of the JS port's buildLlmChatClient (lib/openai/client.ts).
     *
     * @param array<int, array{name: string, base_url: string, api_key: string, model: string}> $models
     */
    public static function buildLlmChatClient(
        array $models,
        ?string $selectedName,
        string $legacyApiKey,
        ?string $legacyBaseUrl = null
    ): FailoverChat|OpenAIClient {
        $models = array_values(array_filter($models, function ($m) {
            return is_array($m)
                && trim((string) ($m['model'] ?? '')) !== ''
                && (trim((string) ($m['api_key'] ?? '')) !== '' || trim((string) ($m['base_url'] ?? '')) !== '');
        }));
        if ($models === []) {
            return new OpenAIClient($legacyApiKey, self::validateProviderBaseUrl($legacyBaseUrl) ?? '');
        }
        $selected = null;
        foreach ($models as $i => $m) {
            if ($selectedName !== null && $selectedName !== '' && $m['name'] === $selectedName) {
                $selected = $i;
                break;
            }
        }
        if ($selected !== null) {
            $picked = $models[$selected];
            unset($models[$selected]);
            array_unshift($models, $picked);
        }
        $entries = [];
        foreach ($models as $m) {
            $entries[] = [
                'client' => new OpenAIClient(
                    trim((string) $m['api_key']),
                    self::validateProviderBaseUrl($m['base_url'] ?? null) ?? 'https://api.openai.com/v1'
                ),
                'model'  => trim((string) $m['model']),
                'name'   => trim((string) $m['name']),
            ];
        }
        return new FailoverChat($entries);
    }

    /**
     * Get embeddings for an array of texts using text-embedding-3-small.
     *
     * @param  string[] $texts
     * @param  int      $dimensions  Desired embedding dimensions (default 1536)
     * @return array[]               Array of ['index' => int, 'embedding' => string (binary float32)]
     * @throws \RuntimeException
     */
    public function embedBatch(array $texts, int $dimensions = 1536): array
    {
        if (empty($texts)) {
            return [];
        }

        $url = $this->baseUrl . '/embeddings';

        $body = json_encode([
            'model'      => $this->embeddingModel,
            'input'      => $texts,
            'dimensions' => $dimensions,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'OpenAI embedding failed (HTTP ' . $httpCode . '): ' . ($error ?: substr($response, 0, 500))
            );
        }

        $data = json_decode($response, true);

        if (!isset($data['data'])) {
            throw new \RuntimeException('OpenAI embedding: unexpected response structure');
        }

        $results = [];
        foreach ($data['data'] as $item) {
            $results[] = [
                'index'     => $item['index'],
                'embedding' => $this->floatArrayToBinary($item['embedding']),
            ];
        }

        return $results;
    }

    /**
     * Get a single embedding for a text string.
     *
     * @param  string $text
     * @return string  Binary float32 embedding
     */
    public function embed(string $text): string
    {
        $results = $this->embedBatch([$text]);
        return $results[0]['embedding'] ?? '';
    }

    /**
     * Generate embedding as a JSON array string compatible with pgvector ::vector cast.
     *
     * Example: "[0.002345, -0.015678, ...]"
     *
     * @param  string $text
     * @return string  JSON array of floats (e.g. "[0.002345, -0.015678]")
     */
    public function embedAsJsonArray(string $text): string
    {
        return $this->floatArrayToJsonArray(
            $this->getRawFloatArray($text)
        );
    }

    /**
     * Generate embeddings for multiple texts, returning JSON array strings compatible with pgvector.
     *
     * @param  string[] $texts
     * @return string[] Array of JSON array strings
     */
    public function embedBatchAsJsonArray(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $body = json_encode([
            'model' => $this->embeddingModel,
            'input' => $texts,
        ]);

        $url = $this->baseUrl . '/embeddings';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'OpenAI embedding failed (HTTP ' . $httpCode . '): ' . ($error ?: substr($response, 0, 500))
            );
        }

        $data = json_decode($response, true);
        if (!isset($data['data'])) {
            throw new \RuntimeException('OpenAI embedding: unexpected response structure');
        }

        $results = array_fill(0, count($texts), '');
        foreach ($data['data'] as $item) {
            $idx = $item['index'] ?? 0;
            $results[$idx] = $this->floatArrayToJsonArray($item['embedding']);
        }

        return $results;
    }

    /**
     * Get the raw float array for a single text via the embedding API.
     *
     * @return float[]
     */
    private function getRawFloatArray(string $text): array
    {
        $body = json_encode([
            'model' => $this->embeddingModel,
            'input' => $text,
        ]);

        $url = $this->baseUrl . '/embeddings';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'OpenAI embedding failed (HTTP ' . $httpCode . '): ' . ($error ?: substr($response, 0, 500))
            );
        }

        $data = json_decode($response, true);
        if (!isset($data['data'][0]['embedding'])) {
            throw new \RuntimeException('OpenAI embedding: unexpected response structure');
        }

        return $data['data'][0]['embedding'];
    }

    /**
     * Call OpenAI Chat Completions API.
     *
     * @param  array $messages  Array of message objects (role + content)
     * @param  array $overrides Optional overrides for model, temperature, max_tokens, etc.
     * @return array{content: string, total_tokens: int}  The assistant's reply and token usage
     * @throws \RuntimeException
     */
    public function chatCompletion(array $messages, array $overrides = []): array
    {
        $url = $this->baseUrl . '/chat/completions';

        $body = json_encode(array_merge([
            'model'       => 'gpt-4.1-mini',
            'messages'    => $messages,
            'temperature' => 0.0,
            'max_tokens'  => 1024,
        ], $overrides));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'OpenAI chat completion failed (HTTP ' . $httpCode . '): ' . ($error ?: substr($response, 0, 500))
            );
        }

        $data = json_decode($response, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \RuntimeException('OpenAI chat: unexpected response structure');
        }

        return [
            'content'      => $data['choices'][0]['message']['content'],
            'total_tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
        ];
    }

    /**
     * Convert an array of floats to packed binary (little-endian float32).
     */
    private function floatArrayToBinary(array $floats): string
    {
        $packed = '';
        foreach ($floats as $f) {
            $packed .= pack('f', $f);
        }
        return $packed;
    }

    /**
     * Convert an array of floats to a JSON array string for pgvector ::vector cast.
     *
     * Example: "[0.002345, -0.015678]"
     */
    private function floatArrayToJsonArray(array $floats): string
    {
        $parts = [];
        foreach ($floats as $f) {
            $parts[] = sprintf('%.8f', $f);
        }
        return '[' . implode(',', $parts) . ']';
    }
}
