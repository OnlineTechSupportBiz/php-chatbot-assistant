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
 * Conversation sessions list for a chatbot.
 *
 * Available variables:
 *   $chatbot       — chatbot record
 *   $conversations — array of conversation records (with last_message_content, last_message_role)
 *   $user          — authenticated user
 */
$pageTitle = 'Conversations — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= (int) $chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Conversations</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1">Conversation Sessions</h1>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($chatbot['name']) ?>
                &middot; <?= count($conversations) ?> session<?= count($conversations) !== 1 ? 's' : '' ?>
            </p>
        </div>
        <div>
            <a href="/chatbots/<?= (int) $chatbot['id'] ?>" class="btn btn-outline-secondary">&larr; Back to Chatbot</a>
        </div>
    </div>

    <?php if (empty($conversations)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-0">No conversations yet. Once visitors start chatting with this chatbot, their sessions will appear here.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Session ID</th>
                                <th>Messages</th>
                                <th>First Message</th>
                                <th>Last Message</th>
                                <th>Last Message Preview</th>
                                <th>Rating</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversations as $i => $conv): ?>
                            <tr>
                                <td><?= count($conversations) - $i ?></td>
                                <td>
                                    <code class="small"><?= htmlspecialchars(substr($conv['visitor_session_id'], 0, 16)) ?>&hellip;</code>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= (int) $conv['message_count'] ?></span>
                                </td>
                                <td class="small">
                                    <?= dt($conv['first_message_at'], 'M j, Y g:i A') ?>
                                </td>
                                <td class="small">
                                    <?= dt($conv['last_message_at'], 'M j, Y g:i A') ?>
                                </td>
                                <td class="small text-muted" style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php if ($conv['last_message_content']): ?>
                                        <?php if ($conv['last_message_role'] === 'user'): ?>
                                            <span class="text-info">Q:</span>
                                        <?php else: ?>
                                            <span class="text-success">A:</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars(mb_substr($conv['last_message_content'], 0, 80)) ?>
                                        <?= mb_strlen($conv['last_message_content']) > 80 ? '&hellip;' : '' ?>
                                    <?php else: ?>
                                        <span class="fst-italic">(no messages)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($conv['rating'] !== null): ?>
                                        <span class="badge bg-info"><?= (int) $conv['rating'] ?>/5</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">&mdash;/5</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations/<?= (int) $conv['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <p class="text-muted small mb-0">
                <i class="bi bi-info-circle"></i>
                Showing the most recent <?= count($conversations) ?> sessions. Click "View" to read the full conversation.
            </p>
        </div>
    <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
