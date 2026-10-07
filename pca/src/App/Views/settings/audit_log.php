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
 * Audit log history view.
 *
 * Simplified to match the js-chatbot-assistant `/audit` page: no filters,
 * search or pagination — just Time / Action / Entity / IP and an entry total.
 *
 * @var array  $result    ['rows' => [...], 'total' => N, 'page' => N]
 * @var array  $user      Authenticated user
 * @var array  $userNames Maps user_id => name (unused by this simplified table)
 * @var array  $filters   Kept for controller compatibility; not rendered.
 */
$pageTitle = 'Audit log - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

$result ??= ['rows' => [], 'total' => 0, 'page' => 1];

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Audit log</h1>
        <p class="subtitle">A record of account and admin actions.</p>
    </div>
</div>

<?php if (empty($result['rows'])): ?>
    <div class="empty">No audit entries yet.</div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td class="muted"><?= dt($row['created_at']) ?></td>
                        <td><span class="mono"><?= htmlspecialchars($row['action'] ?? '') ?></span></td>
                        <td class="muted">
                            <?= htmlspecialchars($row['entity_type'] ?? '-') ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?>
                        </td>
                        <td class="mono"><?= htmlspecialchars($row['ip_address'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="muted" style="padding: 0.75rem 0.9rem; font-size: 0.82rem;"><?= (int) $result['total'] ?> entries total</div>
    </div>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
