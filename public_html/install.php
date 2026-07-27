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
 * Chatbot Assistant — Installation Wizard
 *
 * Guides the user through:
 *   1. Database & environment configuration
 *   2. Database migration (all SQL files)
 *   3. Admin user creation (no MFA)
 *   4. Industry template seeding
 *
 * ── DELETE THIS FILE AFTER SUCCESSFUL INSTALLATION ──
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. Bootstrap (minimal — no app config, no .env)
// ─────────────────────────────────────────────────────────────────────────────

// Ensure we have a session for wizard step tracking
if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

// Helper: reset wizard
function resetWizard(): void
{
    $_SESSION['install_step'] = 0;
    $_SESSION['install_errors'] = [];
    $_SESSION['install_success'] = '';
}

// Helper: read a form value with default
function formVal(string $key, string $default = ''): string
{
    return htmlspecialchars($_POST[$key] ?? $_SESSION['install_data'][$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

// Helper: require a non-empty string from POST
function requirePost(string $key): ?string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $v !== '' ? $v : null;
}

// Known permission keys (must match UserPermission::allPermissions())
// Only widget_* (endpoint toggle) permissions are active; page-level
// permissions are no longer used — admin users have full access automatically.
const PERMISSION_KEYS = [
    'widget_chat', 'widget_quick_answers',
];

// ─────────────────────────────────────────────────────────────────────────────
// 2. Handle POST actions
// ─────────────────────────────────────────────────────────────────────────────

$step = (int) ($_SESSION['install_step'] ?? 0);
$errors = [];
$success = $_SESSION['install_success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    // ── Step 1: .env configuration ──────────────────────────────────────
    if ($action === 'configure_env') {
        $dbHost  = requirePost('db_host');
        $dbPort  = requirePost('db_port');
        $dbName  = requirePost('db_name');
        $dbUser  = requirePost('db_user');
        $dbPass  = requirePost('db_pass');
        $appUrl  = requirePost('app_url');
        $smtpHost = requirePost('smtp_host');
        $smtpPort = requirePost('smtp_port');
        $smtpAuth = requirePost('smtp_auth');
        $smtpUser = requirePost('smtp_user');
        $smtpPass = requirePost('smtp_pass');
        $smtpEncryption = requirePost('smtp_encryption');
        $mailFrom  = requirePost('mail_from_address');
        $mailName  = requirePost('mail_from_name');

        if (!$dbHost)  $errors[] = 'DB Host is required.';
        if (!$dbPort)  $errors[] = 'DB Port is required.';
        if (!$dbName)  $errors[] = 'DB Name is required.';
        if (!$dbUser)  $errors[] = 'DB User is required.';
        if (!$dbPass)  $errors[] = 'DB Password is required.';
        if (!$appUrl)  $errors[] = 'App URL is required.';
        if (!$smtpHost)  $errors[] = 'SMTP Host is required.';
        if (!$smtpPort)  $errors[] = 'SMTP Port is required.';
        if ($smtpAuth === 'true' && !$smtpUser)  $errors[] = 'SMTP User is required when authentication is enabled.';
        if ($smtpAuth === 'true' && !$smtpPass)  $errors[] = 'SMTP Password is required when authentication is enabled.';
        if (!$smtpEncryption) $errors[] = 'SMTP Encryption is required.';
        if (!$mailFrom)  $errors[] = 'Mail From Address is required.';

        if (empty($errors)) {
            $envContent = "# Database\n" .
                "DB_HOST={$dbHost}\n" .
                "DB_PORT={$dbPort}\n" .
                "DB_NAME={$dbName}\n" .
                "DB_USER={$dbUser}\n" .
                "DB_PASS={$dbPass}\n" .
                "\n# App\n" .
                "APP_ENV=production\n" .
                "APP_URL={$appUrl}\n" .
                "\n# Session\n" .
                "SESSION_COOKIE_NAME=chatbot_assistant_session\n" .
                "SESSION_LIFETIME=1440\n" .
                "\n# SMTP / Mail (PHPMailer)\n" .
                "SMTP_HOST={$smtpHost}\n" .
                "SMTP_PORT={$smtpPort}\n" .
                "SMTP_AUTH={$smtpAuth}\n" .
                "SMTP_USER={$smtpUser}\n" .
                "SMTP_PASS={$smtpPass}\n" .
                "SMTP_ENCRYPTION={$smtpEncryption}\n" .
                "MAIL_FROM_ADDRESS={$mailFrom}\n" .
                "MAIL_FROM_NAME={$mailName}\n";

            $envPath = __DIR__ . '/../.env';

            // Test the DB connection first
            try {
                $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                // Test a simple query
                $pdo->query('SELECT 1');

                // Check PostgreSQL version (14+ required for pgvector)
                $fullVersion = $pdo->query("SELECT current_setting('server_version') AS ver")->fetchColumn();
                if (preg_match('/^(\d+)/', $fullVersion, $m)) {
                    $serverVer = (int) $m[1];
                    if ($serverVer < 14) {
                        $errors[] = "PostgreSQL 14+ is required, but the server is running {$fullVersion}. Please upgrade your PostgreSQL server.";
                    }
                }
            } catch (\PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }

            if (empty($errors)) {
                $written = file_put_contents($envPath, $envContent, LOCK_EX);
                if ($written === false) {
                    $errors[] = 'Failed to write .env file. Check filesystem permissions.';
                } else {
                    chmod($envPath, 0600);
                    // Store connection info in session for migration step
                    $_SESSION['install_data'] = [
                        'db_host' => $dbHost,
                        'db_port' => $dbPort,
                        'db_name' => $dbName,
                        'db_user' => $dbUser,
                        'db_pass' => $dbPass,
                    ];
                    $step = 1;
                    $_SESSION['install_step'] = 1;
                    $_SESSION['install_success'] = '✅ .env file written successfully. Database connection verified.';
                }
            }
        }
    }

    // ── Step 2: Run database migrations ─────────────────────────────────
    elseif ($action === 'run_migrations') {
        $data = $_SESSION['install_data'] ?? null;
        if (!$data) {
            $errors[] = 'Session expired. Please start again.';
        } else {
            try {
                $dsn = "pgsql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_name']}";
                $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Run SQL migrations in order (sorted by filename)
                $migrationDir = __DIR__ . '/../migrations';
                $migrationFiles = glob($migrationDir . '/*.sql');
                if ($migrationFiles === false) {
                    $errors[] = 'Failed to scan migrations directory.';
                } else {
                    // Strip path, keep just the filename
                    $migrationFiles = array_map('basename', $migrationFiles);
                    sort($migrationFiles, SORT_STRING);
                }
                $executed = 0;

                foreach ($migrationFiles as $file) {
                    $path = $migrationDir . '/' . $file;
                    if (!file_exists($path)) {
                        $errors[] = "Migration file not found: {$file}";
                        break;
                    }
                    $sql = file_get_contents($path);
                    if ($sql === false || trim($sql) === '') {
                        continue;
                    }

                    // Strip SQL comment lines (-- to end of line) so semicolons
                    // inside comments don't cause false splits.
                    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                    // Split by semicolons, respecting PostgreSQL dollar-quoting
                    // ($$...$$ can contain semicolons inside function bodies).
                    $statements = [];
                    $buf = '';
                    $len = strlen($sql);
                    $i = 0;
                    $dqTag = null;   // null = not in dollar-quote; string = the opening tag
                    $sq = false;      // in single-quote string

                    while ($i < $len) {
                        $ch = $sql[$i];

                        if ($sq) {
                            // Inside single-quote string
                            if ($ch === "'") {
                                // Check for escaped quote ''
                                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                                    $buf .= "''";
                                    $i += 2;
                                    continue;
                                }
                                $buf .= "'";
                                $sq = false;
                            } else {
                                $buf .= $ch;
                            }
                            $i++;
                            continue;
                        }

                        if ($dqTag !== null) {
                            // Inside dollar-quote — find closing tag
                            if (substr($sql, $i, strlen($dqTag)) === $dqTag) {
                                $buf .= $dqTag;
                                $i += strlen($dqTag);
                                $dqTag = null;
                                continue;
                            }
                            $buf .= $ch;
                            $i++;
                            continue;
                        }

                        // Not in any quote — check transitions
                        if ($ch === "'") {
                            $buf .= "'";
                            $sq = true;
                            $i++;
                            continue;
                        }

                        if ($ch === '$') {
                            // Check for $$ or $tag$
                            $tagEnd = strpos($sql, '$', $i + 1);
                            if ($tagEnd !== false) {
                                $tag = substr($sql, $i, $tagEnd - $i + 1);
                                $buf .= $tag;
                                $dqTag = $tag;
                                $i = $tagEnd + 1;
                                continue;
                            }
                        }

                        if ($ch === ';') {
                            // End of a statement
                            $trimmed = trim($buf);
                            if ($trimmed !== '') {
                                $statements[] = $buf;
                            }
                            $buf = '';
                            $i++;
                            continue;
                        }

                        $buf .= $ch;
                        $i++;
                    }

                    // Last statement
                    $trimmed = trim($buf);
                    if ($trimmed !== '') {
                        $statements[] = $buf;
                    }

                    foreach ($statements as $stmt) {
                        $stmt = trim($stmt);
                        if ($stmt !== '') {
                            $pdo->exec($stmt);
                        }
                    }
                    $executed++;
                }

                if (empty($errors)) {
                    $step = 2;
                    $_SESSION['install_step'] = 2;
                    $_SESSION['install_success'] = "✅ Database migrated successfully ({$executed} files executed).";
                }
            } catch (\PDOException $e) {
                $errors[] = 'Migration failed: ' . $e->getMessage();
            }
        }
    }

    // ── Step 3: Create admin account + user ────────────────────────
    elseif ($action === 'create_admin') {
        $orgName    = requirePost('org_name');
        $adminName  = requirePost('admin_name');
        $adminEmail = requirePost('admin_email');
        $adminPass  = requirePost('admin_password');
        $adminConfirm = requirePost('admin_password_confirm');

        if (!$orgName) $errors[] = 'Organization / company name is required.';
        if (!$adminName)  $errors[] = 'Admin name is required.';
        if (!$adminEmail) $errors[] = 'Admin email is required.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!$adminPass)  $errors[] = 'Password is required.';
        if (strlen($adminPass ?? '') < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($adminPass !== $adminConfirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $data = $_SESSION['install_data'] ?? null;
            if (!$data) { $errors[] = 'Session expired.'; }
            else {
                try {
                    $dsn = "pgsql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_name']}";
                    $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $dbName = $pdo->query('SELECT current_database()')->fetchColumn();

                    // Check org name uniqueness (admins flattened into users)
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE company_name = :name AND role = :role');
                    $stmt->execute([':name' => $orgName, ':role' => 'admin']);
                    if ($stmt->fetch()) {
                        $errors[] = 'An admin account with this name already exists.';
                    } else {
                        // Check email uniqueness
                        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
                        $stmt->execute([':email' => strtolower(trim($adminEmail))]);
                        if ($stmt->fetch()) {
                            $errors[] = 'A user with this email already exists.';
                        } else {
                            // Generate a unique slug (against users, admins flattened)
                            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($orgName)), '-');
                            $baseSlug = $slug;
                            $i = 1;
                            $stmt = $pdo->prepare('SELECT id FROM users WHERE slug = :slug AND role = \'admin\'');
                            do {
                                $checkSlug = $i === 1 ? $slug : $slug . '-' . $i;
                                $stmt->execute([':slug' => $checkSlug]);
                                $exists = $stmt->fetch();
                                $i++;
                            } while ($exists);

                            // Create admin user directly (flat model — no admins table)
                            // Defer the self-referencing FK so we can insert admin_id=0 as placeholder,
                            // then update it to self-reference below.
                            // Must wrap in an explicit transaction because PDO auto-commits each exec().
                            $pdo->beginTransaction();
                            $pdo->exec("SET CONSTRAINTS fk_users_admin DEFERRED");
                            $now = date('Y-m-d H:i:s');
                            $hash = password_hash($adminPass, PASSWORD_ARGON2ID);
                            $sql = sprintf(
                                "INSERT INTO users (admin_id, name, email, password_hash, role, company_name, slug, is_active, email_verified_at, mfa_enabled, created_at, updated_at)
                                 VALUES (0, %s, %s, %s, 'admin', %s, %s, 1, %s, 0, %s, %s)",
                                $pdo->quote($adminName),
                                $pdo->quote(strtolower(trim($adminEmail))),
                                $pdo->quote($hash),
                                $pdo->quote($orgName),
                                $pdo->quote($checkSlug),
                                $pdo->quote($now),
                                $pdo->quote($now),
                                $pdo->quote($now)
                            );
                            $pdo->exec($sql);
                            $adminId = (int) $pdo->lastInsertId();

                            // Self-referencing FK — now admin_id = id so the deferred check passes at commit
                            $pdo->prepare('UPDATE users SET admin_id = id WHERE id = :id')
                                ->execute([':id' => $adminId]);
                            $pdo->exec("SET CONSTRAINTS fk_users_admin IMMEDIATE");
                            $pdo->commit();

                            // Seed admin permissions
                            $permStmt = $pdo->prepare(
                                'INSERT INTO user_permissions (user_id, permission, enabled) VALUES (:aid, :perm, 1) ON CONFLICT DO NOTHING'
                            );
                            foreach (PERMISSION_KEYS as $perm) {
                                $permStmt->execute([':aid' => $adminId, ':perm' => $perm]);
                            }

                            $step = 3;
                            $_SESSION['install_step'] = 3;
                            $_SESSION['install_success'] = "✅ Admin account '" . $orgName . "' created successfully.";
                        }
                    }
                } catch (\PDOException $e) {
                    $errors[] = 'Failed to create admin: ' . $e->getMessage();
                }
            }
        }
    }

    // ── Back to env config from migrations ─────────────────────────
    elseif ($action === 'back_to_env') {
        $_SESSION['install_step'] = 0;
        $_SESSION['install_success'] = '';
        $step = 0;
    }

    elseif ($action === 'finish') {
        $step = 99;  // done
        $_SESSION['install_step'] = 99;
        $_SESSION['install_success'] = '';
    }

    // ── Reset ────────────────────────────────────────────────────────────
    elseif ($action === 'reset') {
        resetWizard();
        $step = 0;
    }

    // Store errors back into session for the next request
    $_SESSION['install_errors'] = $errors;
    $_SESSION['install_success'] = $success;
    $_SESSION['install_step'] = $step;

    // Redirect to prevent form re-submission on refresh
    $scriptUrl = $_SERVER['SCRIPT_NAME'];
    header("Location: {$scriptUrl}");
    exit;
}

