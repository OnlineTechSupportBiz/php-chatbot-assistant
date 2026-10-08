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
 * Global leads list — contact details captured across every chatbot.
 *
 * Port of the js-chatbot-assistant `/leads` page. Leads come from
 * Lead::findByAdmin(); the chatbot name is resolved from the $botNames map.
 *
 * Available variables:
 *   $leads    — array of lead records (chatbot_id, name, email, phone, captured_at)
 *   $botNames — map of chatbot_id => chatbot name
 *   $user     — authenticated user
 */
$pageTitle = 'Leads - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Leads</h1>
        <p class="subtitle">Contact details your chatbots have captured.</p>
    </div>
</div>

<?php if (empty($leads)): ?>
    <div class="empty">No leads captured yet.</div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Chatbot</th>
                    <th>Captured</th>
                    <th>Conversation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?= htmlspecialchars($lead['name'] ?: '-') ?></td>
                        <td>
                            <?php if (!empty($lead['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($lead['email']) ?>"><?= htmlspecialchars($lead['email']) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($lead['phone'] ?: '-') ?></td>
                        <td>
                            <a href="/chatbots/<?= (int) $lead['chatbot_id'] ?>"><?= htmlspecialchars($botNames[(int) $lead['chatbot_id']] ?? '-') ?></a>
                        </td>
                        <td class="muted"><?= dt($lead['captured_at'] ?? $lead['created_at'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($lead['conversation_id'])): ?>
                                <a href="/chatbots/<?= (int) $lead['chatbot_id'] ?>/conversations/<?= (int) $lead['conversation_id'] ?>">View conversation</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/dashboard/layout.php';
