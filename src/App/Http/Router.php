<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace App\Http;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /**
     * Register a route.
     *
     * @param string   $method   HTTP method (GET, POST, etc.)
     * @param string   $pattern  Route pattern, e.g. /login or /chatbot/{id}/edit
     * @param callable $handler  Callable receiving (Request, Response, array $params)
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[strtoupper($method)][$pattern] = $handler;
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    /**
     * Dispatch the request to the matching route.
     */
    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->method;
        $path   = $request->path;

        // Normalize path (strip trailing slash)
        $path = '/' . trim($path, '/');

        $routes = $this->routes[$method] ?? [];

        // Exact match first
        if (isset($routes[$path])) {
            $routes[$path]($request, $response, []);
            return;
        }

        // Pattern match with {param} placeholders
        foreach ($routes as $pattern => $handler) {
            $regex = $this->patternToRegex($pattern);
            if (preg_match($regex, $path, $matches)) {
                // Extract named params only
                $params = array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $handler($request, $response, $params);
                return;
            }
        }

        // 404
        if ($request->wantsJson()) {
            $response->json(['error' => 'Not found'], 404)->send();
        } else {
            $response->setStatus(404)->html('<h1>404 Not Found</h1>')->send();
        }
    }

    /**
     * Convert a route pattern like /chatbot/{id}/edit or /admin/tenants/{id:\d+}/permissions to a regex.
     *
     * Supports optional inline constraints: {param} matches any segment, {param:\d+} matches only digits.
     */
    private function patternToRegex(string $pattern): string
    {
        // Convert {param:constraint} or {param} into named capture groups
        $regex = preg_replace_callback('/\{([a-zA-Z_]\w*)(?::([^}]+))?\}/', function (array $m): string {
            $name = $m[1];
            $constraint = !empty($m[2]) ? $m[2] : '[^/]+';
            return '(?P<' . $name . '>' . $constraint . ')';
        }, $pattern);

        return '#^' . $regex . '$#';
    }
}