// Handle GET-based reset (from the "Re-run Installer" link)
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    resetWizard();
    $step = 0;
}

// Read session state after redirect
$step    = (int) ($_SESSION['install_step'] ?? 0);
$errors  = $_SESSION['install_errors'] ?? [];
$success = $_SESSION['install_success'] ?? '';

// Clean up flash messages for next load
$_SESSION['install_errors'] = [];
$_SESSION['install_success'] = '';

// ─────────────────────────────────────────────────────────────────────────────
// 3. Detect if .env already exists (show warning)
// ─────────────────────────────────────────────────────────────────────────────

$envExists = file_exists(__DIR__ . '/../.env');

// If .env exists and user hasn't started or has reset, and step is 0,
// warn but let them re-run if they want
if ($envExists && $step === 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // We show a warning inline instead of blocking
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Render the HTML UI
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Assistant — Installation Wizard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background: #f0f2f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .wizard {
            max-width: 640px;
            width: 100%;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
            overflow: hidden;
        }
        .wizard-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: #fff;
            padding: 1.75rem 2rem;
        }
        .wizard-header h1 { font-size: 1.35rem; font-weight: 600; margin-bottom: .25rem; }
        .wizard-header p { font-size: .875rem; opacity: .85; }
        .wizard-body { padding: 2rem; }

        .step-indicator {
            display: flex;
            gap: .25rem;
            margin-bottom: 1.75rem;
            justify-content: center;
        }
        .dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #d1d5db;
            transition: background .2s;
        }
        .dot.active { background: #2563eb; }
        .dot.done { background: #16a34a; }

        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: #111;
        }

        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .025em;
        }
        .form-group input {
            width: 100%;
            padding: .65rem .75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: .925rem;
            transition: border-color .15s;
            background: #fff;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #b91c1c;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .875rem;
        }
        .error-box ul { margin: .25rem 0 0 1.25rem; }
        .success-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            color: #15803d;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .875rem;
        }
        .warning-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 6px;
            color: #92400e;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .875rem;
        }

        .btn {
            display: inline-block;
            padding: .65rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: .925rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
            text-decoration: none;
        }
        .btn:active { transform: scale(.97); }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #93a8e0; cursor: not-allowed; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover { background: #15803d; }
        .btn-group { display: flex; gap: .75rem; margin-top: 1.5rem; flex-wrap: wrap; }

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            font-size: .875rem;
            line-height: 1.6;
        }
        .summary-box strong { color: #111; }

        .checkbox-group { margin: .5rem 0; }
        .checkbox-group label {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            font-size: .875rem;
            color: #374151;
            cursor: pointer;
            line-height: 1.4;
        }
        .checkbox-group input[type="checkbox"] { margin-top: .15rem; }

        hr { border: none; border-top: 1px solid #e5e7eb; margin: 1.25rem 0; }

        .footer-note {
            text-align: center;
            font-size: .8rem;
            color: #9ca3af;
            padding: 1rem 2rem;
            border-top: 1px solid #e5e7eb;
        }
        .delete-notice {
            background: #fef2f2;
            border: 2px solid #dc2626;
            border-radius: 8px;
            color: #991b1b;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
            font-size: .9rem;
            font-weight: 600;
            text-align: center;
        }
        .delete-notice code {
            background: #fee2e2;
            padding: .15rem .4rem;
            border-radius: 4px;
            font-size: .85rem;
        }
    </style>
</head>
<body>
<div class="wizard">
    <div class="wizard-header">
        <h1>Chatbot Assistant — Installation</h1>
        <p>Set up your environment, database, and initial accounts</p>
    </div>

    <div class="wizard-body">
        <?php if ($step === 99): ?>
            <!-- COMPLETED ---------------------------------------------- -->
            <div class="step-title">🎉 Installation Complete!</div>

            <?php if (!empty($success)): ?>
                <div class="success-box"><?= $success ?></div>
            <?php endif; ?>

            <div class="summary-box">
                <p><strong>Your Chatbot Assistant is ready to use.</strong></p>
                <p style="margin-top:.5rem;">
                    Admin login: please note the credentials you entered.
                </p>
            </div>

            <div class="delete-notice">
                ⚠️ For security, you MUST delete this installer now:<br>
                <code>rm public_html/install.php</code>
            </div>

            <div class="btn-group">
                <a href="<?= htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8') ?>?reset=1"
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to re-run installation? This will NOT undo any changes already made to the database.')">
                    Re-run Installer
                </a>
            </div>

        <?php elseif ($envExists && $step === 0 && empty($errors)): ?>
            <!-- .ENV ALREADY EXISTS --------------------------------- -->
            <div class="warning-box">
                ⚠️ <strong>.env file already exists.</strong> Running the installer again will overwrite it.
            </div>
            <form method="post">
                <input type="hidden" name="_action" value="configure_env">
                <p style="font-size:.875rem;color:#6b7280;margin-bottom:1.25rem;">
                    You can re-configure your environment below. Existing database data will not be affected.
                </p>
                <?php include_component('env_form'); ?>
            </form>

        <?php elseif ($step === 0): ?>
            <!-- STEP 0: ENV CONFIG ---------------------------------- -->
            <?php renderMessages($errors, $success); ?>
            <div class="step-title">Step 1 of 3: Environment Configuration</div>
            <p style="font-size:.875rem;color:#6b7280;margin-bottom:1.25rem;">
                Enter your database and mail server details. These will be written to <code>.env</code>.
            </p>
            <form method="post">
                <input type="hidden" name="_action" value="configure_env">
                <?php include_component('env_form'); ?>
            </form>

        <?php elseif ($step === 1): ?>
            <!-- STEP 1: MIGRATIONS ---------------------------------- -->
            <?php renderMessages($errors, $success); ?>
            <div class="step-title">Step 2 of 3: Database Migration</div>
            <p style="font-size:.875rem;color:#6b7280;margin-bottom:1rem;">
                The database connection was verified. Now run the migrations to create all required tables.
            </p>

            <?php
            $idata = $_SESSION['install_data'] ?? [];
            $tpl_db    = $idata['db_name'] ?? 'chatbot_assistant';
            $tpl_user  = $idata['db_user'] ?? 'chatbot_user';
            $tpl_pass  = $idata['db_pass'] ?? '';
            $tpl_schema = 'chatbot_schema';
            $tpl_host  = $idata['db_host'] ?? 'localhost';
            $tpl_port  = $idata['db_port'] ?? '5432';
            ?>

            <details style="margin-bottom:1.25rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;cursor:pointer;">
                <summary style="font-size:.9rem;font-weight:600;color:#1e40af;cursor:pointer;">
                    🛠️ Pre-Migration: Create Database &#038; Role (click to expand)
                </summary>
                <p style="font-size:.8rem;color:#6b7280;margin:.75rem 0 0 0;cursor:auto;">
                    Run these commands as a PostgreSQL superuser (<code>postgres</code>) before migrating.
                    Click any block to copy.
                </p>
                <div style="margin:.75rem 0 0;cursor:auto;">
                    <?php
                    $cmds = [
                        ['Start psql as postgres superuser', 'sudo -u postgres psql'],
                        ['Create the database', "CREATE DATABASE {$tpl_db};"],
                        ['Create the role/user with login', "CREATE ROLE {$tpl_user} WITH LOGIN PASSWORD '{$tpl_pass}' CREATEDB;"],
                        ['Connect to the new DB', "\\c {$tpl_db}"],
                        ['Create the schema', "CREATE SCHEMA {$tpl_schema};"],
                        ['Enable pgvector extension (requires superuser)', "CREATE EXTENSION IF NOT EXISTS vector SCHEMA {$tpl_schema};"],
                        ['Grant usage on schema to the role', "GRANT USAGE, CREATE ON SCHEMA {$tpl_schema} TO {$tpl_user};"],
                        ['Set default search_path for the user', "ALTER ROLE {$tpl_user} SET search_path TO {$tpl_schema};"],
                        ['Optional: revoke public schema access', "REVOKE ALL ON SCHEMA public FROM {$tpl_user};"],
                        ['Optional: revoke public database access', "REVOKE ALL ON DATABASE {$tpl_db} FROM PUBLIC;"],
                        ['Grant connect on database to the user', "GRANT CONNECT ON DATABASE {$tpl_db} TO {$tpl_user};"],
                        ['Grant create on database to the user', "GRANT CREATE ON DATABASE {$tpl_db} TO {$tpl_user};"],
                        ['Set the user password', "ALTER USER {$tpl_user} WITH PASSWORD '{$tpl_pass}';"],
                        ['Reconnect as the new user', "\\c {$tpl_db}"],
                        ['Grant usage and create on schema', "GRANT USAGE, CREATE ON SCHEMA {$tpl_schema} TO {$tpl_user};"],
                    ];
                    foreach ($cmds as $item):
                        [$label, $cmd] = $item;
                        $escaped = htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8');
                        $clipJson = htmlspecialchars(json_encode($cmd), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div style="margin:.65rem 0;">
                            <?php if ($label !== ''): ?>
                                <div style="font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.2rem;"><?= htmlspecialchars($label) ?></div>
                            <?php endif; ?>
                            <code style="display:block;background:#fff;color:#000;padding:.5rem .75rem;border-radius:4px;font-size:.8rem;white-space:pre-wrap;word-break:break-all;cursor:pointer;border:1px dashed #5f5f5f;"
                                  onclick="navigator.clipboard.writeText(<?= $clipJson ?>).then(()=>{let t=this;t.style.outline='2px solid #22c55e';setTimeout(()=>t.style.outline='',20000)})"
                                  title="Click to copy"><?= nl2br($escaped) ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:.75rem;font-size:.8rem;cursor:auto;">
                    <strong style="color:#374151;">Connection String:</strong><br>
                    <?php
                    $connStr = "postgresql://{$tpl_user}:{$tpl_pass}@{$tpl_host}:{$tpl_port}/{$tpl_db}?search_path={$tpl_schema}";
                    $connEsc = htmlspecialchars($connStr, ENT_QUOTES, 'UTF-8');
                    $connClip = htmlspecialchars(json_encode($connStr), ENT_QUOTES, 'UTF-8');
                    ?>
                    <code style="display:block;background:#fff;color:#000;padding:.5rem .75rem;border-radius:4px;font-size:.8rem;word-break:break-all;cursor:pointer;margin-top:.25rem;border:1px dashed #5f5f5f;"
                          onclick="navigator.clipboard.writeText(<?= $connClip ?>).then(()=>{let t=this;t.style.outline='2px solid #22c55e';setTimeout(()=>t.style.outline='',20000)})"
                          title="Click to copy"><?= $connEsc ?></code>
                </div>
                <div style="margin-top:.5rem;font-size:.8rem;cursor:auto;">
                    <?php
                    $psqlStr = "psql -U {$tpl_user} -d {$tpl_db}";
                    $psqlEsc = htmlspecialchars($psqlStr, ENT_QUOTES, 'UTF-8');
                    $psqlClip = htmlspecialchars(json_encode($psqlStr), ENT_QUOTES, 'UTF-8');
                    ?>
                    <code style="display:block;background:#fff;color:#000;padding:.5rem .75rem;border-radius:4px;font-size:.8rem;word-break:break-all;cursor:pointer;border:1px dashed #5f5f5f;"
                          onclick="navigator.clipboard.writeText(<?= $psqlClip ?>).then(()=>{let t=this;t.style.outline='2px solid #22c55e';setTimeout(()=>t.style.outline='',20000)})"
                          title="Click to copy"><?= $psqlEsc ?></code>
                </div>
            </details>

            <div class="summary-box">
                <p>The following migration files will be executed:</p>
                <ul style="margin:.5rem 0 0 1.25rem;font-size:.85rem;color:#4b5563;">
                    <?php
                    $migrationDir = __DIR__ . '/../migrations';
                    $migrationFiles = glob($migrationDir . '/*.sql');
                    if ($migrationFiles !== false) {
                        $migrationFiles = array_map('basename', $migrationFiles);
                        sort($migrationFiles, SORT_STRING);
                        foreach ($migrationFiles as $file) {
                            echo '                    <li>' . htmlspecialchars($file) . '</li>' . "\n";
                        }
                    }
                    ?>
                </ul>
            </div>
            <form method="post">
                <input type="hidden" name="_action" value="run_migrations">
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Run Migrations</button>
                    <button type="submit" name="_action" value="back_to_env" class="btn btn-secondary" formnovalidate>Back</button>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <!-- STEP 2: ADMIN USER CREATION -------------------- -->
            <?php renderMessages($errors, $success); ?>
            <div class="step-title">Step 3 of 3: Create an Admin User</div>
            <p style="font-size:.875rem;color:#6b7280;margin-bottom:1.25rem;">
                Create the admin account for your organization.
                This account has full dashboard access. <strong>MFA is not enabled</strong>
                (you can enable it later in Settings).
            </p>
            <form method="post">
                <input type="hidden" name="_action" value="create_admin">

                <div class="form-group">
                    <label for="org_name">Organization / Company Name</label>
                    <input type="text" id="org_name" name="org_name" required
                           placeholder="e.g. Acme Corp">
                </div>
                <hr>
                <div class="form-group">
                    <label for="admin_name">Admin Full Name</label>
                    <input type="text" id="admin_name" name="admin_name" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Admin Email</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_password">Password (min 8 chars)</label>
                        <input type="password" id="admin_password" name="admin_password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="admin_password_confirm">Confirm Password</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="8">
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                    <button type="submit" name="_action" value="back_to_env" class="btn btn-secondary" formnovalidate>Back</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <!-- STEP 3: ADMIN CREATED ------------------------- -->
            <?php renderMessages($errors, $success); ?>
            <div class="step-title">Admin Created Successfully</div>
            <p style="font-size:.875rem;color:#6b7280;margin-bottom:1.25rem;">
                The admin account has been created.
                Industry templates have also been seeded.
            </p>
            <div class="summary-box">
                <p><strong>What's next?</strong></p>
                <ul style="margin:.5rem 0 0 1.25rem;font-size:.85rem;color:#4b5563;">
                    <li>Other users can now register under this admin account</li>
                    <li>Login and start managing your chatbots</li>
                </ul>
            </div>
            <form method="post">
                <input type="hidden" name="_action" value="finish">
                <button type="submit" class="btn btn-success">Finish Installation</button>
            </form>

            <div style="margin-top:2rem;padding:1rem 1.25rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                <p style="color:#dc2626;font-size:1.1rem;font-weight:700;margin:0 0 .35rem 0;">
                    ⚠️ Delete this file after use
                </p>
                <p style="color:#991b1b;font-size:.875rem;margin:0;line-height:1.5;">
                    Leaving <code style="background:#fca5a5;color:#7f1d1d;padding:.1rem .35rem;border-radius:3px;font-size:.8rem;">install.php</code> on the server allows anyone to
                    reconfigure the chatbot, reset the database, or create new accounts.
                    <strong>Delete it now</strong> by removing the file from your
                    <code style="background:#fca5a5;color:#7f1d1d;padding:.1rem .35rem;border-radius:3px;font-size:.8rem;">public_html/</code> directory.
                </p>
            </div>

        <?php endif; ?>
    </div>

    <div class="footer-note">
        Chatbot Assistant Installer
    </div>
</div>
</body>
</html>
<?php

// ─────────────────────────────────────────────────────────────────────────────
// 5. Template helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render error and success messages.
 */
function renderMessages(array $errors, string $success): void
{
    if (!empty($errors)) {
        echo '<div class="error-box"><strong>Please fix the following errors:</strong><ul>';
        foreach ($errors as $e) {
            echo '<li>❌ ' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }
    if ($success !== '') {
        echo '<div class="success-box">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

/**
 * Render the .env configuration form fields (shared between step 0 and re-run).
 */
function include_component(string $component): void
{
    if ($component === 'env_form') {
        ?>
        <div class="form-group">
            <label for="app_url">App URL</label>
            <input type="url" id="app_url" name="app_url"
                   value="<?= formVal('app_url', 'http://localhost:8000') ?>" required
                   placeholder="http://localhost:8000">
        </div>
        <hr>
        <div class="form-row">
            <div class="form-group">
                <label for="db_host">DB Host</label>
                <input type="text" id="db_host" name="db_host" value="<?= formVal('db_host', '127.0.0.1') ?>" required>
            </div>
            <div class="form-group">
                <label for="db_port">DB Port</label>
                <input type="text" id="db_port" name="db_port" value="<?= formVal('db_port', '5432') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="db_name">DB Name</label>
                <input type="text" id="db_name" name="db_name" value="<?= formVal('db_name', 'chatbot_assistant') ?>" required>
            </div>
            <div class="form-group">
                <label for="db_user">DB User</label>
                <input type="text" id="db_user" name="db_user" value="<?= formVal('db_user', 'chatbot_user') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="db_pass">DB Password</label>
            <input type="password" id="db_pass" name="db_pass" value="" required>
        </div>
        <hr>
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?= formVal('smtp_host', 'localhost') ?>" required>
            </div>
            <div class="form-group">
                <label for="smtp_port">SMTP Port</label>
                <input type="text" id="smtp_port" name="smtp_port" value="<?= formVal('smtp_port', '465') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_user">SMTP User</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?= formVal('smtp_user') ?>" required>
            </div>
            <div class="form-group">
                <label for="smtp_pass">SMTP Password</label>
                <input type="password" id="smtp_pass" name="smtp_pass" value="" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_encryption">SMTP Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption" required
                        style="width:100%;padding:.65rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.925rem;background:#fff;">
                    <?php
                    $encSubmitted = isset($_POST['smtp_encryption']) || isset($_SESSION['install_data']['smtp_encryption']);
                    $smtpEncVal = formVal('smtp_encryption');
                    ?>
                    <option value="ssl" <?= ($smtpEncVal ?: 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="tls" <?= $smtpEncVal === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="" <?= $encSubmitted && $smtpEncVal === '' ? 'selected' : '' ?>>None</option>
                </select>
            </div>
            <div class="form-group">
                <label for="smtp_auth">SMTP Authentication</label>
                <select id="smtp_auth" name="smtp_auth" required
                        style="width:100%;padding:.65rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.925rem;background:#fff;">
                    <?php $smtpAuthVal = formVal('smtp_auth'); ?>
                    <option value="true" <?= ($smtpAuthVal ?: 'true') === 'true' ? 'selected' : '' ?>>Enabled</option>
                    <option value="false" <?= $smtpAuthVal === 'false' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="mail_from_address">Mail From Address</label>
                <input type="email" id="mail_from_address" name="mail_from_address"
                       value="<?= formVal('mail_from_address', 'noreply@example.com') ?>" required>
            </div>
            <div class="form-group">
                <label for="mail_from_name">Mail From Name</label>
                <input type="text" id="mail_from_name" name="mail_from_name"
                       value="<?= formVal('mail_from_name', 'Chatbot Assistant') ?>" required>
            </div>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-primary">🔌 Test Connection &amp; Write .env</button>
        </div>
        <?php
    }
}
