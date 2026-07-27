<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Conversation;
use PHPUnit\Framework\TestCase;

class ConversationTest extends TestCase
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

    public function test_findBySession_returns_conversation(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'visitor_session_id' => 'sess-abc', 'message_count' => 3],
        ]));

        $conv = Conversation::findBySession(1, 'sess-abc');
        $this->assertIsArray($conv);
        $this->assertSame(1, $conv['id']);
    }

    public function test_findByChatbot_returns_conversations(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'visitor_session_id' => 'sess-1'],
                ['id' => 2, 'visitor_session_id' => 'sess-2'],
            ],
        ]));

        $convs = Conversation::findByChatbot(1);
        $this->assertCount(2, $convs);
    }

    public function test_countByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 42]));
        $this->assertSame(42, Conversation::countByAdmin(1));
    }

    public function test_countByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 7]));
        $this->assertSame(7, Conversation::countByChatbot(1));
    }

    public function test_touch(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        Conversation::touch(1);
        $this->assertTrue(true);
    }

    public function test_sumTokensUsedToday(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 1500]));
        $this->assertSame(1500, Conversation::sumTokensUsedToday(1));
    }

    public function test_uniqueVisitorsByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 25]));
        $this->assertSame(25, Conversation::uniqueVisitorsByAdmin(1));
    }

    public function test_countRecentBySession(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 3]));
        $this->assertSame(3, Conversation::countRecentBySession(1, 'sess-abc', 1));
    }

    public function test_getRecentWithMessages(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'visitor_session_id' => 'sess-1', 'last_message_content' => 'Hello'],
            ],
        ]));

        $rows = Conversation::getRecentWithMessages(1);
        $this->assertCount(1, $rows);
    }

    public function test_rate_accepts_valid_rating(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $result = Conversation::rate(1, '4');
        $this->assertTrue($result);
    }

    public function test_rate_strips_non_digits(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $result = Conversation::rate(1, ' rating: 5 stars ');
        $this->assertTrue($result);
    }

    public function test_rate_rejects_out_of_range(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertFalse(Conversation::rate(1, '0'));
        $this->assertFalse(Conversation::rate(1, '6'));
        $this->assertFalse(Conversation::rate(1, '99'));
    }

    public function test_rate_rejects_blank_after_strip(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertFalse(Conversation::rate(1, 'abc'));
        $this->assertFalse(Conversation::rate(1, ''));
    }
}
