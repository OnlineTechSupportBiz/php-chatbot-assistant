<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Request — HTTP request parser.
 *
 * Covers: method, path, query, body (JSON and POST), files,
 * server, get(), hasFile(), isAjax(), wantsJson(), clientIp().
 */
class RequestTest extends TestCase
{
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;

    protected function setUp(): void
    {
        // Save superglobals
        $this->originalServer = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
    }

    public function test_default_get_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();

        $this->assertSame('GET', $request->method);
        $this->assertSame('/test', $request->path);
        $this->assertSame([], $request->query);
        $this->assertSame([], $request->body);
    }

    public function test_parses_query_string(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/search?q=hello&page=1';
        $_GET = ['q' => 'hello', 'page' => '1'];
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();

        $this->assertSame('hello', $request->query['q']);
        $this->assertSame('1', $request->query['page']);
    }

    public function test_body_populated_from_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['name' => 'John', 'email' => 'john@example.com'];

        $request = new Request();

        $this->assertSame('John', $request->body['name']);
        $this->assertSame('john@example.com', $request->body['email']);
    }

    public function test_get_method(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/page';
        $_GET = ['sort' => 'asc'];
        $_POST = [];

        $request = new Request();

        $this->assertSame('asc', $request->get('sort'));
        $this->assertNull($request->get('missing'));
        $this->assertSame('default', $request->get('missing', 'default'));
    }

    public function test_get_from_body_first(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = ['key' => 'body-value'];
        $_GET = ['key' => 'query-value'];

        $request = new Request();

        // get() checks body first, then query
        $this->assertSame('body-value', $request->get('key'));
    }

    public function test_isAjax_detects_header(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ajax';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $request = new Request();
        $this->assertTrue($request->isAjax());
    }

    public function test_isAjax_detects_accept_json(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/data';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();
        $this->assertTrue($request->isAjax());
    }

    public function test_isAjax_false_for_normal_request(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/page';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();
        $this->assertFalse($request->isAjax());
    }

    public function test_wantsJson_detects_accept_header(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/data';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();
        $this->assertTrue($request->wantsJson());
    }

    public function test_wantsJson_true_for_xhr(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/xhr';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $request = new Request();
        $this->assertTrue($request->wantsJson());
    }

    public function test_wantsJson_false_for_html(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/page';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();
        $this->assertFalse($request->wantsJson());
    }

    public function test_clientIp_from_remote_addr(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $request = new Request();
        $this->assertSame('192.168.1.1', $request->clientIp());
    }

    public function test_clientIp_from_x_forwarded_for(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $request = new Request();
        $this->assertSame('203.0.113.5', $request->clientIp());
    }

    public function test_clientIp_default(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $request = new Request();
        $this->assertSame('127.0.0.1', $request->clientIp());
    }

    public function test_clientIp_from_x_real_ip(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_X_REAL_IP'] = '10.0.0.99';

        $request = new Request();
        $this->assertSame('10.0.0.99', $request->clientIp());
    }

    public function test_hasFile_returns_true_for_valid_file(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/upload';
        $_FILES = [
            'document' => [
                'name' => 'test.pdf',
                'type' => 'application/pdf',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024,
            ],
        ];

        $request = new Request();
        $this->assertTrue($request->hasFile('document'));
        $this->assertFalse($request->hasFile('missing'));
    }
}
