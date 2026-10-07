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
 * Database configuration.
 * Returns a PDO instance connected to PostgreSQL (pgvector).
 * Tables reside in the 'chatbot_assistant' schema — the search_path is set via
 * the DSN options so unqualified table references resolve correctly.
 */

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '5432');
    $schema = env('DB_SCHEMA', 'chatbot_schema');
    $name = env('DB_NAME', 'chatbot_assistant');
    $user = env('DB_USER', 'chatbot_user');
    $pass = env('DB_PASS', '');
    

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};options='--search_path={$schema}'";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * Set the PostgreSQL session variable for RLS tenant isolation.
 *
 * Must be called after authentication is established (user logged in).
 * When no user is authenticated, the variable is left unset — RLS policies
 * treat a NULL value as "bypass" (public widget / login / registration flows).
 */
function setRlsContext(): void
{
    $adminId = $_SESSION['admin_id'] ?? null;
    if ($adminId === null) {
        return; // No active session — RLS bypasses via current_admin_id() IS NULL
    }
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT set_config('app.admin_id', :id, false)");
    $stmt->bindValue(':id', (string) $adminId, PDO::PARAM_STR);
    $stmt->execute();
}

/**
 * Is this exception a "database unreachable" failure?
 *
 * Port of the JS port's lib/db-errors.ts classifier. Postgres driver/SQLSTATE
 * codes for a refused connection, terminated connection, authentication
 * failure, catalog or admin shutdown. When this returns true the front
 * controller renders the 503 outage page (or 503 JSON for /api paths) instead
 * of a raw stack-trace 500.
 */
function isDatabaseUnavailable(\Throwable $e): bool
{
    if (!$e instanceof \PDOException) {
        return false;
    }
    $code = (string) $e->getCode();
    if (preg_match('/^(08|57P0|3D000|28P01)/', $code)) {
        return true;
    }
    // Drivers also put the SQLSTATE in the message when getCode() is generic.
    $msg = $e->getMessage();
    return (bool) preg_match('/SQLSTATE\[(08|57P0|3D000|28P01)/', $msg)
        || str_contains($msg, 'Connection refused')
        || str_contains($msg, 'connection refused')
        || str_contains($msg, 'server has closed the connection');
}

/**
 * Render the database-outage screen and exit.
 *
 * API paths get a machine-readable 503 JSON (widget.js shows its own message
 * for any non-2xx); HTML paths get the styled outage page.
 */
function renderDatabaseUnavailable(): never
{
    http_response_code(503);
    header('Retry-After: 30');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_starts_with((string) $path, '/api/')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'The database is temporarily unavailable. Please try again shortly.']);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Service temporarily unavailable</title>
<link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body>
<div class="outage-wrap">
  <div class="outage-card">
    <span class="outage-badge">503</span>
    <h1>Service temporarily unavailable</h1>
    <p class="outage-lead">The database is unreachable right now, so this page cannot load. Your data is safe; this is a connectivity problem, not a data problem.</p>
    <a class="btn btn-primary outage-retry" href="/">Try again</a>
  </div>
</div>
</body>
</html>';
    exit;
}

/**
 * Timezone-aware date formatting shortcut.
 *
 * Converts a UTC database timestamp to the user's preferred timezone for display.
 * Falls back to UTC when no timezone is set in the session.
 *
 * @param  string $dbTimestamp  UTC timestamp from the database
 * @param  string $format       PHP date() format (default: 'M j, Y g:i A')
 * @return string
 */
function dt(string $dbTimestamp, string $format = 'M j, Y g:i A'): string
{
    $tz = $_SESSION['timezone'] ?? 'UTC';
    return \App\Util\DateTimeHelper::format($dbTimestamp, $tz, $format);
}
