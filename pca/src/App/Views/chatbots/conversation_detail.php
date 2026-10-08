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
 * Port of the js-chatbot-assistant chatbots/[id]/conversations/[cid] page +
 * components/admin/conversation-transcript.tsx. The transcript uses the shared
 * `.msg*` classes from the design system (theme.css); the old inline
 * per-message style block is gone.
 *
 * Available variables:
 *   $chatbot      — chatbot record
 *   $conversation — conversation record
 *   $messages     — array of message records (ordered chronologically)
 *   $lead         — lead record or null
 *   $user         — authenticated user
 */
$sessionId = trim((string) ($conversation['visitor_session_id'] ?? ''));
$pageTitle = ($sessionId !== '' ? $sessionId : 'Conversation') . ' — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-head">
    <div>
        <h1><?= $sessionId !== '' ? htmlspecialchars($sessionId) : 'Conversation' ?></h1>
        <p class="subtitle">
            <?= (int) $conversation['message_count'] ?> message<?= (int) $conversation['message_count'] !== 1 ? 's' : '' ?>
            &middot; <?= dt($conversation['first_message_at'] ?? '') ?> &ndash; <?= dt($conversation['last_message_at'] ?? '') ?>
            &middot; <?= $conversation['rating'] !== null ? 'Rating: ' . (int) $conversation['rating'] . '/5' : 'Rating: -/5' ?>
        </p>
    </div>
    <a href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations" class="btn">Back to Conversations</a>
</div>

<?php if ($lead): ?>
    <div class="lead-card">
        <div class="form-row">
            <div>
                <div class="lead-label">Name</div>
                <div class="lead-value"><?= htmlspecialchars($lead['name'] ?: '—') ?></div>
            </div>
            <div>
                <div class="lead-label">Email</div>
                <div class="lead-value">
                    <?php if (!empty($lead['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($lead['email']) ?>"><?= htmlspecialchars($lead['email']) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="lead-label">Phone</div>
                <div class="lead-value"><?= htmlspecialchars($lead['phone'] ?: '—') ?></div>
            </div>
            <div>
                <div class="lead-label">Summary</div>
                <div class="lead-value"><?= htmlspecialchars($lead['summary'] ?? 'No summary') ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>Messages</h2>
        <span class="badge"><?= count($messages) ?> message<?= count($messages) !== 1 ? 's' : '' ?></span>
    </div>
    <?php if (empty($messages)): ?>
        <p class="muted">No messages in this session.</p>
    <?php else: ?>
        <?php
        $lastDay = null;
        foreach ($messages as $msg):
            $role = strtolower((string) ($msg['role'] ?? ''));
            $cls = in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'system';
            $day = dt($msg['created_at'], 'Y-m-d');
            $initial = strtoupper($role !== '' ? $role[0] : '?');
        ?>
        <?php if ($day !== $lastDay): ?>
            <?php $lastDay = $day; ?>
            <div class="msg-separator"><?= dt($msg['created_at'], 'F j, Y') ?></div>
        <?php endif; ?>

        <div class="msg msg-<?= $cls ?>">
            <div class="msg-avatar msg-avatar-<?= $cls ?>"><?= htmlspecialchars($initial) ?></div>
            <div class="msg-bubble">
                <div class="msg-meta msg-meta-<?= $cls ?>">
                    <?= htmlspecialchars(ucfirst($role)) ?>
                    <?php if (!empty($msg['source'])): ?>
                        <span class="msg-source"><?= htmlspecialchars(str_replace('_', ' ', $msg['source'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="msg-text" data-md="<?= htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8') ?>"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                <div class="msg-detail">
                    <span><?= dt($msg['created_at'], 'g:i:s A') ?></span>
                    <?php if ($msg['tokens_used'] !== null): ?>
                        <span title="Tokens used"><?= (int) $msg['tokens_used'] ?> tok</span>
                    <?php endif; ?>
                    <?php if ($msg['response_time_ms'] !== null): ?>
                        <span title="Response time"><?= number_format((int) $msg['response_time_ms']) ?> ms</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

$pageScripts = ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/marked@15.0.7/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.2.4/dist/purify.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.msg-text').forEach(function(el) {
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
