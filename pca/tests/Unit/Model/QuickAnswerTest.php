<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\QuickAnswer;
use PHPUnit\Framework\TestCase;

class QuickAnswerTest extends TestCase
{
    use \PdoMocker;

    protected function setUp(): void
    {
        \TestDb::reset();
    }

    protected function tearDown(): void
    {
        \TestDb::reset();
    }

    public function test_findByChatbot_returns_answers(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'trigger' => 'hello', 'answer' => 'Hi there!'],
                ['id' => 2, 'trigger' => 'bye', 'answer' => 'Goodbye!'],
            ],
        ]));

        $answers = QuickAnswer::findByChatbot(1, 1);
        $this->assertCount(2, $answers);
    }

    public function test_findActiveByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [['id' => 1, 'trigger' => 'hello', 'answer' => 'Hi!', 'is_active' => 1]],
        ]));

        $answers = QuickAnswer::findActiveByChatbot(1, 1);
        $this->assertCount(1, $answers);
    }

    public function test_matchMessage_returns_answer(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'trigger' => 'pricing', 'answer' => 'Our pricing starts at $29/mo'],
        ]));

        $result = QuickAnswer::matchMessage(1, 1, 'pricing');
        $this->assertIsArray($result);
        $this->assertSame('pricing', $result['trigger']);
    }

    public function test_matchMessage_returns_null(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertNull(QuickAnswer::matchMessage(1, 1, 'nonexistent-trigger'));
    }

    public function test_countByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 12]));
        $this->assertSame(12, QuickAnswer::countByAdmin(1));
    }

    public function test_countByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 5]));
        $this->assertSame(5, QuickAnswer::countByChatbot(1, 1));
    }

    public function test_reorder(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        QuickAnswer::reorder(1, 1, [3, 1, 2]);
        $this->assertTrue(true);
    }
}
