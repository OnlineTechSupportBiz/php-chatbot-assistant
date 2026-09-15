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
 * LlamaParseClient — HTTP wrapper for LlamaCloud Parse REST API.
 *
 * Endpoints:
 *   POST /api/v1/beta/files        — upload a file
 *   POST /api/v2/parse             — kick off a parse job
 *   GET  /api/v2/parse/{job_id}    — poll for result
 */
class LlamaParseClient
{
    private string $apiKey;
    private string $baseUrl = 'https://api.cloud.llamaindex.ai';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Upload a file to LlamaCloud.
     *
     * @param  string $filePath   Absolute path to the file
     * @param  string $mimeType   MIME type (e.g. application/pdf)
     * @return string             File ID on success
     * @throws \RuntimeException  On failure
     */
    public function uploadFile(string $filePath, string $mimeType): string
    {
        $url = $this->baseUrl . '/api/v1/beta/files';

        $curlFile = new \CURLFile($filePath, $mimeType, basename($filePath));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => [
                'purpose' => 'parse',
                'file'    => $curlFile,
            ],
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'LlamaParse upload failed (HTTP ' . $httpCode . '): ' . ($error ?: $response)
            );
        }

        $data = json_decode($response, true);
        if (empty($data['id'])) {
            throw new \RuntimeException('LlamaParse upload: missing file ID in response');
        }

        return $data['id'];
    }

    /**
     * Start a parse job for a previously uploaded file.
     *
     * @param  string $fileId  File ID from uploadFile()
     * @param  string $tier    "agentic", "cost_effective", "fast", or "agentic_plus"
     * @return array           ['job_id' => string, 'status' => string]
     * @throws \RuntimeException
     */
    public function startParseJob(string $fileId, string $tier = 'cost_effective'): array
    {
        $url = $this->baseUrl . '/api/v2/parse';

        $body = json_encode([
            'file_id' => $fileId,
            'tier'    => $tier,
            'version' => 'latest',
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
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            throw new \RuntimeException(
                'LlamaParse start job failed (HTTP ' . $httpCode . '): ' . ($error ?: $response)
            );
        }

        $data = json_decode($response, true);
        if (empty($data['id'])) {
            throw new \RuntimeException('LlamaParse start job: missing job ID in response');
        }

        return [
            'job_id' => $data['id'],
            'status' => $data['status'] ?? 'PENDING',
        ];
    }

    /**
     * Poll for the result of a parse job.
     *
     * @param  string $jobId
     * @return array   ['status' => string, 'markdown' => string|null, 'error_message' => string|null]
     */
    public function pollJob(string $jobId): array
    {
        $url = $this->baseUrl . '/api/v2/parse/' . urlencode($jobId) . '?expand=markdown';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return [
                'status'        => 'FAILED',
                'markdown'      => null,
                'error_message' => 'HTTP ' . $httpCode,
            ];
        }

        $data = json_decode($response, true);

        $status       = $data['job']['status'] ?? 'PENDING';
        $errorMessage = $data['job']['error_message'] ?? null;

        // Extract markdown — the API returns it under result / pages
        if (isset($data['markdown']) && is_string($data['markdown'])) {
            $markdown = $data['markdown'];
        } elseif (isset($data['result']['markdown'])) {
            $markdown = $data['result']['markdown'];
        } elseif (isset($data['markdown']['pages']) && is_array($data['markdown']['pages'])) {
            // Concatenate page markdowns
            $parts = [];
            foreach ($data['markdown']['pages'] as $page) {
                if (isset($page['markdown'])) {
                    $parts[] = $page['markdown'];
                }
            }
            $markdown = implode("\n\n", $parts);
        }

        // Also check for text/result fields
        if (empty($markdown)) {
            $markdown = $data['result']['text'] ?? $data['text'] ?? '';
        }

        return [
            'status'        => $status,
            'markdown'      => $markdown ?: null,
            'error_message' => $errorMessage,
        ];
    }

    /**
     * Convenience: upload → start → poll until complete.
     *
     * @param  string   $filePath
     * @param  string   $mimeType
     * @param  string   $tier
     * @param  int      $maxPollSeconds
     * @return array    ['job_id' => string, 'markdown' => string|null, 'status' => string, 'error' => string|null]
     */
    public function parseFile(string $filePath, string $mimeType, string $tier = 'cost_effective', int $maxPollSeconds = 600): array
    {
        $fileId = $this->uploadFile($filePath, $mimeType);

        $job = $this->startParseJob($fileId, $tier);

        $start = time();
        $jobId = $job['job_id'];

        while (true) {
            if ((time() - $start) > $maxPollSeconds) {
                return [
                    'job_id'   => $jobId,
                    'markdown' => null,
                    'status'   => 'TIMEOUT',
                    'error'    => 'Poll timed out after ' . $maxPollSeconds . 's',
                ];
            }

            sleep(2);
            $result = $this->pollJob($jobId);

            if ($result['status'] === 'COMPLETED') {
                return [
                    'job_id'   => $jobId,
                    'markdown' => $result['markdown'],
                    'status'   => 'COMPLETED',
                    'error'    => null,
                ];
            }

            if ($result['status'] === 'FAILED') {
                return [
                    'job_id'   => $jobId,
                    'markdown' => null,
                    'status'   => 'FAILED',
                    'error'    => $result['error_message'] ?? 'Parse job failed',
                ];
            }

            // PENDING or RUNNING — keep polling
        }
    }
}
