<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\OpenAIClient;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OpenAIClient — HTTP wrapper for OpenAI API.
 *
 * Covers: construction, embed, embedBatch, embedAsJsonArray,
 * chatCompletion.
 *
 * These tests verify the wrapper logic doesn't have syntax errors
 * or structural issues. Actual HTTP calls require a real API key.
 */
class OpenAIClientTest extends TestCase
{
    private OpenAIClient $client;

    protected function setUp(): void
    {
        $this->client = new OpenAIClient('test-api-key');
    }

    public function test_construction(): void
    {
        $this->assertInstanceOf(OpenAIClient::class, $this->client);
    }

    public function test_embedBatch_empty_returns_empty_array(): void
    {
        $result = $this->client->embedBatch([]);
        $this->assertSame([], $result);
    }

    public function test_embedBatchAsJsonArray_empty_returns_empty_array(): void
    {
        $result = $this->client->embedBatchAsJsonArray([]);
        $this->assertSame([], $result);
    }

    public function test_embed_throws_without_api(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->embed('Hello world');
    }

    public function test_embedAsJsonArray_throws_without_api(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->embedAsJsonArray('Hello world');
    }

    public function test_embedBatch_throws_without_api(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->embedBatch(['Hello', 'World']);
    }

    public function test_chatCompletion_throws_without_api(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->chatCompletion([
            ['role' => 'user', 'content' => 'Hello'],
        ]);
    }

    public function test_chatCompletion_with_overrides(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->chatCompletion(
            [['role' => 'user', 'content' => 'Hi']],
            ['model' => 'gpt-4', 'temperature' => 0.5]
        );
    }
}
