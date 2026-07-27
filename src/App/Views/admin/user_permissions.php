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
 * User permission manager view — admin toggles public API endpoints
 * and manages account-level controls.
 *
 * Available variables:
 *   $targetUser        — user/company array
 *   $permissions     — ['widget_chat' => 1, 'widget_quick_answers' => 0, ...]
 *   $endpointToggles — ['widget_chat' => 'Chatbot Widget (public chat API)', ...]
 *   $flashSuccess    — flash message (if any)
 */
$csrfToken = \App\Auth\Session::csrfToken();
$isActive  = $targetUser['is_active'] ?? 1;
?>
<div class="container mt-4">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $flashSuccess ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">
                <a href="/admin" class="text-decoration-none text-reset">Users</a>
                <span class="text-muted mx-2">/</span>
                <?= htmlspecialchars($targetUser['company_name'] ?? $targetUser['name'] ?? 'User') ?>
                <?php if (!$isActive): ?>
                    <span class="badge bg-danger ms-2">Disabled</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:0.85rem;">
                Manage account settings and endpoint permissions.
                <span class="badge bg-secondary-subtle text-secondary ms-2">slug: <?= htmlspecialchars($targetUser['slug'] ?? '—') ?></span>
            </p>
        </div>
        <a href="/admin" class="btn btn-outline-secondary btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:2px;"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back to Dashboard
        </a>
    </div>

    <form method="POST" action="/admin/users/<?= (int) $targetUser['id'] ?>/permissions">
        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

        <!-- ═══════ Account Status ═══════ -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <strong>Account Status</strong>
                <span class="text-muted fw-normal" style="font-size:0.85rem;">&mdash; enable or disable this user's account</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <label class="fw-medium mb-0" for="perm_is_active" style="cursor:pointer;">Account Enabled</label>
                        <div class="text-muted" style="font-size:0.85rem;">
                            When disabled, the user cannot log in and all public endpoints (widget, quick answers) are deactivated.
                        </div>
                    </div>
                    <div class="form-check form-switch switch-cell mb-0">
                        <input class="form-check-input" type="checkbox"
                               id="perm_is_active"
                               name="is_active"
                               value="1"
                               role="switch"
                               <?= $isActive ? 'checked' : '' ?>>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════ Endpoint Toggles ═══════ -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="3" y1="3" x2="9" y2="9"/></svg>
                <strong>Endpoint Toggles</strong>
                <span class="text-muted fw-normal" style="font-size:0.85rem;">&mdash; enable / disable public API endpoints (widget, embed, etc.)</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $endCols = 2;
                    $endChunks = array_chunk($endpointToggles, (int) ceil(count($endpointToggles) / $endCols), true);
                    foreach ($endChunks as $chunk):
                    ?>
                    <div class="col-md-<?= 12 / $endCols ?> mb-3">
                        <?php foreach ($chunk as $key => $label): ?>
                        <div class="card mb-2 perm-card">
                            <div class="card-body d-flex justify-content-between align-items-center py-2 px-3">
                                <label class="form-label mb-0 fw-medium" for="perm_<?= $key ?>" style="cursor:pointer;font-size:0.9rem;">
                                    <?= htmlspecialchars($label) ?>
                                </label>
                                <div class="form-check form-switch switch-cell mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           id="perm_<?= $key ?>"
                                           name="perms[<?= $key ?>]"
                                           value="1"
                                           role="switch"
                                           <?= !empty($permissions[$key]) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ═══════ Change Password ═══════ -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <strong>Change Password</strong>
                <span class="text-muted fw-normal" style="font-size:0.85rem;">&mdash; set a new password for this user (no email verification required)</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave blank to keep current" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label for="new_password_confirm" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" placeholder="Re-enter new password" autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" id="enableAllBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px;"><polyline points="20 6 9 17 4 12"/></svg>
                    Enable All
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="disableAllBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:2px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Disable All
                </button>
            </div>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Update
            </button>
        </div>
    </form>
</div>

<?php
$pageScripts = <<<'HEREDOC'
<script>
document.getElementById('enableAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.form-check-input').forEach(function(cb) { cb.checked = true; });
});
document.getElementById('disableAllBtn')?.addEventListener('click', function() {
    document.querySelectorAll('.form-check-input').forEach(function(cb) { cb.checked = false; });
});
</script>
HEREDOC;

