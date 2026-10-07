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
 * Captured leads view for a chatbot (the Leads tab).
 *
 * Mirrors the js-chatbot-assistant per-chatbot leads tab: Name / Email / Phone /
 * Captured, with no row number and no conversation summary column.
 *
 * Available variables:
 *   $chatbot — chatbot record (with lead_capture_enabled)
 *   $leads   — array of lead records
 *   $user    — authenticated user
 */
$pageTitle = 'Leads — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Leads</h1>
        <p class="subtitle">Contact details this chatbot has captured.</p>
    </div>
</div>

<div class="tab-bar">
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>">Settings</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/documents">Documents</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers">Quick answers</a>
    <a class="tab tab-active" href="/chatbots/<?= (int) $chatbot['id'] ?>/leads" aria-current="page">Leads</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations">Conversations</a>
</div>

<?php if (empty($leads)): ?>
    <div class="empty">
        No leads captured yet.
        <?php if (empty($chatbot['lead_capture_enabled'])): ?>
            <a href="/chatbots/<?= (int) $chatbot['id'] ?>">Enable lead capture</a> to start collecting visitor information.
        <?php else: ?>
            Leads will appear here once the chatbot collects a visitor's name, email, or phone.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Captured</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?= htmlspecialchars($lead['name'] ?: '—') ?></td>
                        <td>
                            <?php if (!empty($lead['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($lead['email']) ?>"><?= htmlspecialchars($lead['email']) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($lead['phone'] ?: '—') ?></td>
                        <td class="muted"><?= dt($lead['captured_at'] ?? $lead['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
