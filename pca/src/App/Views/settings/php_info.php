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

/**
 * PHP Info view — displays important PHP configuration for the app.
 *
 * @var array $user Authenticated user
 * @var array $config PHP settings relevant to this application
 */
$pageTitle = 'PHP Info - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <h1 class="mb-4">PHP Info</h1>

    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php
    /**
     * Render a PHP config value as a label + value row.
     */
    $renderRow = function (string $label, string $value, ?string $note = null): void {
        $badge = '';
        if ($note === 'danger') {
            $badge = ' <span class="badge bg-danger">Warning</span>';
        } elseif ($note === 'warning') {
            $badge = ' <span class="badge bg-warning text-dark">Check</span>';
        } elseif ($note === 'info') {
            $badge = ' <span class="badge bg-info text-dark">Info</span>';
        } elseif ($note === 'success') {
            $badge = ' <span class="badge bg-success">OK</span>';
        } elseif ($note !== null) {
            // Custom note appended after the value
        }
        echo '<div class="row border-bottom py-2">';
        echo '<dt class="col-sm-4 text-muted">' . htmlspecialchars($label) . '</dt>';
        echo '<dd class="col-sm-8 mb-0"><code>' . htmlspecialchars($value) . '</code>' . $badge;
        if ($note !== null && !in_array($note, ['danger', 'warning', 'info', 'success'])) {
            echo '<br><small class="text-muted">' . htmlspecialchars($note) . '</small>';
        }
        echo '</dd></div>';
    };
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">PHP Version & Environment</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php $renderRow('PHP Version', $config['php_version']); ?>
                <?php $renderRow('PHP SAPI', $config['sapi']); ?>
                <?php $renderRow('Architecture', $config['architecture']); ?>
                <?php $renderRow('Configuration File (php.ini)', $config['php_ini_path']); ?>
                <?php $renderRow('Server Platform', $config['server_platform']); ?>
                <?php $renderRow('Server Software', $config['server_software']); ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Critical Settings</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php
                $uploadNote = $config['upload_max_filesize'] !== 'unknown' ? 'Maximum file size for document uploads.' : null;
                $renderRow('upload_max_filesize', $config['upload_max_filesize'], $uploadNote);

                $postNote = $config['post_max_size'] !== 'unknown' ? 'Maximum POST data size. Should be >= upload_max_filesize.' : null;
                $postBadge = $config['post_max_size_note'] ?? null;
                $renderRow('post_max_size', $config['post_max_size'], $postBadge);

                $memBadge = 'info';
                $memVal = $config['memory_limit'] ?? 'unknown';
                $memNote = $memVal !== 'unknown' && $memVal !== '-1' ? 'Minimum 128M recommended for document processing.' : null;
                if ($memVal === '-1') {
                    $memNote = 'Unlimited — no memory ceiling.';
                }
                $renderRow('memory_limit', $memVal, $memNote);

                $execNote = $config['max_execution_time'] !== 'unknown'
                    ? 'Maximum seconds a script can run. 300+ recommended for training documents.'
                    : null;
                if ((int) $config['max_execution_time'] > 0 && (int) $config['max_execution_time'] < 300) {
                    $execNote = ($execNote ?? '') . ' Consider increasing for document training.';
                }
                $renderRow('max_execution_time', $config['max_execution_time'] . 's', $execNote);

                $renderRow('max_input_time', $config['max_input_time'] . 's', 'Maximum seconds to parse input data.');
                $renderRow('max_input_vars', $config['max_input_vars'], 'Maximum number of input variables per request.');
                ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Session Settings</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php $renderRow('Session Save Path', $config['session_save_path'], 'Where session files are stored on disk.'); ?>
                <?php $renderRow('Session GC Max Lifetime', $config['session_gc_maxlifetime'] . 's', 'Session file cleanup threshold (seconds).'); ?>
                <?php $renderRow('Session Cookie Name', $config['session_cookie_name']); ?>
                <?php $renderRow('Session Cookie Lifetime', $config['session_cookie_lifetime'] . 's'); ?>
                <?php $renderRow('Session Cookie SameSite', $config['session_cookie_samesite']); ?>
                <?php $renderRow('Session Cookie Secure', $config['session_cookie_secure']); ?>
                <?php $renderRow('Session Cookie HTTPOnly', $config['session_cookie_httponly']); ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Upload & File Settings</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php $renderRow('file_uploads', $config['file_uploads'], 'Whether file uploads are enabled.'); ?>
                <?php $renderRow('allow_url_fopen', $config['allow_url_fopen'], 'Required for external API calls.'); ?>
                <?php $renderRow('allow_url_include', $config['allow_url_include']); ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Error Reporting</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php
                $displayBadge = $config['display_errors'] === '1' ? 'danger' : 'success';
                $displayNote = $config['display_errors'] === '1'
                    ? 'Errors shown to users. Disable in production.'
                    : 'Errors hidden from users.';
                $renderRow('display_errors', $config['display_errors'], $displayBadge);

                $displayStartupBadge = $config['display_startup_errors'] === '1' && $config['display_errors'] === '1' ? 'warning' : 'success';
                $renderRow('display_startup_errors', $config['display_startup_errors'], $displayStartupBadge);
                $renderRow('error_reporting', $config['error_reporting'], 'Current error reporting level.');
                $renderRow('log_errors', $config['log_errors'], 'Whether errors are logged to file.');
                $renderRow('error_log', $config['error_log'], 'Path to error log file.');
                ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Database & Extensions</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php
                $pdoDrivers = !empty($config['pdo_drivers']) ? implode(', ', $config['pdo_drivers']) : 'none';
                $pdoBadge = in_array('mysql', $config['pdo_drivers'] ?? []) ? 'success' : 'danger';
                $renderRow('PDO Drivers', $pdoDrivers, $pdoBadge);

                $extList = $config['loaded_extensions'] ?? [];
                foreach (['curl', 'json', 'mbstring', 'openssl', 'gd', 'xml', 'zip', 'fileinfo', 'intl'] as $ext) {
                    $present = in_array($ext, $extList, true);
                    $badge = $present ? 'success' : ($ext === 'gd' ? 'info' : 'danger');
                    $note = $present ? 'Loaded' : ($ext === 'gd' ? 'Optional — used for image processing if available' : 'Required — NOT loaded');
                    $renderRow($ext, $present ? '✓ loaded' : '✗ not loaded', $badge);
                }
                ?>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Timezone & Date</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <?php $renderRow('date.timezone', $config['date_timezone'], 'PHP timezone setting.'); ?>
                <?php $renderRow('default_charset', $config['default_charset']); ?>
            </dl>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
