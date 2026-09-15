<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\ChunkingService;
use App\Service\OpenAIClient;
use App\Service\LlamaParseClient;
use App\Service\TraditionalRagStrategy;
use App\Service\PageIndexStrategy;
use App\Service\PageIndexBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for remaining service classes.
 *
 * Covers construction of all services and basic structural tests.
 */
class ServiceConstructionTest extends TestCase
{
    public function test_chunking_service_constructs(): void
    {
        $service = new ChunkingService();
        $this->assertInstanceOf(ChunkingService::class, $service);
    }

    public function test_llamaParseClient_constructs(): void
    {
        $client = new LlamaParseClient('test-api-key');
        $this->assertInstanceOf(LlamaParseClient::class, $client);
    }

    public function test_pageIndexBuilder_constructs(): void
    {
        $builder = new PageIndexBuilder();
        $this->assertInstanceOf(PageIndexBuilder::class, $builder);
    }

    public function test_traditionalRagStrategy_constructs(): void
    {
        $strategy = new TraditionalRagStrategy(new OpenAIClient('test'));
        $this->assertInstanceOf(TraditionalRagStrategy::class, $strategy);
    }

    public function test_pageIndexStrategy_constructs(): void
    {
        $strategy = new PageIndexStrategy(new OpenAIClient('test'));
        $this->assertInstanceOf(PageIndexStrategy::class, $strategy);
    }
}
