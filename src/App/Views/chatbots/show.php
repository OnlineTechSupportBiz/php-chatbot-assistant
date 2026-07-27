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
<style>
    /* Test Chat — matches conversation session message styling */
    .conv-message { display: flex; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 8px; }
    .conv-message.user { background: var(--surface); border: 1px solid var(--border); }
    .conv-message.assistant { background: var(--surface); border: 1px solid var(--border); }
    .conv-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
    .conv-avatar.user { background: var(--avatar-user); }
    .conv-avatar.assistant { background: var(--avatar-assistant); }
    .conv-bubble { flex: 1; min-width: 0; }
    .conv-role { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
    .conv-role.user-role { color: var(--role-user); }
    .conv-role.assistant-role { color: var(--role-assistant); }
    .conv-text { font-size: 0.875rem; line-height: 1.6; word-break: break-word; }
    .conv-text p { margin-bottom: 6px; }
    .conv-text p:last-child { margin-bottom: 0; }
    .conv-text ul, .conv-text ol { padding-left: 1.25rem; margin-bottom: 6px; }
    .conv-text li { margin-bottom: 2px; }
    .conv-text pre { background: var(--surface-2); border-radius: 6px; padding: 10px 12px; overflow-x: auto; margin-bottom: 8px; font-size: 0.8rem; }
    .conv-text code { font-size: 0.8rem; padding: 1px 4px; border-radius: 3px; background: var(--surface-2); }
    .conv-text pre code { padding: 0; background: none; border-radius: 0; }
    .conv-text blockquote { border-left: 3px solid var(--border); padding-left: 12px; margin-bottom: 8px; color: var(--text-secondary); }
    .conv-text table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 0.8rem; }
    .conv-text table th, .conv-text table td { border: 1px solid var(--border); padding: 4px 8px; text-align: left; }
    .conv-text table th { background: var(--surface-2); font-weight: 600; }
    .conv-text a { color: var(--accent); }
    .conv-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display: flex; gap: 12px; flex-wrap: wrap; }
    .conv-meta span { display: inline-flex; align-items: center; gap: 4px; }
    .conv-typing { display: flex; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 8px; background: var(--surface); border: 1px solid var(--border); }
    .source-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 4px; background: var(--hover-bg); color: var(--text-secondary); }
    .sortable { cursor: pointer; user-select: none; }
    .sortable:hover { color: var(--accent); }
    .sort-indicator { opacity: 0.4; font-size: 0.75em; }
    th.sort-asc .sort-indicator::after { content: ' ▲'; opacity: 1; }
    th.sort-desc .sort-indicator::after { content: ' ▼'; opacity: 1; }
</style>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($chatbot['name']) ?></li>
        </ol>
    </nav>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (is_array($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1"><?= htmlspecialchars($chatbot['name']) ?></h1>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($chatbot['industry'] ?? 'General') ?>
                &middot; Created <?= dt($chatbot['created_at'], 'M j, Y') ?>
                &middot;
                <span class="badge bg-<?= $chatbot['status'] === 'active' ? 'success' : 'secondary' ?>">
                    <?= $chatbot['status'] ?>
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="/chatbots/<?= $chatbot['id'] ?>/edit" class="btn btn-outline-primary">Edit</a>
        </div>
    </div>

    <div class="row">
        <!-- Left column: details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title h5 mb-0">System Prompt</h2></div>
                <div class="card-body">
                    <pre class="mb-0 text-wrap" style="white-space: pre-wrap;"><?= htmlspecialchars($chatbot['system_prompt'] ?? '(none)') ?></pre>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title h5 mb-0">Model Configuration</h2></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Model</strong><br>
                            <span class="text-muted"><?= htmlspecialchars($modelCfg['model'] ?? 'gpt-4o-mini') ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Temperature</strong><br>
                            <span class="text-muted"><?= htmlspecialchars((string) ($modelCfg['temperature'] ?? 0.7)) ?></span>
                        </div>
                        <div class="col-md-4">
                            <strong>Max Tokens</strong><br>
                            <span class="text-muted"><?= htmlspecialchars((string) ($modelCfg['max_tokens'] ?? 1024)) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title h5 mb-0">Documents</h2>
                    <a href="/chatbots/<?= $chatbot['id'] ?>/documents" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($documents) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" id="docsTable">
                            <thead>
                                <tr>
                                    <th role="button" onclick="sortTable(0)" class="sortable">Name <span class="sort-indicator"></span></th>
                                    <th role="button" onclick="sortTable(1)" class="sortable">Type <span class="sort-indicator"></span></th>
                                    <th role="button" onclick="sortTable(2)" class="sortable">Status <span class="sort-indicator"></span></th>
                                    <th role="button" onclick="sortTable(3)" class="sortable">Uploaded <span class="sort-indicator"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['filename']) ?></td>
                                    <td><?= htmlspecialchars(formatMimeType($doc['mime_type'] ?? '')) ?></td>
                                    <td>
                                        <span class="badge bg-<?= match($doc['status']) {
                                            'indexed'  => 'success',
                                            'indexing' => 'info',
                                            'failed'   => 'danger',
                                            default    => 'secondary',
                                        } ?>"><?= htmlspecialchars($doc['status']) ?></span>
                                    </td>
                                    <td data-sort="<?= $doc['created_at'] ?>"><?= dt($doc['created_at'], 'M j, Y') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-file-earmark-text fs-2 d-block mb-2"></i>
                        No documents uploaded yet.
                        <a href="/chatbots/<?= $chatbot['id'] ?>/documents" class="d-block mt-2">Upload Documents</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column: embed + widget -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title h5 mb-0">Management</h2></div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="/chatbots/<?= $chatbot['id'] ?>/documents" class="btn btn-primary btn-sm">Manage Documents</a>
                    <a href="/chatbots/<?= $chatbot['id'] ?>/quick-answers" class="btn btn-primary btn-sm">Quick Answers</a>
                    <a href="/chatbots/<?= $chatbot['id'] ?>/conversations" class="btn btn-primary btn-sm">Conversations</a>
                    <a href="/chatbots/<?= $chatbot['id'] ?>/leads" class="btn btn-primary btn-sm">Leads</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title h5 mb-0">Stats</h2></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Documents</span>
                        <strong><?= count($documents) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Indexed</span>
                        <strong><?= $indexedCount ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Vector DB</span>
                        <strong><?= formatBytes($vectorStorage) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>File Storage</span>
                        <strong><?= formatBytes($totalFileSize) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title h5 mb-0">Test Chat</h2></div>
                <div class="card-body">
                    <div id="chatMessages" class="mb-3" style="max-height: 300px; overflow-y: auto;"></div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="chatInput" class="form-control"
                               placeholder="Type a message…" autocomplete="off">
                        <button class="btn btn-primary" onclick="testChat()">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked@15/marked.min.js"></script>
