<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for IndustryPresets data file.
 *
 * Verifies the file returns a valid array structure and
 * contains the expected industry categories.
 */
class IndustryPresetsTest extends TestCase
{
    private array $presets;

    protected function setUp(): void
    {
        $this->presets = require dirname(__DIR__, 2) . '/src/App/Data/IndustryPresets.php';
    }

    public function test_returns_array(): void
    {
        $this->assertIsArray($this->presets);
    }

    public function test_contains_expected_categories(): void
    {
        $expected = [
            'Custom', 'Healthcare', 'Technology & SaaS', 'E-Commerce & Retail',
            'Food & Beverage', 'Real Estate', 'Finance & Banking',
            'Hospitality & Travel', 'Home Services', 'Legal',
        ];

        foreach ($expected as $cat) {
            $this->assertArrayHasKey($cat, $this->presets, "Missing category: {$cat}");
        }
    }

    public function test_each_category_has_valid_prompts(): void
    {
        foreach ($this->presets as $category => $prompts) {
            $this->assertNotEmpty($prompts, "Category '{$category}' has no prompts");
            foreach ($prompts as $prompt) {
                $this->assertArrayHasKey('label', $prompt, "Missing label in {$category}");
                $this->assertArrayHasKey('prompt', $prompt, "Missing prompt in {$category}");
                $this->assertIsString($prompt['label']);
                $this->assertIsString($prompt['prompt']);
                $this->assertNotEmpty($prompt['label'], "Empty label in {$category}");
            }
        }
    }

    public function test_custom_category_has_empty_prompt(): void
    {
        $this->assertSame('', $this->presets['Custom'][0]['prompt']);
    }

    public function test_prompts_contain_company_placeholder(): void
    {
        $found = false;
        foreach ($this->presets as $category => $prompts) {
            foreach ($prompts as $prompt) {
                if (str_contains($prompt['prompt'], '{company}')) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($found, 'No prompts contain the {company} placeholder');
    }

    public function test_at_least_20_industries(): void
    {
        $this->assertGreaterThanOrEqual(20, count($this->presets));
    }

    public function test_prompts_are_unique_within_category(): void
    {
        foreach ($this->presets as $category => $prompts) {
            $labels = array_map(fn($p) => $p['label'], $prompts);
            $this->assertCount(
                count($labels),
                array_unique($labels),
                "Duplicate labels in category '{$category}'"
            );
        }
    }
}
