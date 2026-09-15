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

namespace App\Util;

/**
 * PromptScanner — lightweight regex-based prompt injection / jailbreak detector.
 *
 * Scans incoming chatbot visitor messages for common attack patterns.
 * Returns a category label on match, or null if the message is clean.
 *
 * All patterns are case-insensitive and applied greedily per category.
 */
class PromptScanner
{
    /**
     * Pattern => category label pairs, ordered by specificity (most specific first).
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        // Ignore / disregard instructions (the most common injection vector)
        '/\bignore\s+(all\s+)?(previous\s+)?(instructions?|directives?|prompts?|commands?|above|below|everything)\b/i'
        => 'ignore_instructions',

        // System prompt override / role hijack
        '/\b(from\s+now\s+on|you\s+are\s+(now\s+)?)\b.*\b(you\s+(are|will)\s+)?(a\s+|the\s+)?(dan|chatgpt|free\s+(unlocked|mode)|unlocked|uncensored|unfiltered|raw\s*mode)\b/i'
        => 'system_override',

        // Jailbreak references (DAN, developer mode, bypass, etc.)
        '/\b(do\s+anything\s+now|dan\s+(jail|mode|setup)|jail\s*break|prompt\s+injection|developer\s+mode|secret\s+mode|bypass\s+(safety|rules|filter|restrictions?)|god\s+mode|super\s+prompt)\b/i'
        => 'jailbreak_reference',

        // Output format coercion — try to extract the system prompt or force a specific output
        '/\b(?:start|begin)\s+(?:your\s+)?(?:response|reply|answer)\s+with\b/i'
        => 'output_coercion',

        // Token / secret extraction
        '/\b(reveal|show|display|print|output|spill|leak|extract|dump)\s+.*\b(system\s+prompt|initial\s+prompt|instructions?|directives?|api\s+key|token|password|secret)\b/i'
        => 'secret_extraction',
    ];

    /**
     * Scan a message for prompt injection / jailbreak patterns.
     *
     * @param  string      $message The visitor's raw message text.
     * @return string|null          The category label if a pattern matched, null otherwise.
     */
    public static function scan(string $message): ?string
    {
        foreach (self::PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $message)) {
                return $label;
            }
        }

        return null;
    }
}
