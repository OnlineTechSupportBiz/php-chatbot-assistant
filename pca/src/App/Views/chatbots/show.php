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
 * Chatbot detail page view.
 *
 * Available variables:
 *   $chatbot      — chatbot array (with decoded model_config, styling)
 *   $documents    — array of documents for this chatbot
 *   $indexedCount — number of indexed documents
 *   $user         — authenticated user
 */
\App\Auth\Session::start();
$errors  = \App\Auth\Session::getFlash('errors');
$success = \App\Auth\Session::getFlash('success');

$modelCfg  = $chatbot['model_config'] ?? [];
$styling   = $chatbot['styling'] ?? [];

$pageTitle = htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

/**
 * Format a MIME type into a short display label.
 */
function formatMimeType(string $mime): string
{
    if ($mime === '') {
        return '—';
    }
    $map = [
        'text/plain'        => 'TXT',
        'text/markdown'     => 'MD',
        'text/csv'          => 'CSV',
        'text/html'         => 'HTML',
        'application/pdf'   => 'PDF',
        'application/json'  => 'JSON',
        'application/xml'   => 'XML',
        'application/msword' => 'DOC',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
    ];
    return $map[$mime] ?? strtoupper(pathinfo(parse_url($mime, PHP_URL_PATH) ?: $mime, PATHINFO_EXTENSION)) ?: $mime;
}

