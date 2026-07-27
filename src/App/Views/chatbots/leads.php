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
 * Captured leads view for a chatbot.
 *
 * Available variables:
 *   $chatbot — chatbot record (with lead_capture_enabled)
 *   $leads   — array of lead records
 *   $user    — authenticated user
 */
$pageTitle = 'Captured Leads — ' . htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= (int) $chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Captured Leads</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="mb-1">Captured Leads</h1>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($chatbot['name']) ?>
                &middot; <?= count($leads) ?> lead<?= count($leads) !== 1 ? 's' : '' ?> captured
            </p>
        </div>
        <div>
            <a href="/chatbots/<?= (int) $chatbot['id'] ?>" class="btn btn-outline-secondary">&larr; Back to Chatbot</a>
        </div>
    </div>

    <?php if (empty($leads)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-lines-fill fs-1 d-block mb-3 text-muted"></i>
                <p class="text-muted mb-0">
                    No leads captured yet.
                    <?php if (!empty($chatbot['lead_capture_enabled'])): ?>
                        Leads will appear here once the chatbot collects a visitor's name, email, or phone.
                    <?php else: ?>
                        <a href="/chatbots/<?= (int) $chatbot['id'] ?>/edit">Enable lead capture</a> to start collecting visitor information.
                    <?php endif; ?>
                </p>
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
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Summary</th>
                                <th>Captured At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leads as $i => $lead): ?>
                            <tr>
                                <td><?= count($leads) - $i ?></td>
                                <td><strong><?= htmlspecialchars($lead['name']) ?></strong></td>
                                <td><a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($lead['email']) ?></a></td>
                                <td><?= htmlspecialchars($lead['phone']) ?></td>
                                <td><small><?= htmlspecialchars($lead['summary'] ?? '') ?: '<span class="text-muted fst-italic">No summary yet</span>' ?></small></td>
                                <td><?= dt($lead['captured_at'] ?? $lead['created_at'], 'M j, Y g:i A') ?></td>
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
                Leads are captured automatically when a visitor provides their name, email, or phone. A summary of the conversation is also captured and updated as the conversation continues.
                Only one lead per conversation is stored.
            </p>
        </div>
    <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