<script>
async function testChat() {
    const input  = document.getElementById('chatInput');
    const msg    = input.value.trim();
    const box    = document.getElementById('chatMessages');

    if (!msg) return;

    // Clear placeholder
    box.innerHTML = '';

    // Show user message (rendered via marked for consistency with session view)
    box.innerHTML += '<div class="conv-message user">'
        + '<div class="conv-avatar user" style="background:var(--avatar-user)">U</div>'
        + '<div class="conv-bubble">'
        + '<div class="conv-role user-role">User</div>'
        + '<div class="conv-text">' + marked.parse(htmlEsc(msg)) + '</div>'
        + '</div>'
        + '</div>';

    // Add loading indicator
    const loadingId = 'loading-' + Date.now();
    box.innerHTML += '<div id="' + loadingId + '" class="conv-typing">'
        + '<div class="conv-avatar assistant" style="background:var(--avatar-assistant)">A</div>'
        + '<div class="conv-bubble">'
        + '<div class="conv-role assistant-role">Assistant</div>'
        + '<div class="conv-text"><em>Thinking&hellip;</em></div>'
        + '</div>'
        + '</div>';
    box.scrollTop = box.scrollHeight;

    try {
        const resp = await fetch('/chatbots/<?= $chatbot['id'] ?>/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        });
        const data = await resp.json();
        // Bot replies are markdown — render with marked
        var source = data.source || '';
        var sourceBadge = source ? '<span class="source-badge">' + source.replace(/_/g, ' ') + '</span>' : '';
        document.getElementById(loadingId).outerHTML =
            '<div class="conv-message assistant">'
            + '<div class="conv-avatar assistant" style="background:var(--avatar-assistant)">A</div>'
            + '<div class="conv-bubble">'
            + '<div class="conv-role assistant-role">Assistant ' + sourceBadge + '</div>'
            + '<div class="conv-text">' + marked.parse(data.reply || data.error || '(empty)') + '</div>'
            + '</div>'
            + '</div>';
    } catch (e) {
        document.getElementById(loadingId).outerHTML =
            '<div class="conv-message assistant" style="border-left: 3px solid #ef4444;">'
            + '<div class="conv-avatar assistant" style="background:var(--avatar-assistant)">A</div>'
            + '<div class="conv-bubble">'
            + '<div class="conv-role assistant-role" style="color:#ef4444;">Error</div>'
            + '<div class="conv-text">Request failed: ' + htmlEsc(e.message) + '</div>'
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

    // Update sort indicators
    table.querySelectorAll('th.sortable').forEach((th, i) => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (i === col) {
            th.classList.add(dir ? 'sort-asc' : 'sort-desc');
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
