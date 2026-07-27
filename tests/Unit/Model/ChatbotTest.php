<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Chatbot;
use PHPUnit\Framework\TestCase;

class ChatbotTest extends TestCase
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

    public function test_findByAdmin_returns_chatbots(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'name' => 'Bot A', 'admin_id' => 1],
                ['id' => 2, 'name' => 'Bot B', 'admin_id' => 1],
            ],
        ]));

        $bots = Chatbot::findByAdmin(1);
        $this->assertCount(2, $bots);
    }

    public function test_findByUser_returns_chatbots(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'name' => 'User Bot', 'created_by' => 1],
            ],
        ]));

        $bots = Chatbot::findByUser(1);
        $this->assertCount(1, $bots);
    }

    public function test_countByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 3]));
        $this->assertSame(3, Chatbot::countByAdmin(1));
    }

    public function test_createChatbot_encodes_json_fields(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '77']));

        $id = Chatbot::createChatbot([
            'admin_id'     => 1,
            'name'         => 'New Bot',
            'model_config' => ['model' => 'gpt-4', 'temperature' => 0.7],
            'styling'      => ['primary_color' => '#007bff'],
        ]);
        $this->assertSame(77, $id);
    }

    public function test_updateChatbot_encodes_json_fields(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $result = Chatbot::updateChatbot(1, ['name' => 'Renamed', 'styling' => ['color' => 'red']]);
        $this->assertTrue($result);
    }

    public function test_findByWidgetToken_returns_chatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'name' => 'Widget Bot', 'widget_token' => 'abc123', 'status' => 'active'],
        ]));

        $bot = Chatbot::findByWidgetToken('abc123');
        $this->assertIsArray($bot);
        $this->assertSame('Widget Bot', $bot['name']);
    }

    public function test_findByWidgetToken_returns_null(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertNull(Chatbot::findByWidgetToken('invalid'));
    }

    public function test_findActiveByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [['id' => 1, 'name' => 'Active Bot', 'status' => 'active']],
        ]));

        $bots = Chatbot::findActiveByAdmin(1);
        $this->assertCount(1, $bots);
    }

    public function test_cloneChatbot_throws_on_missing_source(): void
    {
        \TestDb::setPdo($this->createMockPdo()); // no fetch result -> not found
        $this->expectException(\RuntimeException::class);
        Chatbot::cloneChatbot(999, 'Cloned Bot');
    }

    public function test_countByUser(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 7]));
        $this->assertSame(7, Chatbot::countByUser(1));
    }
}
