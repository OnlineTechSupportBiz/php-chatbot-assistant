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
 * Requires $user from Auth::requireAuth().
 */
$pageTitle = 'Dashboard - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-container">
    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Overview of your chatbot activity</p>
    </div>

    <!-- Row 1: Primary metrics (4 stat cards) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-chat-circle-text"></i></div>
                <div class="card-title">Chatbots</div>
                <div class="display-6" id="bot-count">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-file-text"></i></div>
                <div class="card-title">Documents</div>
                <div class="display-6" id="doc-count">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-chats-circle"></i></div>
                <div class="card-title">Conversations</div>
                <div class="display-6" id="conv-count">--</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph ph-speech-bubble"></i></div>
                <div class="card-title">Messages</div>
                <div class="display-6" id="msg-count">--</div>
            </div>
        </div>
    </div>

    <!-- Row 2: Secondary metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="stat-secondary">
                        <div class="stat-label">Unique Visitors</div>
                        <div class="stat-value" id="visitor-count">--</div>
                        <div class="stat-note">Distinct people who have interacted with your chatbots</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="stat-secondary">
                        <div class="stat-label">Tokens Used</div>
                        <div class="stat-value" id="token-count">--</div>
                        <div class="stat-note">Rough estimate of AI text processed (1 token ~ 4 characters)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="stat-secondary">
                        <div class="stat-label">Avg Response Time</div>
                        <div class="stat-value" id="avg-time">--</div>
                        <div class="stat-note">Average time for the AI to respond to a user message</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Line chart + Source breakdown -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header flex-column align-items-start gap-2">
                    <span>Messages (This month)</span>
                    <div class="d-flex align-items-center gap-2 flex-wrap" id="date-range-controls">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-range="7d">7 Days</button>
                            <button type="button" class="btn btn-outline-secondary" data-range="30d">30 Days</button>
                            <button type="button" class="btn btn-outline-secondary" data-range="month">This Month</button>
                        </div>
                        <input type="date" id="date-from" class="form-control form-control-sm date-range-input">
                        <input type="date" id="date-to" class="form-control form-control-sm date-range-input">
                        <button type="button" class="btn btn-sm btn-primary" id="apply-range">Apply</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" id="message-chart" style="min-height:200px;width:100%;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <span>Response Sources</span>
                </div>
                <div class="card-body" id="source-breakdown">
                    <p class="text-muted mb-0">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Quick actions + Per-chatbot -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <span>Quick Actions</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <a href="/chatbots" class="quick-action">
                            <span class="quick-icon"><i class="ph ph-chat-circle-text"></i></span>
                            <span class="quick-label">Manage Chatbots</span>
                        </a>
                        <a href="/chatbots/create" class="quick-action">
                            <span class="quick-icon"><i class="ph ph-plus-circle"></i></span>
                            <span class="quick-label">Create Chatbot</span>
                        </a>
                        <a href="/settings" class="quick-action">
                            <span class="quick-icon"><i class="ph ph-gear"></i></span>
                            <span class="quick-label">Settings</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <span>Conversations per Chatbot</span>
                </div>
                <div class="card-body pt-0">
                    <small class="text-muted d-block mb-2">Lifetime conversation count per chatbot. Bar color reflects today's token budget usage when set.</small>
                </div>
                <div class="card-body" id="bot-chart-container">
                    <p class="text-muted mb-0">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $pageContent = ob_get_clean();

