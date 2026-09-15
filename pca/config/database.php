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
