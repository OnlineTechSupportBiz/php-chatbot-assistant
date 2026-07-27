<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Router — route registration, pattern matching, 404 handling.
 *
 * Covers: add, get, post, put, delete, exact match, parameterized
 * routes, regex constraints, 404 JSON, 404 HTML.
 */
class RouterTest extends TestCase
{
    private Router $router;
    private array $captured; // Used to capture handler call arguments

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->captured = [];
        // Reset server globals to prevent test pollution
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE']);
    }

    private function makeRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        return new Request();
    }

    private function makeResponse(): Response
    {
        return new Response();
    }

    // ── Basic route registration ────────────────────────────────────────────

    public function test_get_route_matches(): void
    {
        $this->router->get('/hello', function (Request $req, Response $res, array $params) {
            $this->captured = ['handler' => 'hello', 'params' => $params];
        });

        $this->router->dispatch($this->makeRequest('GET', '/hello'), $this->makeResponse());

        $this->assertSame('hello', $this->captured['handler']);
        $this->assertSame([], $this->captured['params']);
    }

    public function test_post_route_matches(): void
    {
        $this->router->post('/submit', function (Request $req, Response $res, array $params) {
            $this->captured = ['handler' => 'submit', 'params' => $params];
        });

        $this->router->dispatch($this->makeRequest('POST', '/submit'), $this->makeResponse());

        $this->assertSame('submit', $this->captured['handler']);
    }

    public function test_put_route_matches(): void
    {
        $this->router->put('/update', function (Request $req, Response $res, array $params) {
            $this->captured = ['handler' => 'update'];
        });

        $this->router->dispatch($this->makeRequest('PUT', '/update'), $this->makeResponse());

        $this->assertSame('update', $this->captured['handler']);
    }

    public function test_delete_route_matches(): void
    {
        $this->router->delete('/remove', function (Request $req, Response $res, array $params) {
            $this->captured = ['handler' => 'delete'];
        });

        $this->router->dispatch($this->makeRequest('DELETE', '/remove'), $this->makeResponse());

        $this->assertSame('delete', $this->captured['handler']);
    }

    public function test_route_method_mismatch_returns_404(): void
    {
        $this->router->get('/only-get', function () {
            $this->captured = ['called' => true];
        });

        // Capture output to check 404
        ob_start();
        $this->router->dispatch($this->makeRequest('POST', '/only-get'), $this->makeResponse());
        $output = ob_get_clean();

        $this->assertArrayNotHasKey('called', $this->captured);
        $this->assertStringContainsString('Not Found', $output);
    }

    // ── Pattern matching ────────────────────────────────────────────────────

    public function test_route_with_named_parameter(): void
    {
        $this->router->get('/chatbot/{id}/edit', function (Request $req, Response $res, array $params) {
            $this->captured = $params;
        });

        $this->router->dispatch($this->makeRequest('GET', '/chatbot/42/edit'), $this->makeResponse());

        $this->assertSame(['id' => '42'], $this->captured);
    }

    public function test_route_with_multiple_parameters(): void
    {
        $this->router->get('/admin/{adminId}/user/{userId}', function (Request $req, Response $res, array $params) {
            $this->captured = $params;
        });

        $this->router->dispatch($this->makeRequest('GET', '/admin/5/user/12'), $this->makeResponse());

        $this->assertSame(['adminId' => '5', 'userId' => '12'], $this->captured);
    }

    public function test_route_with_regex_constraint(): void
    {
        $this->router->get('/item/{id:\d+}', function (Request $req, Response $res, array $params) {
            $this->captured = $params;
        });

        // Should match digits
        $this->router->dispatch($this->makeRequest('GET', '/item/99'), $this->makeResponse());
        $this->assertSame(['id' => '99'], $this->captured);

        // Should NOT match non-digits (no route, so 404)
        $this->captured = [];
        ob_start();
        $this->router->dispatch($this->makeRequest('GET', '/item/abc'), $this->makeResponse());
        ob_get_clean();
        $this->assertSame([], $this->captured);
    }

    // ── Path normalization ──────────────────────────────────────────────────

    public function test_trailing_slash_normalized(): void
    {
        $this->router->get('/dashboard', function () {
            $this->captured = ['matched' => true];
        });

        $this->router->dispatch($this->makeRequest('GET', '/dashboard/'), $this->makeResponse());

        $this->assertTrue($this->captured['matched']);
    }

    public function test_root_path(): void
    {
        $this->router->get('/', function () {
            $this->captured = ['matched' => true];
        });

        $this->router->dispatch($this->makeRequest('GET', '/'), $this->makeResponse());

        $this->assertTrue($this->captured['matched']);
    }

    // ── 404 handling ────────────────────────────────────────────────────────

    public function test_unknown_route_returns_html_404(): void
    {
        ob_start();
        $this->router->dispatch($this->makeRequest('GET', '/nonexistent'), $this->makeResponse());
        $output = ob_get_clean();

        $this->assertStringContainsString('404', $output);
        $this->assertStringContainsString('Not Found', $output);
    }

    public function test_unknown_route_returns_json_404_for_api(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        $this->router->dispatch($this->makeRequest('GET', '/api/nonexistent'), $this->makeResponse());
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertSame(['error' => 'Not found'], $decoded);
    }

    // ── Exact vs pattern priority ───────────────────────────────────────────

    public function test_exact_match_takes_priority(): void
    {
        $this->router->get('/items/42', function () {
            $this->captured = ['route' => 'exact'];
        });
        $this->router->get('/items/{id}', function () {
            $this->captured = ['route' => 'pattern'];
        });

        $this->router->dispatch($this->makeRequest('GET', '/items/42'), $this->makeResponse());

        $this->assertSame('exact', $this->captured['route']);
    }

    public function test_first_matching_pattern_wins(): void
    {
        $this->router->get('/{a}/{b}', function () {
            $this->captured = ['route' => 'first'];
        });
        $this->router->get('/hello/{name}', function () {
            $this->captured = ['route' => 'second'];
        });

        $this->router->dispatch($this->makeRequest('GET', '/hello/world'), $this->makeResponse());

        // First registered pattern matches
        $this->assertSame('first', $this->captured['route']);
    }
}