$pageScripts = <<<'HEREDOC'
<script>
    fetch('/api/stats/summary')
        .then(r => r.json())
        .then(data => {
            document.getElementById('bot-count').textContent = data.chatbots ?? '--';
            document.getElementById('doc-count').textContent = data.documents ?? '--';
            document.getElementById('conv-count').textContent = data.conversations ?? '--';
            document.getElementById('msg-count').textContent = data.messages ?? '--';
            document.getElementById('visitor-count').textContent = data.unique_visitors ?? '--';
            document.getElementById('token-count').textContent = (data.tokens_used ?? 0).toLocaleString();
            document.getElementById('avg-time').innerHTML = data.avg_response_time ? Math.round(data.avg_response_time).toLocaleString() + ' <small style="font-size:0.75rem;color:var(--text-muted);font-weight:400;">ms</small>' : '--';

            // Line chart
            renderLineChart(data.message_chart || []);

            // Source breakdown
            var srcEl = document.getElementById('source-breakdown');
            var sources = data.sources || [];
            if (sources.length === 0) {
                srcEl.innerHTML = '<p class="text-muted mb-0">No assistant responses yet.</p>';
            } else {
                var totalSrc = sources.reduce(function(s, r) { return s + r.count; }, 0);
                var html = '';
                sources.forEach(function(r) {
                    var pct = totalSrc > 0 ? (r.count / totalSrc * 100).toFixed(1) : 0;
                    var label = (r.source || 'unknown').replace(/_/g, ' ');
                    var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#1e3a5f';
                    html += '<div class="mb-2">'
                        + '<div class="d-flex justify-content-between mb-1"><span style="font-size:0.875rem;">' + label + '</span><span style="font-size:0.875rem;font-weight:500;">' + r.count + '</span></div>'
                        + '<div class="progress" style="height:4px;"><div class="progress-bar" style="width:' + pct + '%;"></div></div>'
                        + '</div>';
                });
                srcEl.innerHTML = html;
            }

            // Conversations per chatbot
            var botEl = document.getElementById('bot-chart-container');
            var bots = data.bot_chart || [];
            if (bots.length === 0) {
                botEl.innerHTML = '<p class="text-muted mb-0">No conversations yet.</p>';
            } else {
                var maxBot = Math.max(1, ...bots.map(function(b) {
                    return (typeof b.count === 'number') ? b.count : parseInt(b.count || 0);
                }));
                function getUsageColor(pct) {
                    if (pct >= 100) return '#dc2626';
                    if (pct >= 80)  return '#ea580c';
                    if (pct >= 60)  return '#ca8a04';
                    return 'var(--accent)';
                }
                var html2 = '';
                bots.forEach(function(b) {
                    var count = typeof b.count === 'number' ? b.count : parseInt(b.count || 0);
                    var pct = (count / maxBot * 100).toFixed(0);
                    var barColor = 'var(--accent)';
                    if (b.daily_token_budget !== null && b.daily_token_budget > 0) {
                        var usagePct = Math.round((b.tokens_today || 0) / b.daily_token_budget * 100);
                        barColor = getUsageColor(usagePct);
                    }
                    html2 += '<div class="mb-2">'
                        + '<div class="d-flex justify-content-between mb-1"><span style="font-size:0.875rem;">' + (b.chatbot_name || 'Unknown') + '</span><span style="font-size:0.875rem;font-weight:500;">' + count + '</span></div>'
                        + '<div class="progress-bot"><div class="bar" style="width:' + pct + '%;background:' + barColor + ';"></div></div>'
                        + '</div>';
                });
                botEl.innerHTML = html2;
            }
        })
        .catch(function(err) {
            console.error('Stats load failed', err);
        });

    // SVG Line Chart
    function renderLineChart(data) {
        var el = document.getElementById('message-chart');
        if (!data || data.length === 0) {
            el.innerHTML = '<p class="text-muted text-center pt-5">No message data for this period.</p>';
            return;
        }

        var rect = el.getBoundingClientRect();
        var w = Math.max(rect.width, 300);
        var h = 200;
        var pad = { top: 10, right: 10, bottom: 28, left: 40 };
        var plotW = w - pad.left - pad.right;
        var plotH = h - pad.top - pad.bottom;

        var maxVal = Math.max(1, Math.ceil(Math.max.apply(null, data.map(function(d) { return d.count; })) * 1.1));
        var nice = Math.pow(10, Math.floor(Math.log10(maxVal)));
        maxVal = Math.ceil(maxVal / nice) * nice;
        var yStep = Math.max(1, Math.ceil(maxVal / 4 / nice) * nice);
        maxVal = Math.max(yStep * 4, maxVal);

        function xPos(i) { return pad.left + (i / (data.length - 1 || 1)) * plotW; }
        function yPos(v) { return pad.top + plotH - (v / maxVal) * plotH; }

        var points = data.map(function(d, i) {
            return (i === 0 ? '' : ' ') + xPos(i).toFixed(1) + ',' + yPos(d.count).toFixed(1);
        }).join('');

        var areaPoints = (pad.left).toFixed(1) + ',' + (pad.top + plotH).toFixed(1)
            + ' ' + points
            + ' ' + xPos(data.length - 1).toFixed(1) + ',' + (pad.top + plotH).toFixed(1);

        var yLabels = '';
        for (var y = 0; y <= maxVal; y += yStep) {
            var yy = yPos(y);
            yLabels += '<text x="' + (pad.left - 6) + '" y="' + (yy + 4) + '" text-anchor="end" font-size="11" fill="var(--text-muted,#888)">' + y + '</text>';
            if (y > 0) {
                yLabels += '<line x1="' + pad.left + '" y1="' + yy + '" x2="' + (w - pad.right) + '" y2="' + yy + '" stroke="var(--border-light)" stroke-width="1" />';
            }
        }

        var xLabelCount = Math.min(data.length, 10);
        var xLabelStep = Math.max(1, Math.floor((data.length - 1) / (xLabelCount - 1)));
        var xLabels = '';
        for (var i = 0; i < data.length; i += xLabelStep) {
            var xx = xPos(i);
            var label = data[i].date ? data[i].date.slice(5) : '';
            xLabels += '<text x="' + xx + '" y="' + (pad.top + plotH + 17) + '" text-anchor="middle" font-size="11" fill="var(--text-secondary,#888)">' + label + '</text>';
            if (i > 0) {
                xLabels += '<line x1="' + xx + '" y1="' + pad.top + '" x2="' + xx + '" y2="' + (pad.top + plotH) + '" stroke="var(--border)" stroke-width="1" />';
            }
        }
        if ((data.length - 1) % xLabelStep !== 0 && data.length > 1) {
            var lastI = data.length - 1;
            xLabels += '<text x="' + xPos(lastI) + '" y="' + (pad.top + plotH + 17) + '" text-anchor="middle" font-size="10" fill="var(--text-muted,#888)">' + (data[lastI].date ? data[lastI].date.slice(5) : '') + '</text>';
        }

        var dots = data.map(function(d, i) {
            return '<circle cx="' + xPos(i).toFixed(1) + '" cy="' + yPos(d.count).toFixed(1) + '" r="3.5" fill="var(--accent,#1e3a5f)" stroke="var(--surface,#ffffff)" stroke-width="1.5" />';
        }).join('');

        var tooltips = data.map(function(d, i) {
            var cx = xPos(i);
            var ww = (data.length > 1) ? (xPos(1) - xPos(0)) : 60;
            return '<rect x="' + (cx - ww/2) + '" y="' + pad.top + '" width="' + ww + '" height="' + plotH + '" fill="transparent" class="chart-bar-tip" data-count="' + d.count + '" data-date="' + (d.date || '') + '" />';
        }).join('');

        var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#1e3a5f';

        var html = ''
            + '<div style="position:relative;">'
            + '<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '" style="display:block;width:100%;height:auto;">'
            + '<line x1="' + pad.left + '" y1="' + (pad.top + plotH) + '" x2="' + (w - pad.right) + '" y2="' + (pad.top + plotH) + '" stroke="var(--text-muted,#888)" stroke-width="1" />'
            + '<line x1="' + pad.left + '" y1="' + pad.top + '" x2="' + pad.left + '" y2="' + (pad.top + plotH) + '" stroke="var(--text-muted,#888)" stroke-width="1" />'
            + yLabels + xLabels
            + '<polygon points="' + areaPoints + '" fill="' + accentColor + '" opacity="0.12" />'
            + '<polyline points="' + points + '" fill="none" stroke="' + accentColor + '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />'
            + dots
            + tooltips
            + '</svg>'
            + '<div id="chart-tooltip" style="display:none;position:absolute;"></div>'
            + '</div>';

        el.innerHTML = html;

        var tips = el.querySelectorAll('.chart-bar-tip');
        var tipBox = document.getElementById('chart-tooltip');
        tips.forEach(function(rect) {
            rect.addEventListener('mouseenter', function() {
                var count = this.getAttribute('data-count');
                var date = this.getAttribute('data-date');
                tipBox.textContent = date + ': ' + count + ' messages';
                tipBox.style.display = 'block';
            });
            rect.addEventListener('mousemove', function(e) {
                var containerRect = el.querySelector('div[style*="position:relative"]').getBoundingClientRect();
                tipBox.style.left = (e.clientX - containerRect.left + 12) + 'px';
                tipBox.style.top = (e.clientY - containerRect.top + 12) + 'px';
            });
            rect.addEventListener('mouseleave', function() {
                tipBox.style.display = 'none';
            });
        });
    }

    // Date range logic
    function loadChart(from, to) {
        var params = 'from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
        fetch('/api/stats/summary?' + params)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderLineChart(data.message_chart || []);
            })
            .catch(function(err) {
                console.error('Chart load failed', err);
            });
    }

    (function initDates() {
        var now = new Date();
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var firstDay = y + '-' + m + '-01';
        var lastDay = y + '-' + m + '-' + String(new Date(y, now.getMonth() + 1, 0).getDate()).padStart(2, '0');
        document.getElementById('date-from').value = firstDay;
        document.getElementById('date-to').value = lastDay;
    })();

    document.querySelectorAll('[data-range]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[data-range]').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');

            var range = this.getAttribute('data-range');
            var now = new Date();
            var y = now.getFullYear();
            var m = String(now.getMonth() + 1).padStart(2, '0');
            var d = String(now.getDate()).padStart(2, '0');
            var to = y + '-' + m + '-' + d;
            var from;

            if (range === '7d') {
                var d7 = new Date(now);
                d7.setDate(d7.getDate() - 7);
                from = d7.getFullYear() + '-' + String(d7.getMonth() + 1).padStart(2, '0') + '-' + String(d7.getDate()).padStart(2, '0');
            } else if (range === '30d') {
                var d30 = new Date(now);
                d30.setDate(d30.getDate() - 30);
                from = d30.getFullYear() + '-' + String(d30.getMonth() + 1).padStart(2, '0') + '-' + String(d30.getDate()).padStart(2, '0');
            } else {
                from = y + '-' + m + '-01';
                to = y + '-' + m + '-' + String(new Date(y, now.getMonth() + 1, 0).getDate()).padStart(2, '0');
            }

            document.getElementById('date-from').value = from;
            document.getElementById('date-to').value = to;
            loadChart(from, to);
        });
    });

    document.getElementById('apply-range').addEventListener('click', function() {
        var from = document.getElementById('date-from').value;
        var to = document.getElementById('date-to').value;
        if (!from || !to) return;
        document.querySelectorAll('[data-range]').forEach(function(b) { b.classList.remove('active'); });
        loadChart(from, to);
    });
</script>
HEREDOC;

require __DIR__ . '/layout.php';
