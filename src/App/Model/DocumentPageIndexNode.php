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

namespace App\Model;

use PDO;

/**
 * DocumentPageIndexNode model — hierarchical tree of document sections for PageIndex retrieval.
 *
 * Each node represents a section (heading + content) in a document's structure.
 * Nodes form a parent-child tree where the root represents the document itself
 * and children represent sections, subsections, and leaf content blocks.
 *
 * Schema columns:
 *   id, admin_id, chatbot_id, document_id, parent_id, node_type (root|section|subsection|leaf),
 *   heading, heading_level (1-6), content, node_order, created_at
 */
class DocumentPageIndexNode extends Model
{
    protected static function table(): string
    {
        return 'document_page_index';
    }

    /**
     * Fetch the entire tree for a chatbot, ordered for hierarchical assembly.
     *
     * Returns flat rows ordered by document_id, parent_id NULLS FIRST, then node_order.
     */
    public static function getTreeByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM document_page_index
             WHERE admin_id = :aid AND chatbot_id = :cid
             ORDER BY document_id, parent_id IS NOT NULL, parent_id, node_order'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch the tree for a specific document.
     */
    public static function getTreeByDocument(int $documentId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM document_page_index
             WHERE document_id = :did
             ORDER BY parent_id IS NOT NULL, parent_id, node_order'
        );
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch only the tree outline (headings, no content) for a chatbot.
     * This is what gets sent to the LLM for PageIndex navigation.
     *
     * Returns flat rows with id, parent_id, heading, heading_level, node_type, document_id.
     */
    public static function getOutlineByChatbot(int $adminId, int $chatbotId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, parent_id, document_id, node_type, heading, heading_level, node_order
             FROM document_page_index
             WHERE admin_id = :aid AND chatbot_id = :cid
             ORDER BY document_id, parent_id IS NOT NULL, parent_id, node_order'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch full content of specific nodes by their IDs.
     */
    public static function getContentByIds(int $adminId, int $chatbotId, array $nodeIds): array
    {
        if (empty($nodeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $stmt = self::db()->prepare(
            "SELECT id, heading, content, node_type, document_id
             FROM document_page_index
             WHERE admin_id = ? AND chatbot_id = ? AND id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$adminId, $chatbotId], $nodeIds));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert a batch of nodes for a document in a single transaction.
     *
     * Each node: ['parent_id' => ?int, 'node_type' => string, 'heading' => ?string,
     *            'heading_level' => ?int, 'content' => ?string, 'node_order' => int]
     *
     * @param int   $adminId
     * @param int   $chatbotId
     * @param int   $documentId
     * @param array $nodes
     * @return int[]  Inserted node IDs in order
     */
    public static function insertBatch(int $adminId, int $chatbotId, int $documentId, array $nodes): array
    {
        if (empty($nodes)) {
            return [];
        }

        $db = self::db();
        $db->beginTransaction();

        try {
            $insertedIds = [];
            foreach ($nodes as $i => $node) {
                $parentId = $node['parent_id'] ?? null;
                $nodeType = $node['node_type'] ?? 'section';
                $heading  = $node['heading'] ?? null;
                $level    = isset($node['heading_level']) ? (int) $node['heading_level'] : null;
                $content  = $node['content'] ?? null;
                $order    = (int) ($node['node_order'] ?? 0);

                $ai = ":aid_{$i}";
                $ci = ":cid_{$i}";
                $di = ":did_{$i}";
                $pi = ":pid_{$i}";
                $nt = ":nt_{$i}";
                $hd = ":hd_{$i}";
                $hl = ":hl_{$i}";
                $ct = ":ct_{$i}";
                $no = ":no_{$i}";

                $values[] = "({$ai}, {$ci}, {$di}, {$pi}, {$nt}, {$hd}, {$hl}, {$ct}, {$no})";
                $params[$ai] = [$adminId, PDO::PARAM_INT];
                $params[$ci] = [$chatbotId, PDO::PARAM_INT];
                $params[$di] = [$documentId, PDO::PARAM_INT];
                $params[$pi] = [$parentId, $parentId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL];
                $params[$nt] = [$nodeType, PDO::PARAM_STR];
                $params[$hd] = [$heading, PDO::PARAM_STR];
                $params[$hl] = [$level, $level !== null ? PDO::PARAM_INT : PDO::PARAM_NULL];
                $params[$ct] = [$content, PDO::PARAM_STR];
                $params[$no] = [$order, PDO::PARAM_INT];
            }

            $sql = 'INSERT INTO document_page_index (admin_id, chatbot_id, document_id, parent_id, node_type, heading, heading_level, content, node_order) VALUES '
                 . implode(', ', $values)
                 . ' RETURNING id';

            $stmt = $db->prepare($sql);
            foreach ($params as $key => [$value, $type]) {
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();

            while ($row = $stmt->fetch()) {
                $insertedIds[] = (int) $row['id'];
            }

            $db->commit();
            return $insertedIds;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete all nodes for a given document.
     */
    public static function deleteByDocument(int $documentId): bool
    {
        $stmt = self::db()->prepare(
            'DELETE FROM document_page_index WHERE document_id = :did'
        );
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Delete all nodes for a given chatbot.
     */
    public static function deleteByChatbot(int $chatbotId): bool
    {
        $stmt = self::db()->prepare(
            'DELETE FROM document_page_index WHERE chatbot_id = :cid'
        );
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Count nodes for a document.
     */
    public static function countByDocument(int $documentId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM document_page_index WHERE document_id = :did'
        );
        $stmt->bindValue(':did', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count indexed (has at least one node) documents for a chatbot under PageIndex.
     */
    public static function countIndexedDocumentsByChatbot(int $adminId, int $chatbotId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(DISTINCT document_id) FROM document_page_index
             WHERE admin_id = :aid AND chatbot_id = :cid'
        );
        $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':cid', $chatbotId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Build a nested tree array from flat rows (in-memory).
     *
     * Input: flat rows with id, parent_id, heading, node_type, etc.
     * Output: hierarchical array with 'children' arrays.
     */
    public static function buildTree(array $flatRows): array
    {
        $map = [];
        $roots = [];

        // First pass: index by id
        foreach ($flatRows as $row) {
            $row['children'] = [];
            $map[$row['id']] = $row;
        }

        // Second pass: link children to parents
        foreach ($map as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    /**
     * Render a tree (or subtree) as a text outline for LLM consumption.
     *
     * Format:
     *   [id:123] 1. Introduction
     *   [id:124]   1.1 Background
     *   [id:125]     1.1.1 History
     */
    public static function renderOutline(array $nodes, int $depth = 0): string
    {
        return implode("\n", self::renderOutlineLines($nodes, $depth));
    }

    /**
     * Recursive helper: returns an array of lines (no implode), so child calls
     * can be merged with array_merge without type errors.
     *
     * @return string[]
     */
    private static function renderOutlineLines(array $nodes, int $depth = 0): array
    {
        $lines = [];

        foreach ($nodes as $node) {
            $prefix = str_repeat('  ', $depth);
            $heading = $node['heading'] ?? '(untitled)';
            $lines[] = sprintf(
                '%s[id:%d] %s%s',
                $prefix,
                $node['id'],
                $prefix ? '' : (!empty($node['heading_level']) ? str_repeat('#', (int) $node['heading_level']) . ' ' : ''),
                $heading
            );

            if (!empty($node['children'])) {
                $childLines = self::renderOutlineLines($node['children'], $depth + 1);
                $lines = array_merge($lines, $childLines);
            }
        }

        return $lines;
    }
}
