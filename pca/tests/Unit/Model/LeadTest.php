<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Lead;
use PHPUnit\Framework\TestCase;

class LeadTest extends TestCase
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

    public function test_findByChatbot_returns_leads(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
                ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
            ],
        ]));

        $leads = Lead::findByChatbot(1, 1);
        $this->assertCount(2, $leads);
    }

    public function test_findByConversation_returns_lead(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetch' => ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
        ]));

        $lead = Lead::findByConversation(1);
        $this->assertIsArray($lead);
        $this->assertSame('John', $lead['name']);
    }

    public function test_countByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 8]));
        $this->assertSame(8, Lead::countByChatbot(1, 1));
    }

    public function test_createLead(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '33']));
        $this->assertSame(33, Lead::createLead([
            'admin_id' => 1, 'chatbot_id' => 1, 'name' => 'New Lead', 'email' => 'lead@test.com',
        ]));
    }
}
