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
 * Admin settings page — brand name, MFA, recent audit log.
 *
 * Available variables:
 *   $user             — array (authenticated admin user)
 *   $admin            — array (full admin record)
 *   $mfaEnabled       — bool
 *   $auditLogEntries  — array (recent audit log rows)
 */
?><div class="container-fluid mt-4">
    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h1 class="mb-1">Settings</h1>
    <p class="text-muted mb-4" style="font-size:0.85rem;">Configure your account and security settings</p>

    <div class="row">
        <!-- Brand Name -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Brand Name</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:0.85rem;">This name appears in the sidebar and email communications.</p>
                    <form method="POST" action="/admin/settings/brand-name">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <div class="mb-3">
                            <label for="brand_name" class="form-label">Brand Name</label>
                            <input type="text" class="form-control" id="brand_name" name="brand_name"
                                   value="<?= htmlspecialchars($admin['brand_name'] ?? '') ?>"
                                   placeholder="e.g. My Company">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Brand Name</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Details -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Account Details</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['name'] ?? '') ?></dd>
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['email'] ?? '') ?></dd>
                        <dt class="col-sm-4">Company</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($user['brand_name'] ?? '') ?></dd>
                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><span class="badge bg-danger">Super Admin</span></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Timezone -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Timezone</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:0.85rem;">All dates and times are shown in your selected timezone.</p>
                    <form method="POST" action="/admin/settings/timezone">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach (\App\Util\DateTimeHelper::TIMEZONES as $tz => $label): ?>
                                <option value="<?= htmlspecialchars($tz) ?>" <?= ($user['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Timezone</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Multi-Factor Auth -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Multi-Factor Authentication</h2>
                </div>
                <div class="card-body">
                    <?php if ($mfaEnabled): ?>
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-success me-2">Enabled</span>
                        <span class="text-muted">Authenticator app is configured.</span>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                data-bs-target="#disableMfaModal">
                            Disable MFA
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-3">Add an extra layer of security to your account by enabling two-factor authentication.</p>
                    <a href="/admin/mfa/setup" class="btn btn-primary">Enable MFA</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Audit Log -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Recent Audit Log</h2>
                    <a href="/admin/settings/audit-log" class="btn btn-outline-secondary btn-sm">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($auditLogEntries['rows'])): ?>
                    <p class="text-muted p-3 mb-0">No audit log entries yet.</p>
                    <?php else: ?>
                    <table class="table table-striped table-hover mb-0" style="font-size:0.85rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogEntries['rows'] as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars($entry['action'] ?? '') ?></td>
                                <td><?= htmlspecialchars($entry['entity_type'] ?? '') ?></td>
                                <td style="white-space:nowrap;"><?= htmlspecialchars(
                                    isset($entry['created_at']) ? dt($entry['created_at'], 'M j, g:i A') : ''
                                ) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($mfaEnabled): ?>
<!-- Disable MFA Modal -->
<div class="modal fade" id="disableMfaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-labelledby="mfa-modal-title" aria-modal="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="mfa-modal-title">Disable Multi-Factor Authentication</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/mfa/disable">
                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                <div class="modal-body">
                    <p>Are you sure you want to disable MFA? Your account will be less secure.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Disable MFA</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
