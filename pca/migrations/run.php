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
 * PostgreSQL migration runner.
 *
 * Usage:
 *   php migrations/run.php                          # run all pending migrations
 *   php migrations/run.php <filename.sql>            # run a specific migration file
 *   php migrations/run.php --fresh                  # drop all tables, then migrate
 *   php migrations/run.php --drop                   # only drop all tables, skip migration
 *   php migrations/run.php --squash                 # dump current schema to 001_initial.sql and
 *                                                     remove old migration files
 *
 * Uses the psql CLI for reliable multi-statement execution.
 * Uses PGPASSWORD environment variable for password (more secure than -w flag).
 *
 * Environment variables:
 *   DB_HOST        (default: 127.0.0.1)
 *   DB_PORT        (default: 5432)
 *   DB_NAME        (default: postgres)         -- the database containing the chatbot_assistant schema
 *   DB_USER        (default: postgres)
 *   DB_PASS        (default: empty string)
 *   PG_SCHEMA      (default: chatbot_assistant)      -- schema in which tables are created
 */

// ── CLI auth helper ──────────────────────────────────────────────────────
function dbConnect(): string
{
    $host   = getenv('DB_HOST') ?: '127.0.0.1';
    $port   = getenv('DB_PORT') ?: '5432';
    $name   = getenv('DB_NAME') ?: 'postgres';
    $user   = getenv('DB_USER') ?: 'postgres';
    $pass   = getenv('DB_PASS') ?: '';

    // Export password so psql/pg_dump can pick it up (avoid -W prompt)
    putenv("PGPASSWORD={$pass}");

    // Build psql connection args (no -W — password comes from PGPASSWORD env)
    $args = sprintf(
        '-h %s -p %s -U %s -d %s',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($user),
        escapeshellarg($name)
    );
    return $args;
}

// Load config for env vars
require_once __DIR__ . '/../config/config.php';

$dbArgs  = dbConnect();
$schema  = getenv('PG_SCHEMA') ?: 'chatbot_assistant';
$fresh   = in_array('--fresh', $argv ?? [], true);
$dropOnly = in_array('--drop', $argv ?? [], true);
$squash  = in_array('--squash', $argv ?? [], true);

// ── Squash: dump schema, clean old files ──────────────────────────────────
if ($squash) {
    $migrationDir = __DIR__;
    $outputFile   = $migrationDir . '/001_initial.sql';

    echo "  SQUASH: dumping current schema...\n";

    // ── 1. Backup existing migrations ──
    $backupDir = dirname(__DIR__) . '/migrations_backup_' . date('Ymd_His');
    if (!mkdir($backupDir, 0755) && !is_dir($backupDir)) {
        echo "FAILED to create backup directory {$backupDir}\n";
        exit(1);
    }
    foreach (glob($migrationDir . '/*.sql') as $sqlFile) {
        copy($sqlFile, $backupDir . '/' . basename($sqlFile));
    }
    echo "Backed up migrations to {$backupDir}\n";

    // ── 2. pg_dump schema (no data, no ownership/ACL clutter) ──
    $tmpFile = tempnam(sys_get_temp_dir(), 'squash_');

    $dumpCmd = sprintf(
        'pg_dump --schema-only --no-owner --no-acl --schema=%s %s > %s 2>&1',
        escapeshellarg($schema),
        $dbArgs,
        escapeshellarg($tmpFile)
    );

    passthru($dumpCmd, $exitCode);

    if ($exitCode !== 0) {
        unlink($tmpFile);
        echo "FAILED to dump schema (exit code {$exitCode}).\n";
        exit(1);
    }

    // ── 3. Verify the dump is complete (should have many CREATE TABLE lines) ──
    $rawLines = file($tmpFile, FILE_IGNORE_NEW_LINES);
    $tableCount = count(preg_grep('/^CREATE\s+TABLE/i', $rawLines ?? []));

    if ($tableCount < 5) {
        echo "  Dump contains only {$tableCount} CREATE TABLE statements - looks incomplete.\n";
        echo "   The DB user '" . (getenv('DB_USER') ?: 'postgres') . "' may not have sufficient privileges.\n";
        echo "   Attempting fallback with superuser 'postgres' via local socket...\n";

        // Fallback: try sudo -u postgres (local peer auth)
        $fallbackCmd = sprintf(
            'sudo -u postgres pg_dump --schema-only --no-owner --no-acl --schema=%s -d %s > %s 2>&1',
            escapeshellarg($schema),
            escapeshellarg(getenv('DB_NAME') ?: 'postgres'),
            escapeshellarg($tmpFile)
        );
        passthru($fallbackCmd, $exitCode);

        if ($exitCode !== 0) {
            unlink($tmpFile);
            echo "FAILED even with sudo -u postgres.\n";
            echo "To recover, restore from backup: cp -a {$backupDir}/*.sql {$migrationDir}/\n";
            exit(1);
        }

        // Re-check
        $rawLines = file($tmpFile, FILE_IGNORE_NEW_LINES);
        $tableCount = count(preg_grep('/^CREATE\s+TABLE/i', $rawLines ?? []));
        if ($tableCount < 5) {
            unlink($tmpFile);
            echo "Still got only {$tableCount} tables after fallback. Dump is broken.\n";
            echo "To recover: cp -a {$backupDir}/*.sql {$migrationDir}/\n";
            exit(1);
        }
        echo "   Fallback succeeded ({$tableCount} tables).\n";
    }

    // ── 4. Write the raw dump to final location ──
    rename($tmpFile, $outputFile);

    // ── 5. Strip environment-specific noise ──
    $dumpContent = file_get_contents($outputFile);

    // Remove pg_dump version/session header comment lines
    $dumpContent = preg_replace('/^-- (pg_dump|Dumped from|Database).*$/m', '', $dumpContent);

    // Remove SET statements that are environment-specific
    $dumpContent = preg_replace(
        '/^SET (statement_timeout|lock_timeout|idle_in_transaction_session_timeout|client_encoding|standard_conforming_strings|xmloption|client_min_messages|row_security|default_table_access_method|default_tablespace|check_function_bodies|transaction_isolation|extra_float_digits).*$/m',
        '',
        $dumpContent
    );

    // Remove psql meta-commands \restrict and \unrestrict (not valid SQL)
    $dumpContent = preg_replace('/^\x5c(?:un)?restrict.*$/m', '', $dumpContent);

    // Remove COMMENT ON EXTENSION / COLUMN lines (environment-specific noise)
    $dumpContent = preg_replace('/^COMMENT ON (EXTENSION|COLUMN).*$/m', '', $dumpContent);

    // Remove empty lines that resulted from stripping, collapse 3+ to 2
    $dumpContent = preg_replace("/\n{3,}/", "\n\n", $dumpContent);
    $dumpContent = trim($dumpContent) . "\n";

    file_put_contents($outputFile, $dumpContent);

    // ── 6. Drop all tables in the schema so the database is clean ──
    echo "Dropping all tables in schema \"{$schema}\"...\n";

    // Dynamic table drop via PL/pgSQL (supports FK dependencies via CASCADE)
    $dropSql = sprintf(
        "DO \$\$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN (
                SELECT tablename
                FROM pg_tables
                WHERE schemaname = %s
            ) LOOP
                EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
            END LOOP;
        END
        \$\$;",
        escapeshellarg($schema)
    );

    $cmd = sprintf('psql -v ON_ERROR_STOP=1 %s -c %s 2>&1', $dbArgs, escapeshellarg($dropSql));
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "WARNING: failed to drop all tables (some may remain).\n";
    } else {
        echo "OK - all tables dropped.\n";
    }

    // Remove old migration .sql files (everything except the new dump)
    echo "Removing old migration files...\n";
    foreach (glob($migrationDir . '/*.sql') as $oldFile) {
        if (realpath($oldFile) !== realpath($outputFile)) {
            unlink($oldFile);
            echo "  removed: " . basename($oldFile) . "\n";
        }
    }

    echo "OK - schema dumped to {$outputFile}\n";
    echo "Database schema \"{$schema}\" is now empty. Run `php migrations/run2.php` to rebuild from the new single file.\n";
    exit(0);
}

