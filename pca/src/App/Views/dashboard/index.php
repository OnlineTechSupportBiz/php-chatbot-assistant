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
 * Dashboard index view (user account landing page after login).
 * Requires $user from Auth::requireAuth(), plus the server-side stats the
 * controller computes ($totalMessages, $totalConversations, $uniqueVisitors,
 * $totalTokens, $chatbots, $messageChart, $sourceBreakdown, $chatbotStats).
 */
$pageTitle = 'Dashboard - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

// Normalise the 7-day message chart for the bar heights (max = tallest bar).
$chartMax = 0;
foreach ($messageChart as $point) {
    $chartMax = max($chartMax, (int) $point['count']);
}

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">Activity across your chatbots.</p>
    </div>
</div>

<?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat">
        <div class="stat-label">Chatbots</div>
        <div class="stat-value"><?= count($chatbots) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Conversations</div>
        <div class="stat-value"><?= (int) $totalConversations ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Messages</div>
        <div class="stat-value"><?= (int) $totalMessages ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Visitors</div>
        <div class="stat-value"><?= (int) $uniqueVisitors ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Tokens used</div>
        <div class="stat-value"><?= number_format((int) $totalTokens) ?></div>
    </div>
</div>

<div class="card">
    <h2>Messages (last 7 days)</h2>
    <?php if (empty($messageChart)): ?>
        <p class="muted">No messages in the last 7 days.</p>
    <?php else: ?>
        <div class="bar-chart">
            <?php foreach ($messageChart as $point): ?>
                <?php
                    $count = (int) $point['count'];
                    $height = $chartMax > 0 ? (int) round($count / $chartMax * 120) : 0;
                ?>
                <div class="bar-col">
                    <span class="muted bar-count"><?= $count ?></span>
                    <div class="bar" style="height: <?= $height ?>px; min-height: <?= $count > 0 ? 4 : 0 ?>px;"></div>
                    <span class="muted bar-label"><?= htmlspecialchars(date('M j', strtotime($point['date']))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card card-flush">
    <div class="card-head"><h2>Answers by source</h2></div>
    <?php if (empty($sourceBreakdown)): ?>
        <p class="card-note muted">No assistant messages yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Messages</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sourceBreakdown as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars(str_replace('_', ' ', $row['source'] ?? 'unknown')) ?></td>
                        <td><?= (int) $row['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card card-flush">
    <div class="card-head"><h2>Conversations per chatbot</h2></div>
    <?php if (empty($chatbotStats)): ?>
        <p class="card-note muted">No conversations yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Chatbot</th>
                    <th>Conversations</th>
                    <th>Tokens today</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatbotStats as $bot): ?>
                    <tr>
                        <td><?= htmlspecialchars($bot['chatbot_name'] ?? 'Unknown') ?></td>
                        <td><?= (int) $bot['count'] ?></td>
                        <td><?= number_format((int) ($bot['tokens_today'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php $pageContent = ob_get_clean();

require __DIR__ . '/layout.php';
