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

// Data attributes for the Chart.js canvas (kept out of inline JS strings).
$chartLabels = [];
$chartCounts = [];
foreach ($messageChart as $point) {
    $chartLabels[] = date('M j', strtotime($point['date']));
    $chartCounts[] = (int) $point['count'];
}
$chartLabelsJson = json_encode($chartLabels);
$chartCountsJson = json_encode($chartCounts);

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
    <div class="card-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <h2>Messages (last <?= (int) $chartRange ?> days)</h2>
        <div style="display:flex; gap:6px;">
            <?php foreach ([7, 30, 90] as $r): ?>
                <a class="btn btn-sm<?= $r === (int) $chartRange ? ' btn-active' : '' ?>"
                   href="/dashboard?range=<?= $r ?>"><?= $r ?>d</a>
            <?php endforeach; ?>
        </div>
    </div>
    <canvas id="messages-chart" height="110" data-labels="<?= htmlspecialchars($chartLabelsJson) ?>" data-counts="<?= htmlspecialchars($chartCountsJson) ?>" aria-label="Messages per day bar chart" role="img"></canvas>
</div>
<?php $pageScripts = ($pageScripts ?? '') . <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
(function () {
    var el = document.getElementById('messages-chart');
    if (!el || typeof Chart === 'undefined') return;
    var labels = JSON.parse(el.dataset.labels || '[]');
    var counts = JSON.parse(el.dataset.counts || '[]');
    new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Messages',
                data: counts,
                backgroundColor: 'rgba(13, 110, 253, 0.65)',
                hoverBackgroundColor: 'rgba(13, 110, 253, 0.9)',
                borderRadius: 4,
                maxBarThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
})();
</script>
HTML;
?>

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
