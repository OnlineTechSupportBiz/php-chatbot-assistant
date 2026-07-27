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

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
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
            'model'      => 'text-embedding-3-small',
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
            'model' => 'text-embedding-3-small',
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
            'model' => 'text-embedding-3-small',
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
     * Send a chat completion request to OpenAI.
     *
     * @param  array  $messages   Array of ['role' => 'system'|'user'|'assistant', 'content' => string]
     * @param  array  $overrides  Optional overrides for model, temperature, max_tokens, etc.
     * @return string             The assistant's response text
     * @throws \RuntimeException
     */
    public function chatCompletion(array $messages, array $overrides = []): string
    {
        $url = $this->baseUrl . '/chat/completions';

        $body = json_encode(array_merge([
            'model'       => 'gpt-4o-mini',
            'messages'    => $messages,
            'temperature' => 0.7,
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

        return $data['choices'][0]['message']['content'];
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
