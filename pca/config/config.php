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

/**
 * Application configuration loader.
 * Reads from .env if present, falls back to environment variables.
 */

$rootDir = dirname(__DIR__);

// Load .env file. Shell-provided environment variables WIN over .env values
// (an operator passing DB_NAME on the command line or to php -S intends it),
// so only keys not already present in the real environment are applied.
$shellEnv = [];
foreach (explode("\n", shell_exec('env') ?? '') as $line) {
    $k = explode('=', $line, 2)[0] ?? '';
    if ($k !== '') {
        $shellEnv[$k] = true;
    }
}
$envFile = $rootDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip matching surrounding quotes so SMTP_PASS="secret" means
            // "secret", not '"secret"' (a literal-quote password fails SMTP
            // AUTH with 535 while the operator thinks the value is right).
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if (!isset($shellEnv[$key])) {
                putenv("$key=$value");
            }
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    // Real environment (shell/proc) wins over .env values.
    $real = getenv($key);
    if ($real !== false && $real !== '') {
        return $real;
    }
    return $_ENV[$key] ?? $default;
}

return [
    'env'     => env('APP_ENV', 'production'),
    'url'     => env('APP_URL', 'https://example.com'),
    'session' => [
        'lifetime'    => (int) env('SESSION_LIFETIME', 120),
        'cookie_name' => env('SESSION_COOKIE_NAME', 'chatbot_session'),
    ],

    'mail' => [
        'host'        => env('SMTP_HOST', 'localhost'),
        'port'        => (int) env('SMTP_PORT', 465),
        'auth'        => (bool) env('SMTP_AUTH', true),
        'user'        => env('SMTP_USER', ''),
        'pass'        => env('SMTP_PASS', ''),
        'encryption'  => env('SMTP_ENCRYPTION', 'ssl'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@mydomain.com'),
        'from_name'    => env('MAIL_FROM_NAME', 'Chatbot Assistant'),
    ],
];