// ── Specific file ─────────────────────────────────────────────────────
$singleFile = null;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg !== '--fresh' && $arg !== '--drop' && $arg !== '--squash') {
        $singleFile = $arg;
        break;
    }
}

$migrationDir = __DIR__;

if ($singleFile !== null) {
    // Resolve path — allow bare filename or relative/absolute path
    if (str_contains($singleFile, DIRECTORY_SEPARATOR)) {
        $resolved = realpath($singleFile);
    } else {
        $candidate = $migrationDir . '/' . $singleFile;
        $resolved = file_exists($candidate) ? realpath($candidate) : realpath($singleFile);
    }
    if ($resolved === false || !str_ends_with($resolved, '.sql')) {
        echo "Migration file not found: {$singleFile}\n";
        exit(1);
    }
    $files = [$resolved];
} else {
    $files = glob($migrationDir . '/*.sql');
    sort($files);
}

if (empty($files)) {
    echo "No migration files found.\n";
    exit(0);
}

// ── Fresh: drop everything ───────────────────────────────────────────────
if ($fresh || $dropOnly) {
    echo "  " . ($dropOnly ? "DROP ONLY" : "FRESH") . ": dropping all tables in schema \"{$schema}\"...\n";

    // Drop all tables in the schema with CASCADE to handle FK dependencies
    $dropSql = sprintf(
        "DO \$\$
        DECLARE
            r RECORD;
        BEGIN
            FOR r IN (
                SELECT tablename
                FROM pg_tables
                WHERE schemaname = %s
            ) LOOP
                EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
            END LOOP;
        END
        \$\$;",
        escapeshellarg($schema)
    );

    $cmd = sprintf('psql -v ON_ERROR_STOP=1 %s -c %s 2>&1', $dbArgs, escapeshellarg($dropSql));
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "FAILED to drop tables.\n";
        exit(1);
    }
    echo "OK\n\n";
}

// ── Drop-only: stop here ──────────────────────────────────────────────────
if ($dropOnly) {
    echo "Drop complete. Exiting (--drop mode).\n";
    exit(0);
}

// ── Run each SQL file ────────────────────────────────────────────────────
foreach ($files as $file) {
    $basename = basename($file);
    echo "Running: {$basename} ... ";

    // -v ON_ERROR_STOP=1 makes psql return non-zero exit on any error
    // -f reads the file and executes it
    $cmd = sprintf('psql -v ON_ERROR_STOP=1 %s -f %s 2>&1', $dbArgs, escapeshellarg($file));
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        echo "FAILED\n";
        exit(1);
    }
    echo "OK\n";
}

echo "\nAll migrations complete.\n";
