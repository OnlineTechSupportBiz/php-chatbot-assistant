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
 * Unified admin dashboard — Platform Settings, Top Accounts, and full Accounts table.
 *
 * Available variables:
 *   $registrationEnabled — bool for registration toggle
 *   $topUsers             — array (top 5 by chatbot count)
 *   $users                — array (all user accounts with chatbot_count)
 *   $search              — current search query (if any)
 *   $flashSuccess        — flash message (if any)
 */
$search = htmlspecialchars($_GET['q'] ?? '');
?><div class="container mt-4">
    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Admin Dashboard</h1>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Platform management across all accounts</p>
        </div>
    </div>

    <!-- Platform Settings -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Platform Settings</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="/admin/registration">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="registration_enabled" name="registration_enabled"
                                   value="1"
                                   <?= ($registrationEnabled ? 'checked' : '') ?>>
                            <label class="form-check-label" for="registration_enabled">
                                Allow new user registration
                            </label>
                        </div>
                        <p class="text-muted small mb-3">
                            When disabled, new accounts cannot be created via the public registration page.
                        </p>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Accounts by Chatbot Count -->
    <div class="card mb-4">
        <div class="card-header">
            <span>Top Accounts by Chatbot Count</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th class="text-center">Chatbots</th>
                            <th class="text-center">Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUsers as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td class="text-center"><?= $t['chatbot_count'] ?></td>
                            <td class="text-center"><?= $t['doc_count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topUsers)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No user accounts yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- All Accounts (full searchable table) -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>All Accounts</span>
            <form method="GET" action="/admin" class="d-flex gap-2">
                <input type="text" name="q" class="form-control form-control-sm" style="width:220px;" placeholder="Search accounts…" value="<?= $search ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="/admin" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Slug</th>
                            <th class="text-center">Chatbots</th>
                            <th class="text-center">Active</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                            <td><?= htmlspecialchars($t['company_name'] ?? '—') ?></td>
                            <td><code><?= htmlspecialchars($t['slug'] ?? '—') ?></code></td>
                            <td class="text-center"><?= (int) $t['chatbot_count'] ?></td>
                            <td class="text-center">
                                <?php if ($t['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= dt($t['created_at'], 'M j, Y') ?></td>
                            <td class="text-center">
                                <a href="/admin/users/<?= (int) $t['id'] ?>/permissions" class="btn btn-sm btn-outline-secondary" title="Manage permissions">Permissions</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><?= $search !== '' ? 'No accounts match your search.' : 'No user accounts registered yet.' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

