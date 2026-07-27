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

use App\Model\DocumentPageIndexNode;

/**
 * PageIndexBuilder — parses document text (markdown) into a hierarchical tree
 * of sections and stores them as document_page_index nodes.
 *
 * The tree mirrors the document's heading structure, allowing the PageIndex
 * retrieval strategy to navigate by section headings rather than by vector search.
 */
class PageIndexBuilder
{
    /**
     * Build a PageIndex tree from parsed markdown text and store it in the DB.
     *
     * @param  int    $adminId
     * @param  int    $chatbotId
     * @param  int    $documentId
     * @param  string $parsedText  Markdown text from LlamaParse
     * @return int   Number of nodes created
     */
    public function buildAndStore(int $adminId, int $chatbotId, int $documentId, string $parsedText): int
    {
        // Remove existing tree for this document (re-index)
        DocumentPageIndexNode::deleteByDocument($documentId);

        $parsedText = trim($parsedText);
        if ($parsedText === '') {
            return 0;
        }

        // Split into sections by markdown headings
        $sections = $this->parseSections($parsedText);

        if (empty($sections)) {
            // No headings at all — treat entire document as a single leaf node
            $nodeData = [[
                'parent_id'     => null,
                'node_type'     => 'root',
                'heading'       => null,
                'heading_level' => null,
                'content'       => $parsedText,
                'node_order'    => 0,
            ]];
            $ids = DocumentPageIndexNode::insertBatch($adminId, $chatbotId, $documentId, $nodeData);
            return count($ids);
        }

        // Insert nodes and build hierarchy using heading levels
        $nodes = [];      // Flat list of node data
        $stack = [];      // Stack of [level, node_id] — used after insert

        // Track the root node ID (to be filled after insert)
        $rootId = null;

        foreach ($sections as $i => $section) {
            $level = $section['level'];

            // Determine parent: walk stack to find the last node at a lower level
            $parentLevel = null;
            while (!empty($stack)) {
                $top = end($stack);
                if ($top['level'] < $level) {
                    $parentLevel = $top['level'];
                    break;
                }
                array_pop($stack);
            }

            // If there's a parent on the stack, use its index in $nodes
            $parentIdx = !empty($stack) ? key($stack) : null;
            // If no parent in stack, parent is the root (index 0 if exists)
            // or null (top-level section with root as implicit parent)

            $nodeData = [
                'parent_id'     => null,   // will be resolved after insert
                'node_type'     => $this->determineNodeType($level),
                'heading'       => $section['heading'],
                'heading_level' => $level,
                'content'       => $section['content'],
                'node_order'    => $i,
            ];

            $nodes[] = $nodeData;
            $nodeIdx = count($nodes) - 1;

            // Push onto stack
            $stack[$nodeIdx] = ['level' => $level];
        }

        // Insert all nodes and get their IDs
        $insertedIds = DocumentPageIndexNode::insertBatch($adminId, $chatbotId, $documentId, $nodes);

        if (empty($insertedIds)) {
            return 0;
        }

        // Now resolve parent_id references
        // We need to determine which node is parent of which based on heading levels
        // Since we inserted linearly, we can update parent_id in a second pass
        $this->setParentRelations($adminId, $chatbotId, $documentId, $insertedIds, $sections);

        return count($insertedIds);
    }

    /**
     * Parse markdown text into sections split by headings.
     *
     * @return array[] Each entry: ['heading' => string, 'level' => int, 'content' => string]
     */
    private function parseSections(string $text): array
    {
        // Match markdown headings: ## Some Title
        // Supports headings with optional closing # characters
        $pattern = '/^(#{1,6})\s+(.+?)(?:\s+#+)?$/m';

        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $sections = [];
        $lastOffset = 0;
        $lastLevel = 0;
        $lastHeading = null;
        $lastHeadingOffset = null;

        foreach ($matches as $match) {
            $level = strlen($match[1][0]);
            $heading = trim($match[2][0]);
            $offset = $match[0][1];

            // Content before this heading (from after previous heading to before this one)
            if ($lastHeadingOffset !== null) {
                $content = substr($text, $lastHeadingOffset, $offset - $lastHeadingOffset);
                $content = trim($content);
                // Strip the heading line itself from the content
                $headingLineLen = $offset - $lastHeadingOffset;
                $headingLine = substr($text, $lastHeadingOffset, strcspn(substr($text, $lastHeadingOffset), "\n") + 1);
                $content = trim(substr($text, $lastHeadingOffset + strlen($headingLine), $offset - $lastHeadingOffset - strlen($headingLine)));
                $sections[] = [
                    'heading' => $lastHeading,
                    'level'   => $lastLevel,
                    'content' => $content,
                ];
            }

            $lastHeading = $heading;
            $lastLevel = $level;
            $lastHeadingOffset = $offset;

            // Find end of heading line
            $headingEnd = strpos($text, "\n", $offset);
            $lastHeadingOffset = ($headingEnd !== false) ? $headingEnd + 1 : strlen($text);
        }

        // Last section — content after last heading to end
        if ($lastHeadingOffset !== null) {
            $content = trim(substr($text, $lastHeadingOffset));
            $sections[] = [
                'heading' => $lastHeading,
                'level'   => $lastLevel,
                'content' => $content,
            ];
        }

        return $sections;
    }

    /**
     * Determine node type based on heading level.
     */
    private function determineNodeType(int $level): string
    {
        return match (true) {
            $level === 1 => 'section',
            $level === 2 => 'subsection',
            default      => 'leaf',
        };
    }

    /**
     * Set parent_id for each inserted node based on heading level hierarchy.
     *
     * After flat insert, this walks the sections and for each node finds the
     * nearest preceding node at a lower heading level, then sets it as parent.
     */
    private function setParentRelations(int $adminId, int $chatbotId, int $documentId, array $insertedIds, array $sections): void
    {
        $db = \getDb();
        $stack = []; // [node_id, level]

        foreach ($sections as $i => $section) {
            $nodeId = $insertedIds[$i] ?? null;
            if ($nodeId === null) {
                continue;
            }

            $level = $section['level'];

            // Pop stack until we find a parent at a lower level
            while (!empty($stack)) {
                $top = end($stack);
                if ($top['level'] < $level) {
                    break;
                }
                array_pop($stack);
            }

            if (!empty($stack)) {
                $parentId = end($stack)['node_id'];
                // Update parent_id in DB
                $stmt = $db->prepare(
                    'UPDATE document_page_index SET parent_id = :pid WHERE id = :id'
                );
                $stmt->bindValue(':pid', $parentId, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $nodeId, \PDO::PARAM_INT);
                $stmt->execute();
            }

            // Push this node onto the stack
            $stack[] = ['node_id' => $nodeId, 'level' => $level];
        }
    }
}
