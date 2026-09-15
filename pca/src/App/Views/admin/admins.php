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
 * List all admin accounts (super admin only).
 *
 * @var array  $admins       All admin (org) records
 * @var string $search       Current search query (for form persistence)
 * @var string $flashSuccess Flash message (if any)
 */
$csrfToken = \App\Auth\Session::csrfToken();
?>
<div class="container mt-4">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $flashSuccess ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Admins</h1>
    </div>

    <!-- Search -->
    <form method="GET" action="/admin/admins" class="mb-4">
        <div class="input-group" style="max-width:400px;">
            <input type="text" name="q" class="form-control" placeholder="Search by name or slug…" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Search
            </button>
        </div>
    </form>

    <!-- Admins table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Users</th>
                    <th>Chatbots</th>
                    <th>Created</th>
                    <th>Permissions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No admin accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($row['slug']) ?></code></td>
                            <td><?= (int) ($row['user_count'] ?? 0) ?></td>
                            <td><?= (int) ($row['chatbot_count'] ?? 0) ?></td>
                            <td><?= dt($row['created_at'] ?? '', 'M j, Y g:i A') ?></td>
                            <td>
                                <a href="/admin/users/<?= (int) $row['id'] ?>/permissions" class="btn btn-sm btn-outline-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Permissions
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <a href="/admin" class="btn btn-outline-secondary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:2px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Dashboard
        </a>
    </div>
</div>


