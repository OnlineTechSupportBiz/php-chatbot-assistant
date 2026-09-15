<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
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

    public function test_findByChatbot_returns_documents(): void
    {
        \TestDb::setPdo($this->createMockPdo([
            'fetchAll' => [
                ['id' => 1, 'original_name' => 'doc1.pdf', 'status' => 'indexed'],
                ['id' => 2, 'original_name' => 'doc2.pdf', 'status' => 'parsed'],
            ],
        ]));

        $docs = Document::findByChatbot(1, 1);
        $this->assertCount(2, $docs);
    }

    public function test_countIndexedByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 5]));
        $this->assertSame(5, Document::countIndexedByChatbot(1, 1));
    }

    public function test_patchStatus(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(Document::patchStatus(1, 'parsed'));
    }

    public function test_saveParsedText(): void
    {
        \TestDb::setPdo($this->createMockPdo());
        $this->assertTrue(Document::saveParsedText(1, 'Parsed text'));
    }

    public function test_existsByOriginalName(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 1]));
        $this->assertTrue(Document::existsByOriginalName(1, 1, 'test.pdf'));
    }

    public function test_create_returns_id(): void
    {
        \TestDb::setPdo($this->createMockPdo(['id' => '55']));
        $this->assertSame(55, Document::create(['admin_id' => 1, 'chatbot_id' => 1, 'original_name' => 'file.pdf']));
    }

    public function test_totalChunksByChatbot(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 42]));
        $this->assertSame(42, Document::totalChunksByChatbot(1, 1));
    }

    public function test_countByAdmin(): void
    {
        \TestDb::setPdo($this->createMockPdo(['column' => 10]));
        $this->assertSame(10, Document::countByAdmin(1));
    }
}
