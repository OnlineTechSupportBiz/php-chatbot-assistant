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
 * Dashboard index view (admin account landing page after login).
 * Requires $user from Auth::requireAuth().
 */
$pageTitle = 'Dashboard - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container mt-4">
    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <h1 class="mb-4">Dashboard</h1>

    <!-- Row 1: Big stat cards -->
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card stat-card stat-card-primary">
                <div class="card-body">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                    </div>
                    <h2 class="card-title h5">Chatbots</h2>
                    <p class="card-text display-6" id="bot-count">—</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card stat-card-accent2">
                <div class="card-body">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM6 20V4h5v7h7v9H6z"/></svg>
                    </div>
                    <h2 class="card-title h5">Documents</h2>
                    <p class="card-text display-6" id="doc-count">—</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card stat-card-accent">
                <div class="card-body">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                    </div>
                    <h2 class="card-title h5">Conversations</h2>
                    <p class="card-text display-6" id="conv-count">—</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stat-card stat-card-amber">
                <div class="card-body">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                    </div>
                    <h2 class="card-title h5">Messages</h2>
                    <p class="card-text display-6" id="msg-count">—</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Secondary stat cards -->
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-1 text-muted">Unique Visitors</h6>
                    <p class="card-text display-6" id="visitor-count">—</p>
                    <small class="text-muted" style="font-size:0.75rem;">Distinct people who have interacted with your chatbots</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-1 text-muted">Tokens Used</h6>
                    <p class="card-text display-6" id="token-count">—</p>
                    <small class="text-muted" style="font-size:0.75rem;">Rough estimate of AI text processed (1 token ≈ 4 characters)</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-subtitle mb-1 text-muted">Avg Response Time</h6>
                    <p class="card-text display-6" id="avg-time">—</p>
                    <small class="text-muted" style="font-size:0.75rem;">Average time for the AI to respond to a user message</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Charts -->
    <div class="row mt-2">
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Messages (This month)</span>
                    <div class="d-flex align-items-center gap-2 flex-wrap" id="date-range-controls">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-range="7d">7 Days</button>
                            <button type="button" class="btn btn-outline-secondary" data-range="30d">30 Days</button>
                            <button type="button" class="btn btn-outline-secondary" data-range="month">This Month</button>
                        </div>
                        <input type="date" id="date-from" class="form-control form-control-sm" style="width:140px;">
                        <input type="date" id="date-to" class="form-control form-control-sm" style="width:140px;">
                        <button type="button" class="btn btn-sm btn-primary" id="apply-range">Apply</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="message-chart" style="min-height:200px;width:100%;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <span>Response Sources</span>
                    <small class="text-muted d-block" style="font-size:0.75rem;">Where the AI pulls each answer from</small>
                </div>
                <div class="card-body" id="source-breakdown">
                    <p class="text-muted mb-0">Loading…</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 4: Quick Actions & Per-Bot Stats -->
    <div class="row mt-2">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Quick Actions</span>
                </div>
                <div class="card-body d-flex gap-2 flex-wrap">
                    <a href="/chatbots" class="btn btn-outline-primary">Manage Chatbots</a>
                    <a href="/chatbots/create" class="btn btn-primary">Create Chatbot</a>
                    <a href="/settings" class="btn btn-outline-secondary">Settings</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <span>Conversations per Chatbot</span>
                </div>
                <div class="card-body pt-0">
                    <small class="text-muted d-block mb-2">Lifetime conversation count per chatbot. Bar color reflects today's token budget usage when set.</small>
                </div>
                <div class="card-body" id="bot-chart-container">
                    <p class="text-muted mb-0">Loading…</p>
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
            document.getElementById('bot-count').textContent = data.chatbots ?? '—';
            document.getElementById('doc-count').textContent = data.documents ?? '—';
            document.getElementById('conv-count').textContent = data.conversations ?? '—';
            document.getElementById('msg-count').textContent = data.messages ?? '—';
            document.getElementById('visitor-count').textContent = data.unique_visitors ?? '—';
            document.getElementById('token-count').textContent = (data.tokens_used ?? 0).toLocaleString();
            document.getElementById('avg-time').innerHTML = data.avg_response_time ? Math.round(data.avg_response_time).toLocaleString() + ' <small>ms</small>' : '—';

            // ── Message chart (SVG line chart) ──
            renderLineChart(data.message_chart || []);

            // ── Source breakdown ──
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
                    html += '<div class="mb-2">'
                        + '<div class="d-flex justify-content-between"><span>' + label + '</span><span>' + r.count + '</span></div>'
                        + '<div class="progress" style="height:6px;"><div class="progress-bar" style="width:' + pct + '%;background:var(--accent,#2563eb);"></div></div>'
                        + '</div>';
                });
                srcEl.innerHTML = html;
            }

            // ── Conversations per chatbot ──
            var botEl = document.getElementById('bot-chart-container');
            var bots = data.bot_chart || [];
            if (bots.length === 0) {
                botEl.innerHTML = '<p class="text-muted mb-0">No conversations yet.</p>';
            } else {
                var maxBot = Math.max(1, ...bots.map(function(b) {
                    return (typeof b.count === 'number') ? b.count : parseInt(b.count || 0);
                }));
                function getLimitGradient(pct) {
                    if (pct >= 100) return 'linear-gradient(90deg, #dc2626, #ef4444)';
                    if (pct >= 80)  return 'linear-gradient(90deg, #ea580c, #f97316)';
                    if (pct >= 60)  return 'linear-gradient(90deg, #ca8a04, #eab308)';
                    return 'linear-gradient(90deg, #0d9488, #14b8a6)';
                }
                var html2 = '';
                bots.forEach(function(b) {
                    var count = typeof b.count === 'number' ? b.count : parseInt(b.count || 0);
                    var pct = (count / maxBot * 100).toFixed(0);
                    var bg = 'var(--accent2,#0d9488)';
                    if (b.daily_token_budget !== null && b.daily_token_budget > 0) {
                        var usagePct = Math.round((b.tokens_today || 0) / b.daily_token_budget * 100);
                        bg = getLimitGradient(usagePct);
                    }
                    html2 += '<div class="mb-2">'
                        + '<div class="d-flex justify-content-between"><span>' + (b.chatbot_name || 'Unknown') + '</span><span>' + count + '</span></div>'
                        + '<div class="progress" style="height:8px;"><div class="progress-bar" style="width:' + pct + '%;background:' + bg + ';"></div></div>'
                        + '</div>';
                });
                botEl.innerHTML = html2;
            }
        })
        .catch(function(err) {
            console.error('Stats load failed', err);
        });

    // ── SVG Line Chart Renderer ──
    function renderLineChart(data) {
        var el = document.getElementById('message-chart');
        if (!data || data.length === 0) {
            el.innerHTML = '<p class="text-muted text-center pt-5">No message data for this period.</p>';
            return;
        }

        // Determine responsive width
        var rect = el.getBoundingClientRect();
        var w = Math.max(rect.width, 300);
        var h = 200;
        var pad = { top: 10, right: 10, bottom: 28, left: 40 };
        var plotW = w - pad.left - pad.right;
        var plotH = h - pad.top - pad.bottom;

        var maxVal = Math.max(1, Math.ceil(Math.max.apply(null, data.map(function(d) { return d.count; })) * 1.1));
        // Round max to a nice number
        var nice = Math.pow(10, Math.floor(Math.log10(maxVal)));
        maxVal = Math.ceil(maxVal / nice) * nice;
        // At least 4 y-axis ticks
        var yStep = Math.max(1, Math.ceil(maxVal / 4 / nice) * nice);
        maxVal = Math.max(yStep * 4, maxVal);

        function xPos(i) { return pad.left + (i / (data.length - 1 || 1)) * plotW; }
        function yPos(v) { return pad.top + plotH - (v / maxVal) * plotH; }

        // Build polyline points
        var points = data.map(function(d, i) {
            return (i === 0 ? '' : ' ') + xPos(i).toFixed(1) + ',' + yPos(d.count).toFixed(1);
        }).join('');

        // Build area fill points (polygon: start at bottom-left, trace line, back to bottom-right)
        var areaPoints = (pad.left).toFixed(1) + ',' + (pad.top + plotH).toFixed(1)
            + ' ' + points
            + ' ' + xPos(data.length - 1).toFixed(1) + ',' + (pad.top + plotH).toFixed(1);

        // Y-axis labels (4 ticks)
        var yLabels = '';
        for (var y = 0; y <= maxVal; y += yStep) {
            var yy = yPos(y);
            yLabels += '<text x="' + (pad.left - 6) + '" y="' + (yy + 4) + '" text-anchor="end" font-size="11" fill="var(--text-muted,#888)">' + y + '</text>';
            if (y > 0) {
                yLabels += '<line x1="' + pad.left + '" y1="' + yy + '" x2="' + (w - pad.right) + '" y2="' + yy + '" stroke="var(--border-color,rgba(128,128,128,0.15))" stroke-width="1" />';
            }
        }

        // X-axis labels (show up to ~10 evenly spaced)
        var xLabelCount = Math.min(data.length, 10);
        var xLabelStep = Math.max(1, Math.floor((data.length - 1) / (xLabelCount - 1)));
        var xLabels = '';
        for (var i = 0; i < data.length; i += xLabelStep) {
            var xx = xPos(i);
            var label = data[i].date ? data[i].date.slice(5) : '';
            xLabels += '<text x="' + xx + '" y="' + (pad.top + plotH + 17) + '" text-anchor="middle" font-size="10" fill="var(--text-muted,#888)">' + label + '</text>';
            // Vertical gridline
            if (i > 0) {
                xLabels += '<line x1="' + xx + '" y1="' + pad.top + '" x2="' + xx + '" y2="' + (pad.top + plotH) + '" stroke="var(--border-color,rgba(128,128,128,0.08))" stroke-width="1" />';
            }
        }
        // Ensure last label
        if ((data.length - 1) % xLabelStep !== 0 && data.length > 1) {
            var lastI = data.length - 1;
            xLabels += '<text x="' + xPos(lastI) + '" y="' + (pad.top + plotH + 17) + '" text-anchor="middle" font-size="10" fill="var(--text-muted,#888)">' + (data[lastI].date ? data[lastI].date.slice(5) : '') + '</text>';
        }

        // Data point circles
        var dots = data.map(function(d, i) {
            return '<circle cx="' + xPos(i).toFixed(1) + '" cy="' + yPos(d.count).toFixed(1) + '" r="3.5" fill="var(--accent,#2563eb)" stroke="#fff" stroke-width="1.5" />';
        }).join('');

        // Tooltip overlay (invisible rects per data point)
        var tooltips = data.map(function(d, i) {
            var cx = xPos(i);
            var cy = yPos(d.count);
            var ww = (data.length > 1) ? (xPos(1) - xPos(0)) : 60;
            return '<rect x="' + (cx - ww/2) + '" y="' + pad.top + '" width="' + ww + '" height="' + plotH + '" fill="transparent" class="chart-bar-tip" data-count="' + d.count + '" data-date="' + (d.date || '') + '" />';
        }).join('');

        // Hover tooltip element
        var html = ''
            + '<div style="position:relative;">'
            + '<svg width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '" style="display:block;width:100%;height:auto;">'
            // Bottom axis line
            + '<line x1="' + pad.left + '" y1="' + (pad.top + plotH) + '" x2="' + (w - pad.right) + '" y2="' + (pad.top + plotH) + '" stroke="var(--text-muted,#888)" stroke-width="1" />'
            // Left axis line
            + '<line x1="' + pad.left + '" y1="' + pad.top + '" x2="' + pad.left + '" y2="' + (pad.top + plotH) + '" stroke="var(--text-muted,#888)" stroke-width="1" />'
            // Gridlines and labels
            + yLabels + xLabels
            // Area fill
            + '<polygon points="' + areaPoints + '" fill="url(#chartGrad)" opacity="0.3" />'
            // Line
            + '<polyline points="' + points + '" fill="none" stroke="var(--accent,#2563eb)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />'
            // Dots
            + dots
            // Tooltip hover targets
            + tooltips
            // Gradient def
            + '<defs>'
            + '<linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">'
            + '<stop offset="0%" stop-color="var(--accent,#2563eb)" stop-opacity="0.4" />'
            + '<stop offset="100%" stop-color="var(--accent,#2563eb)" stop-opacity="0.02" />'
            + '</linearGradient>'
            + '</defs>'
            + '</svg>'
            + '<div id="chart-tooltip" style="display:none;position:absolute;pointer-events:none;background:var(--surface,#1a1a2e);color:var(--text-primary,#fff);border:1px solid var(--border-color,rgba(255,255,255,0.1));border-radius:6px;padding:6px 10px;font-size:12px;white-space:nowrap;z-index:10;"></div>'
            + '</div>';

        el.innerHTML = html;

        // Hover tooltip logic
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
                var svgRect = el.querySelector('svg').getBoundingClientRect();
                tipBox.style.left = (e.clientX - svgRect.left + 10) + 'px';
                tipBox.style.top = (e.clientY - svgRect.top - 30) + 'px';
            });
            rect.addEventListener('mouseleave', function() {
                tipBox.style.display = 'none';
            });
        });
    }

    // ── Date range selector logic ──
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

    // Set default date inputs to first/last of this month
    (function initDates() {
        var now = new Date();
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var firstDay = y + '-' + m + '-01';
        var lastDay = y + '-' + m + '-' + String(new Date(y, now.getMonth() + 1, 0).getDate()).padStart(2, '0');
        document.getElementById('date-from').value = firstDay;
        document.getElementById('date-to').value = lastDay;
    })();

    // Preset buttons
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
            } else { // month
                from = y + '-' + m + '-01';
                to = y + '-' + m + '-' + String(new Date(y, now.getMonth() + 1, 0).getDate()).padStart(2, '0');
            }

            document.getElementById('date-from').value = from;
            document.getElementById('date-to').value = to;
            loadChart(from, to);
        });
    });

    // Apply button
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
