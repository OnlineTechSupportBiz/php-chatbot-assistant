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
 * Conversation sessions list for a chatbot (the Conversations tab).
 *
 * Mirrors the js-chatbot-assistant global `/conversations` page, minus the
 * Chatbot column (this page is already scoped to one chatbot).
 *
 * Available variables:
 *   $chatbot       — chatbot record
 *   $conversations — array of conversation records (with visitor_ip, rating)
 *   $user          — authenticated user
 */
$pageTitle = 'Conversations — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Conversations</h1>
        <p class="subtitle">Recent visitor conversations.</p>
    </div>
</div>

<div class="tab-bar">
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>">Settings</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/documents">Documents</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers">Quick answers</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/leads">Leads</a>
    <a class="tab tab-active" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations" aria-current="page">Conversations</a>
</div>

<?php if (empty($conversations)): ?>
    <div class="empty">No conversations yet. Once visitors start chatting with this chatbot, their sessions will appear here.</div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Visitor IP</th>
                    <th>Messages</th>
                    <th>Rating</th>
                    <th>Last message</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars($conv['visitor_ip'] ?? '—') ?></td>
                        <td><?= (int) $conv['message_count'] ?></td>
                        <td><?= $conv['rating'] !== null ? (int) $conv['rating'] : '—' ?></td>
                        <td class="muted"><?= dt($conv['last_message_at'] ?? '') ?></td>
                        <td style="text-align: right;">
                            <a class="btn btn-sm" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations/<?= (int) $conv['id'] ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
