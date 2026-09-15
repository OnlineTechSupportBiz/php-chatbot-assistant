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
 * ChunkingService — splits parsed document text into overlapping chunks.
 *
 * Default: 500 chars per chunk with 50-char overlap (word-aware boundaries).
 */
class ChunkingService
{
    /**
     * Split text into overlapping chunks, respecting word boundaries.
     *
     * @param  string $text     Parsed text from LlamaParse
     * @param  int    $size     Target chunk size in characters
     * @param  int    $overlap  Overlap between consecutive chunks in characters
     * @return array[]          Array of ['chunk_index' => int, 'chunk_text' => string]
     */
    public function chunk(string $text, int $size = 500, int $overlap = 50): array
    {
        $chunks     = [];
        $textLength = mb_strlen($text);

        if ($textLength === 0) {
            return [];
        }

        $start = 0;
        $index = 0;

        while ($start < $textLength) {
            // Extract chunk
            $chunkText = mb_substr($text, $start, $size);

            // If we're not at the end, try to break at the last space within the chunk
            $endPos = $start + $size;
            if ($endPos < $textLength) {
                $lastSpace = mb_strrpos($chunkText, ' ');
                if ($lastSpace !== false && $lastSpace > $size / 2) {
                    $chunkText  = mb_substr($text, $start, $lastSpace);
                    $endPos     = $start + $lastSpace;
                }
            }

            $chunks[] = [
                'chunk_index' => $index,
                'chunk_text'  => $chunkText,
            ];

            // Next start = current end - overlap, but advance at least 1 char
            $start = max($endPos - $overlap, $start + 1);
            $index++;
        }

        return $chunks;
    }

    /**
     * Split text by paragraph boundaries for larger, more coherent chunks.
     *
     * @param  string $text
     * @param  int    $maxChars  Max chars per chunk (will try to break at paragraph)
     * @return array[]
     */
    public function chunkByParagraphs(string $text, int $maxChars = 1000): array
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $chunks     = [];
        $buffer     = '';
        $index      = 0;

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            // If a single paragraph exceeds maxChars, split it with the standard method
            if (mb_strlen($para) > $maxChars) {
                // Flush buffer first
                if ($buffer !== '') {
                    $chunks[] = [
                        'chunk_index' => $index++,
                        'chunk_text'  => $buffer,
                    ];
                    $buffer = '';
                }

                $subChunks = $this->chunk($para, $maxChars, (int) ($maxChars * 0.1));
                foreach ($subChunks as $sc) {
                    $chunks[] = [
                        'chunk_index' => $index++,
                        'chunk_text'  => $sc['chunk_text'],
                    ];
                }
                continue;
            }

            // Would adding this paragraph exceed maxChars?
            $candidate = $buffer === '' ? $para : $buffer . "\n\n" . $para;
            if (mb_strlen($candidate) > $maxChars && $buffer !== '') {
                $chunks[] = [
                    'chunk_index' => $index++,
                    'chunk_text'  => $buffer,
                ];
                $buffer = $para;
            } else {
                $buffer = $candidate;
            }
        }

        // Flush remaining buffer
        if ($buffer !== '') {
            $chunks[] = [
                'chunk_index' => $index++,
                'chunk_text'  => $buffer,
            ];
        }

        return $chunks;
    }
}
