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

/**
 * IndustryTemplate — templates loaded from IndustryPresets.php.
 *
 * Each entry is a pre-built industry profile: category (optgroup label),
 * template label, and default system prompt. The dropdown on the
 * chatbot create/edit form reads from this file.
 */
class IndustryTemplate
{
    /**
     * Return all templates, grouped by category for optgroup rendering.
     *
     * @return array<string, array[]>  e.g. ['Technology & SaaS' => [['label'=>..., 'prompt'=>...], ...], ...]
     */
    public static function allGrouped(): array
    {
        $presets = require __DIR__ . '/../Data/IndustryPresets.php';

        // Move "Custom" to the first position so the form renders its
        // placeholder + custom option at the top of the dropdown.
        if (isset($presets['Custom'])) {
            $custom = ['Custom' => $presets['Custom']];
            unset($presets['Custom']);
            $presets = $custom + $presets;
        }

        return $presets;
    }
}
