<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Response — HTTP response builder.
 *
 * Covers: setStatus, setHeader, setBody, json, html, redirect,
 * method chaining, send() output buffer.
 */
class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function test_default_status_is_200(): void
    {
        $this->response->setBody('ok');
        ob_start();
        $this->response->send();
        // send() calls http_response_code + header + echo
        $output = ob_get_clean();
        $this->assertSame('ok', $output);
    }

    public function test_setStatus_returns_self_for_chaining(): void
    {
        $result = $this->response->setStatus(404);
        $this->assertSame($this->response, $result);
    }

    public function test_setHeader_returns_self_for_chaining(): void
    {
        $result = $this->response->setHeader('X-Custom', 'value');
        $this->assertSame($this->response, $result);
    }

    public function test_setBody_returns_self_for_chaining(): void
    {
        $result = $this->response->setBody('test');
        $this->assertSame($this->response, $result);
    }

    public function test_json_sets_content_type_and_body(): void
    {
        $data = ['name' => 'Test', 'count' => 42];
        $result = $this->response->json($data);

        // Should be chainable
        $this->assertSame($this->response, $result);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(['name' => 'Test', 'count' => 42], $decoded);
    }

    public function test_json_with_custom_status(): void
    {
        $this->response->json(['error' => 'Not found'], 404);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(['error' => 'Not found'], $decoded);
    }

    public function test_html_sets_content_type(): void
    {
        $this->response->html('<h1>Hello</h1>');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('<h1>Hello</h1>', $output);
    }

    public function test_html_with_status(): void
    {
        $this->response->html('<h1>Server Error</h1>', 500);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('<h1>Server Error</h1>', $output);
    }

    public function test_redirect_sets_location_header(): void
    {
        $this->response->redirect('/login');

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_redirect_with_custom_status(): void
    {
        $this->response->redirect('/new-url', 301);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_full_chain(): void
    {
        $this->response
            ->setStatus(201)
            ->setHeader('X-Request-Id', 'abc-123')
            ->json(['id' => 1, 'created' => true]);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(['id' => 1, 'created' => true], $decoded);
    }

    public function test_json_with_unicode(): void
    {
        $this->response->json(['message' => '日本語テスト']);

        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame('日本語テスト', $decoded['message']);
    }
}
