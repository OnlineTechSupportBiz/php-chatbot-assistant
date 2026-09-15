<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Model;
use App\Model\Chatbot;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
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

    public function test_find_returns_row(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'name' => 'Test Bot', 'admin_id' => 1],
        ]));

        $result = Chatbot::find(1);
        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Test Bot', $result['name']);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertNull(Chatbot::find(999));
    }

    public function test_findAll_returns_array(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'name' => 'Bot 1', 'admin_id' => 1],
                ['id' => 2, 'name' => 'Bot 2', 'admin_id' => 1],
            ],
        ]));

        $result = Chatbot::findAll();
        $this->assertCount(2, $result);
    }

    public function test_insert_returns_id(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '42']));
        $this->assertSame(42, Chatbot::insert(['name' => 'New Bot', 'admin_id' => 1]));
    }

    public function test_update_returns_true(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(Chatbot::update(1, ['name' => 'Updated']));
    }

    public function test_delete_returns_true(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(Chatbot::delete(1));
    }

    public function test_count_returns_int(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 5]));
        $this->assertSame(5, Chatbot::count());
    }

    public function test_table_naming_convention(): void
    {
        $method = new \ReflectionMethod(Chatbot::class, 'table');
        $method->setAccessible(true);
        $this->assertSame('chatbots', $method->invoke(null));
    }
}