/**
 * Format bytes into a human-readable size string.
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
}

ob_start(); ?>
<div class="page-head">
    <div>
        <h1><?= htmlspecialchars($chatbot['name']) ?></h1>
        <p class="subtitle">
            Created <?= dt($chatbot['created_at'], 'M j, Y') ?>
            &middot;
            <?php
                $botStatus = $chatbot['status'] ?? 'active';
                $statusClass = $botStatus === 'active' ? 'badge-success' : ($botStatus === 'paused' ? 'badge-warning' : '');
            ?>
            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($botStatus) ?></span>
            &middot;
            Widget token: <span class="mono"><?= htmlspecialchars($chatbot['widget_token'] ?? '') ?></span>
        </p>
    </div>
    <a href="/chatbots/<?= (int) $chatbot['id'] ?>/edit" class="btn btn-primary">Edit</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (is_array($errors)): ?>
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:1.25rem;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tab-bar">
    <a class="tab tab-active" href="/chatbots/<?= (int) $chatbot['id'] ?>" aria-current="page">Overview</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/documents">Documents</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers">Quick answers</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/leads">Leads</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations">Conversations</a>
</div>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Documents</div>
        <div class="stat-value"><?= count($documents) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Indexed</div>
        <div class="stat-value"><?= (int) $indexedCount ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Vector DB</div>
        <div class="stat-value"><?= htmlspecialchars(formatBytes($vectorStorage)) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">File Storage</div>
        <div class="stat-value"><?= htmlspecialchars(formatBytes($totalFileSize)) ?></div>
    </div>
</div>

<div class="card">
    <h2>System Prompt</h2>
    <pre class="code-block" style="white-space: pre-wrap;"><?= htmlspecialchars($chatbot['system_prompt'] ?? '(none)') ?></pre>
</div>

<div class="card">
    <h2>Model Configuration</h2>
    <div class="form-row">
        <div class="field">
            <div class="label">Model</div>
            <div class="muted"><?= htmlspecialchars($modelCfg['model'] ?? 'gpt-4.1-mini') ?></div>
        </div>
        <div class="field">
            <div class="label">Temperature</div>
            <div class="muted"><?= htmlspecialchars((string) ($modelCfg['temperature'] ?? 0.0)) ?></div>
        </div>
        <div class="field">
            <div class="label">Max Tokens</div>
            <div class="muted"><?= htmlspecialchars((string) ($modelCfg['max_tokens'] ?? 1024)) ?></div>
        </div>
    </div>
</div>

<div class="card card-flush">
    <div class="card-head">
        <h2>Documents</h2>
        <a href="/chatbots/<?= (int) $chatbot['id'] ?>/documents" class="btn btn-sm">Manage</a>
    </div>
    <?php if (count($documents) > 0): ?>
        <table class="table" id="docsTable">
            <thead>
                <tr>
                    <th style="cursor:pointer;user-select:none;" onclick="sortTable(0)">Name</th>
                    <th style="cursor:pointer;user-select:none;" onclick="sortTable(1)">Type</th>
                    <th style="cursor:pointer;user-select:none;" onclick="sortTable(2)">Status</th>
                    <th style="cursor:pointer;user-select:none;" onclick="sortTable(3)">Uploaded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <?php
                        $docClass = match ($doc['status']) {
                            'indexed'  => 'badge-success',
                            'failed'   => 'badge-danger',
                            default    => '',
                        };
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['filename']) ?></td>
                        <td><?= htmlspecialchars(formatMimeType($doc['mime_type'] ?? '')) ?></td>
                        <td><span class="badge <?= $docClass ?>"><?= htmlspecialchars($doc['status']) ?></span></td>
                        <td data-sort="<?= $doc['created_at'] ?>"><?= dt($doc['created_at'], 'M j, Y') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="card-note muted" style="padding-top:0.5rem;">
            No documents uploaded yet.
            <a href="/chatbots/<?= (int) $chatbot['id'] ?>/documents">Upload documents</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Test Chat</h2>
    <div id="chatMessages" style="max-height: 300px; overflow-y: auto; margin-bottom: 0.75rem;"></div>
    <div class="row">
        <input type="text" id="chatInput" class="input" style="flex:1;" placeholder="Type a message…" autocomplete="off">
        <button type="button" class="btn btn-primary" onclick="testChat()">Send</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked@15/marked.min.js"></script>
<script>
function msgShell(role, body) {
    return '<div class="msg msg-' + role + '">'
        + '<div class="msg-avatar msg-avatar-' + role + '">' + (role === 'user' ? 'U' : 'A') + '</div>'
        + '<div class="msg-bubble">' + body + '</div>'
        + '</div>';
}

async function testChat() {
    const input  = document.getElementById('chatInput');
    const msg    = input.value.trim();
    const box    = document.getElementById('chatMessages');

    if (!msg) return;

    // Clear placeholder
    box.innerHTML = '';

    // Show user message (rendered via marked for consistency with session view)
    box.innerHTML += msgShell('user',
        '<div class="msg-meta msg-meta-user">User</div>'
        + '<div class="msg-text">' + marked.parse(htmlEsc(msg)) + '</div>');

    // Add loading indicator
    const loadingId = 'loading-' + Date.now();
    box.innerHTML += '<div id="' + loadingId + '" class="msg msg-assistant">'
        + '<div class="msg-avatar msg-avatar-assistant">A</div>'
        + '<div class="msg-bubble">'
        + '<div class="msg-meta msg-meta-assistant">Assistant</div>'
        + '<div class="msg-text"><em>Thinking&hellip;</em></div>'
        + '</div>'
        + '</div>';
    box.scrollTop = box.scrollHeight;

    try {
        const resp = await fetch('/chatbots/<?= (int) $chatbot['id'] ?>/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        });
        const data = await resp.json();
        // Bot replies are markdown — render with marked
        var source = data.source || '';
        var sourceBadge = source ? '<span class="msg-source">' + source.replace(/_/g, ' ') + '</span>' : '';
        document.getElementById(loadingId).outerHTML =
            '<div class="msg msg-assistant">'
            + '<div class="msg-avatar msg-avatar-assistant">A</div>'
            + '<div class="msg-bubble">'
            + '<div class="msg-meta msg-meta-assistant">Assistant ' + sourceBadge + '</div>'
            + '<div class="msg-text">' + marked.parse(data.reply || data.error || '(empty)') + '</div>'
            + '</div>'
            + '</div>';
    } catch (e) {
        document.getElementById(loadingId).outerHTML =
            '<div class="msg msg-assistant" style="border-left: 3px solid #ef4444;">'
            + '<div class="msg-avatar msg-avatar-assistant">A</div>'
            + '<div class="msg-bubble">'
            + '<div class="msg-meta msg-meta-assistant" style="color:#ef4444;">Error</div>'
            + '<div class="msg-text">Request failed: ' + htmlEsc(e.message) + '</div>'
            + '</div>'
            + '</div>';
    }

    input.value = '';
    box.scrollTop = box.scrollHeight;
}

function htmlEsc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Send on Enter
document.getElementById('chatInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') testChat();
});

/**
 * Client-side table sorting.
 */
let sortDir = [false, false, false, false];
function sortTable(col) {
    const table = document.getElementById('docsTable');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));
    const dir   = sortDir[col] = !sortDir[col];

    rows.sort((a, b) => {
        const aVal = a.children[col].getAttribute('data-sort') || a.children[col].textContent.trim();
        const bVal = b.children[col].getAttribute('data-sort') || b.children[col].textContent.trim();
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return dir ? aNum - bNum : bNum - aNum;
        }
        return dir ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });

    rows.forEach(r => tbody.appendChild(r));
}
</script>
<?php
$pageContent = ob_get_clean();

require __DIR__ . '/../dashboard/layout.php';
