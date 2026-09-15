<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\RetrievalResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RetrievalResult — value object for RAG retrieval results.
 *
 * Covers: construction, getters, defaults, hasContext(), metadata.
 */
class RetrievalResultTest extends TestCase
{
    public function test_default_construction(): void
    {
        $result = new RetrievalResult();
        $this->assertSame('', $result->getContext());
        $this->assertSame('llm_only', $result->getSource());
        $this->assertSame(0, $result->getChunksUsed());
        $this->assertSame([], $result->getMetadata());
        $this->assertFalse($result->hasContext());
    }

    public function test_construction_with_values(): void
    {
        $result = new RetrievalResult(
            context: 'Some relevant context text',
            source: 'traditional_rag',
            chunksUsed: 3,
            metadata: ['scores' => [0.95, 0.87, 0.76]]
        );
        $this->assertSame('Some relevant context text', $result->getContext());
        $this->assertSame('traditional_rag', $result->getSource());
        $this->assertSame(3, $result->getChunksUsed());
        $this->assertSame(['scores' => [0.95, 0.87, 0.76]], $result->getMetadata());
        $this->assertTrue($result->hasContext());
    }

    public function test_page_index_source(): void
    {
        $result = new RetrievalResult(
            context: "## Introduction\n\nDocument content here",
            source: 'page_index',
            chunksUsed: 2,
            metadata: ['node_ids' => [101, 105]]
        );
        $this->assertSame('page_index', $result->getSource());
        $this->assertSame(2, $result->getChunksUsed());
        $this->assertSame([101, 105], $result->getMetadata()['node_ids']);
    }

    public function test_hasContext_empty_string(): void
    {
        $result = new RetrievalResult(context: '');
        $this->assertFalse($result->hasContext());
    }

    public function test_hasContext_with_content(): void
    {
        $result = new RetrievalResult(context: 'Some content');
        $this->assertTrue($result->hasContext());
    }

    public function test_getters_return_correct_types(): void
    {
        $result = new RetrievalResult();
        $this->assertIsString($result->getContext());
        $this->assertIsString($result->getSource());
        $this->assertIsInt($result->getChunksUsed());
        $this->assertIsArray($result->getMetadata());
    }
}
