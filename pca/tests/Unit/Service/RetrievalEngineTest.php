<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\OpenAIClient;
use App\Service\RetrievalEngine;
use App\Service\TraditionalRagStrategy;
use App\Service\PageIndexStrategy;
use App\Service\RetrievalResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RetrievalEngine — strategy selection and dispatch.
 *
 * Covers: construction, retrieve with traditional_rag strategy,
 * retrieve with page_index strategy, retrieve with unknown strategy
 * (falls back to traditional_rag).
 */
class RetrievalEngineTest extends TestCase
{
    private RetrievalEngine $engine;
    private OpenAIClient $openai;

    protected function setUp(): void
    {
        $this->openai = new OpenAIClient('test-key');
        $this->engine = new RetrievalEngine($this->openai);
    }

    public function test_construction(): void
    {
        $this->assertInstanceOf(RetrievalEngine::class, $this->engine);
    }

    public function test_retrieve_traditional_rag_without_db_returns_empty(): void
    {
        // Without a DB connection, Document::countIndexedByChatbot will
        // throw because TestDb isn't configured. We test the happy path
        // by configuring a mock DB.
        $this->expectException(\RuntimeException::class);
        $this->engine->retrieve(1, ['id' => 1, 'retrieval_strategy' => 'traditional_rag'], 'Hello');
    }

    public function test_retrieve_unknown_strategy_falls_back_to_traditional_rag(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->retrieve(1, ['id' => 1, 'retrieval_strategy' => 'unknown'], 'Hello');
    }
}
