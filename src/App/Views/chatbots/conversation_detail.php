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
 * Conversation detail page — shows all messages for a session.
 *
 * Available variables:
 *   $chatbot     — chatbot record
 *   $conversation — conversation record
 *   $messages    — array of message records (ordered chronologically)
 *   $lead        — lead record or null
 *   $user        — authenticated user
 */
$pageTitle = 'Session — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

$avatarColor = function ($seed) {
    return match ($seed) {
        'user'      => '#f65c5c',
        'assistant' => '#3b82f6',
        default     => '#2563eb',
    };
};

ob_start(); ?>
<style>
    .conv-message { display: flex; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 8px; }
    .conv-message.user { background: var(--surface); border: 1px solid var(--table-border); }
    .conv-message.assistant { background: var(--surface); border: 1px solid var(--table-border); }
    .conv-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
    .conv-bubble { flex: 1; min-width: 0; }
    .conv-role { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
    .conv-role.user-role { color: #60a5fa; }
    .conv-role.assistant-role { color: #34d399; }
    .conv-role.system-role { color: #fbbf24; }
    .conv-text { font-size: 0.875rem; line-height: 1.6; word-break: break-word; }
    .conv-text p { margin-bottom: 6px; }
    .conv-text p:last-child { margin-bottom: 0; }
    .conv-text ul, .conv-text ol { padding-left: 1.25rem; margin-bottom: 6px; }
    .conv-text li { margin-bottom: 2px; }
    .conv-text pre { background: var(--bs-secondary-bg); border-radius: 6px; padding: 10px 12px; overflow-x: auto; margin-bottom: 8px; font-size: 0.8rem; }
    .conv-text code { font-size: 0.8rem; padding: 1px 4px; border-radius: 3px; background: var(--bs-secondary-bg); }
    .conv-text pre code { padding: 0; background: none; border-radius: 0; }
    .conv-text blockquote { border-left: 3px solid var(--bs-border-color); padding-left: 12px; margin-bottom: 8px; color: var(--bs-secondary-color); }
    .conv-text table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 0.8rem; }
    .conv-text table th, .conv-text table td { border: 1px solid var(--bs-border-color); padding: 4px 8px; text-align: left; }
    .conv-text table th { background: var(--bs-secondary-bg); font-weight: 600; }
    .conv-text img { max-width: 100%; border-radius: 6px; }
    .conv-text a { color: var(--bs-link-color); }
    .conv-meta { font-size: 0.7rem; color: var(--bs-secondary-color); margin-top: 4px; display: flex; gap: 12px; flex-wrap: wrap; }
    .conv-meta span { display: inline-flex; align-items: center; gap: 4px; }
    .message-separator { text-align: center; padding: 4px 0; color: var(--bs-secondary-color); font-size: 0.7rem; }
    .source-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 4px; background: rgba(128,128,128,0.12); color: var(--bs-secondary-color); }
    .lead-info-card { background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; }
    .lead-info-card .lead-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--bs-secondary-color); margin-bottom: 2px; }
    .lead-info-card .lead-value { font-size: 0.9rem; font-weight: 500; }
</style>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= (int) $chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations">Conversations</a></li>
            <li class="breadcrumb-item active" aria-current="page">Session</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1">Conversation Session</h1>
            <p class="text-muted mb-0">
                <code class="small"><?= htmlspecialchars($conversation['visitor_session_id']) ?></code>
                &middot; <?= (int) $conversation['message_count'] ?> message<?= (int) $conversation['message_count'] !== 1 ? 's' : '' ?>
                &middot; <?= dt($conversation['first_message_at'], 'M j, Y g:i A') ?>
                &ndash; <?= dt($conversation['last_message_at'], 'M j, Y g:i A') ?>
                <?php if ($conversation['rating'] !== null): ?>
                &middot; <span class="badge bg-info">Rating: <?= (int) $conversation['rating'] ?>/5</span>
                <?php else: ?>
                &middot; <span class="badge bg-info">Rating: &mdash;/5</span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations" class="btn btn-outline-secondary">&larr; Back to Sessions</a>
        </div>
    </div>

    <?php if ($lead): ?>
    <div class="lead-info-card">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="lead-label">Name</div>
                <div class="lead-value"><?= htmlspecialchars($lead['name']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="lead-label">Email</div>
                <div class="lead-value"><?= htmlspecialchars($lead['email']) ?></div>
            </div>
            <div class="col-md-2">
                <div class="lead-label">Phone</div>
                <div class="lead-value"><?= htmlspecialchars($lead['phone'] ?: '—') ?></div>
            </div>
            <div class="col-md-4">
                <div class="lead-label">Summary</div>
                <div class="lead-value small"><?= htmlspecialchars($lead['summary'] ?? 'No summary') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title h5 mb-0">Messages</h2>
            <span class="badge bg-secondary"><?= count($messages) ?> message<?= count($messages) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="card-body p-3">
            <?php if (empty($messages)): ?>
                <p class="text-muted text-center py-4 mb-0">No messages in this session.</p>
            <?php else: ?>
                <?php
                $lastDate = null;
                foreach ($messages as $msg):
                    $msgDate = dt($msg['created_at'], 'Y-m-d');
                    $initial = strtoupper($msg['role'][0]);
                    $color   = $avatarColor($msg['role']);
                ?>
                <?php if ($msgDate !== $lastDate): ?>
                    <?php $lastDate = $msgDate; ?>
                    <div class="message-separator"><?= dt($msg['created_at'], 'F j, Y') ?></div>
                <?php endif; ?>

                <div class="conv-message <?= htmlspecialchars($msg['role']) ?>">
                    <div class="conv-avatar" style="background: <?= $color ?>"><?= htmlspecialchars($initial) ?></div>
                    <div class="conv-bubble">
                        <div class="conv-role <?= htmlspecialchars($msg['role']) ?>-role">
                            <?= htmlspecialchars(ucfirst($msg['role'])) ?>

                            <?php if (!empty($msg['source'])): ?>
                                <span class="source-badge"><?= htmlspecialchars(str_replace('_', ' ', $msg['source'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="conv-text" data-md="<?= htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                        <div class="conv-meta">
                            <span><?= dt($msg['created_at'], 'g:i:s A') ?></span>
                            <?php if ($msg['tokens_used'] !== null): ?>
                                <span title="Tokens used">&#9881; <?= (int) $msg['tokens_used'] ?> tok</span>
                            <?php endif; ?>
                            <?php if ($msg['response_time_ms'] !== null): ?>
                                <span title="Response time">&#9201; <?= number_format((int) $msg['response_time_ms']) ?> ms</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();

$pageScripts = ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/marked@15.0.7/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.4/dist/purify.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.conv-text').forEach(function(el) {
        var raw = el.getAttribute('data-md');
        if (!raw) return;
        var ta = document.createElement('textarea');
        ta.innerHTML = raw;
        try {
            // DOMPurify strips <script>, on*, javascript:, etc.
            // while preserving safe markdown HTML (tables, code, lists, links)
            el.innerHTML = DOMPurify.sanitize(marked.parse(ta.value, { breaks: true, gfm: true }));
        } catch(e) { /* fallback: keep PHP-rendered escaped content */ }
    });
});
</script>
<?php $pageScripts = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
