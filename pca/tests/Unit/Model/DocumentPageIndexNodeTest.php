<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;

/**
 * Integration-ready tests for DocumentPageIndexNode — tree building and outline rendering.
 *
 * These tests focus on the static buildTree() and renderOutline() methods
 * that are pure logic (no DB needed).
 */
use App\Model\DocumentPageIndexNode;

class DocumentPageIndexNodeTest extends TestCase
{
    // ── buildTree() ─────────────────────────────────────────────────────────

    public function test_buildTree_empty_returns_empty(): void
    {
        $this->assertSame([], DocumentPageIndexNode::buildTree([]));
    }

    public function test_buildTree_single_root(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'heading' => 'Root', 'node_type' => 'root'],
        ];

        $tree = DocumentPageIndexNode::buildTree($rows);
        $this->assertCount(1, $tree);
        $this->assertSame('Root', $tree[0]['heading']);
        $this->assertSame([], $tree[0]['children']);
    }

    public function test_buildTree_with_children(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'heading' => 'Root', 'node_type' => 'root'],
            ['id' => 2, 'parent_id' => 1, 'heading' => 'Section 1', 'node_type' => 'section'],
            ['id' => 3, 'parent_id' => 1, 'heading' => 'Section 2', 'node_type' => 'section'],
        ];

        $tree = DocumentPageIndexNode::buildTree($rows);
        $this->assertCount(1, $tree);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertSame('Section 1', $tree[0]['children'][0]['heading']);
        $this->assertSame('Section 2', $tree[0]['children'][1]['heading']);
    }

    public function test_buildTree_nested_hierarchy(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'heading' => 'Root', 'node_type' => 'root'],
            ['id' => 2, 'parent_id' => 1, 'heading' => 'Chapter 1', 'node_type' => 'section'],
            ['id' => 3, 'parent_id' => 2, 'heading' => 'Section 1.1', 'node_type' => 'subsection'],
            ['id' => 4, 'parent_id' => 3, 'heading' => 'Leaf 1.1.1', 'node_type' => 'leaf'],
        ];

        $tree = DocumentPageIndexNode::buildTree($rows);
        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
        $this->assertSame('Section 1.1', $tree[0]['children'][0]['children'][0]['heading']);
    }

    public function test_buildTree_multiple_roots(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'heading' => 'Doc 1', 'node_type' => 'root'],
            ['id' => 2, 'parent_id' => null, 'heading' => 'Doc 2', 'node_type' => 'root'],
        ];

        $tree = DocumentPageIndexNode::buildTree($rows);
        $this->assertCount(2, $tree);
    }

    public function test_buildTree_orphaned_children_become_roots(): void
    {
        $rows = [
            ['id' => 2, 'parent_id' => 999, 'heading' => 'Orphan', 'node_type' => 'section'],
            ['id' => 3, 'parent_id' => null, 'heading' => 'Root', 'node_type' => 'root'],
        ];

        $tree = DocumentPageIndexNode::buildTree($rows);
        // Orphan becomes a root node since parent doesn't exist
        $this->assertCount(2, $tree);
    }

    // ── renderOutline() ─────────────────────────────────────────────────────

    public function test_renderOutline_empty_returns_empty_string(): void
    {
        $this->assertSame('', DocumentPageIndexNode::renderOutline([]));
    }

    public function test_renderOutline_single_node(): void
    {
        $nodes = [
            ['id' => 1, 'heading' => 'Introduction', 'node_type' => 'section', 'children' => []],
        ];

        $outline = DocumentPageIndexNode::renderOutline($nodes);
        $this->assertStringContainsString('[id:1]', $outline);
        $this->assertStringContainsString('Introduction', $outline);
    }

    public function test_renderOutline_nested_nodes(): void
    {
        $nodes = [
            [
                'id' => 1, 'heading' => 'Chapter 1', 'node_type' => 'section',
                'children' => [
                    ['id' => 2, 'heading' => 'Section 1.1', 'node_type' => 'subsection', 'children' => []],
                    ['id' => 3, 'heading' => 'Section 1.2', 'node_type' => 'subsection', 'children' => []],
                ],
            ],
        ];

        $outline = DocumentPageIndexNode::renderOutline($nodes);
        $this->assertStringContainsString('[id:1]', $outline);
        $this->assertStringContainsString('[id:2]', $outline);
        $this->assertStringContainsString('[id:3]', $outline);
        $this->assertStringContainsString('Chapter 1', $outline);
        $this->assertStringContainsString('Section 1.1', $outline);
        $this->assertStringContainsString('Section 1.2', $outline);
    }

    public function test_renderOutline_indentation(): void
    {
        $nodes = [
            [
                'id' => 1, 'heading' => 'Top', 'node_type' => 'section',
                'children' => [
                    ['id' => 2, 'heading' => 'Child', 'node_type' => 'subsection', 'children' => []],
                ],
            ],
        ];

        $outline = DocumentPageIndexNode::renderOutline($nodes);
        $lines = explode("\n", $outline);
        // Child should be indented more than parent
        $this->assertStringStartsWith('[id:1]', trim($lines[0]));
        $this->assertStringContainsString('[id:2]', $lines[1]);
        // Child line should have leading whitespace (indentation)
        $this->assertStringStartsWith('  ', $lines[1]);
    }

    public function test_renderOutline_node_without_heading(): void
    {
        $nodes = [
            ['id' => 5, 'heading' => null, 'node_type' => 'root', 'children' => []],
        ];

        $outline = DocumentPageIndexNode::renderOutline($nodes);
        $this->assertStringContainsString('[id:5]', $outline);
        $this->assertStringContainsString('(untitled)', $outline);
    }
}
