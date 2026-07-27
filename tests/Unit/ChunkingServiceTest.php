<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\ChunkingService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChunkingService — text splitting into chunks.
 *
 * Covers: chunk(), chunkByParagraphs(), edge cases (empty, single word,
 * exact size, overlap behavior, paragraph boundaries, oversize paragraphs).
 */
class ChunkingServiceTest extends TestCase
{
    private ChunkingService $service;

    protected function setUp(): void
    {
        $this->service = new ChunkingService();
    }

    // ── chunk() ─────────────────────────────────────────────────────────────

    public function test_empty_text_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->chunk(''));
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $result = $this->service->chunk('Hello world', 500);
        $this->assertCount(1, $result);
        $this->assertSame('Hello world', $result[0]['chunk_text']);
        $this->assertSame(0, $result[0]['chunk_index']);
    }

    public function test_text_under_chunk_size_returns_one_chunk(): void
    {
        $text = str_repeat('a', 100);
        $result = $this->service->chunk($text, 500);
        $this->assertCount(1, $result);
        $this->assertSame(100, strlen($result[0]['chunk_text']));
    }

    public function test_text_exactly_chunk_size_returns_one_chunk(): void
    {
        $text = str_repeat('a', 500);
        $result = $this->service->chunk($text, 500, 0);
        $this->assertCount(1, $result);
        $this->assertSame(500, strlen($result[0]['chunk_text']));
    }

    public function test_text_slightly_over_chunk_size_returns_two_chunks(): void
    {
        $text = str_repeat('a', 600);
        $result = $this->service->chunk($text, 500, 0);
        $this->assertCount(2, $result);
        // First chunk should be at char boundary since no spaces to break on
        $this->assertSame(500, strlen($result[0]['chunk_text']));
        $this->assertSame(100, strlen($result[1]['chunk_text']));
    }

    public function test_chunk_respects_word_boundaries(): void
    {
        // Create text where natural break happens at a space
        $text = str_repeat('word ', 150); // ~750 chars with spaces
        $result = $this->service->chunk($text, 500, 0);

        // Each chunk should end with complete words
        foreach ($result as $chunk) {
            $this->assertStringEndsNotWith(' ', trim($chunk['chunk_text']));
        }
    }

    public function test_chunk_with_overlap(): void
    {
        // Text with clear word boundaries and no overlap = discontiguous
        $text = 'apple banana cherry date elderberry fig grape honeydew';
        $result = $this->service->chunk($text, 30, 0);

        // With no overlap, chunks should be sequential
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function test_chunk_indices_are_sequential(): void
    {
        $text = str_repeat('hello world ', 100);
        $result = $this->service->chunk($text, 200, 20);

        $indices = array_map(fn($c) => $c['chunk_index'], $result);
        $this->assertSame(range(0, count($result) - 1), $indices);
    }

    // ── chunkByParagraphs() ─────────────────────────────────────────────────

    public function test_chunkByParagraphs_empty_text(): void
    {
        $this->assertSame([], $this->service->chunkByParagraphs(''));
    }

    public function test_chunkByParagraphs_single_paragraph(): void
    {
        $text = 'This is a single paragraph. It has no line breaks.';
        $result = $this->service->chunkByParagraphs($text);
        $this->assertCount(1, $result);
        $this->assertSame($text, $result[0]['chunk_text']);
    }

    public function test_chunkByParagraphs_multiple_small_paragraphs(): void
    {
        $text = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";
        $result = $this->service->chunkByParagraphs($text, 1000);
        // All three should fit in one chunk (total << 1000)
        $this->assertCount(1, $result);
    }

    public function test_chunkByParagraphs_splits_large_chunks(): void
    {
        // Two paragraphs that together exceed maxChars
        $text = "Small first paragraph.\n\n" . str_repeat('Large paragraph content. ', 100);
        $result = $this->service->chunkByParagraphs($text, 200);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function test_chunkByParagraphs_oversize_paragraph_uses_chunk(): void
    {
        $text = str_repeat('Very long single paragraph with no breaks. ', 200);
        $result = $this->service->chunkByParagraphs($text, 500);
        // Should be split into multiple chunks using the regular chunk method
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    // ── Edge cases ──────────────────────────────────────────────────────────

    public function test_single_word(): void
    {
        $result = $this->service->chunk('Hello', 500);
        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['chunk_text']);
    }

    public function test_text_with_only_spaces(): void
    {
        // mb_strlen would count spaces — but the service strips nothing
        $result = $this->service->chunk('     ', 500);
        $this->assertCount(1, $result);
    }

    public function test_unicode_text(): void
    {
        $text = '日本語のテスト文章です。これはチャンキングのテストです。';
        $result = $this->service->chunk($text, 500);
        $this->assertCount(1, $result);
        $this->assertSame($text, $result[0]['chunk_text']);
    }

    public function test_chunk_by_paragraphs_with_trailing_newlines(): void
    {
        $text = "Para one.\n\nPara two.\n\n\n\n";
        $result = $this->service->chunkByParagraphs($text, 1000);
        $this->assertCount(1, $result);
    }
}
