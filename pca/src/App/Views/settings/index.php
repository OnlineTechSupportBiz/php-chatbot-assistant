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
 * Admin settings view.
 *
 * @var array $user       Authenticated user
 * @var array $admin      Admin record
 * @var array $keys       API keys (openai_api_key, llamacloud_api_key)
 * @var bool  $mfaEnabled Whether MFA is configured
 * @var array $recoveryCodes Recovery codes (if MFA enabled)
 */
$pageTitle = 'Settings - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <h1 class="mb-4">Settings</h1>

    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($codes = \App\Auth\Session::getFlash('mfa_recovery_codes')): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <strong>Recovery Codes</strong> — Save these one-time use codes in a safe place. Each code can be used once if you lose access to your authenticator app.
        <div class="bg-dark p-3 mt-2 rounded" style="font-family: monospace; font-size: 0.9rem; color: #e0e0e0;">
            <?php foreach ($codes as $code): ?>
            <div><?= htmlspecialchars($code) ?></div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- API Key Section -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">API Settings</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="/settings/api-keys">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                        <div class="mb-3">
                            <label for="openai_api_key" class="form-label">OpenAI API Key</label>
                            <input type="password" class="form-control" id="openai_api_key" name="openai_api_key"
                                   value="<?= htmlspecialchars($keys['openai_api_key'] ?? '') ?>"
                                   placeholder="sk-...">
                            <div class="form-text">Used for embeddings (text-embedding-3-small).</div>
                        </div>

                        <div class="mb-3">
                            <label for="llamacloud_api_key" class="form-label">LlamaCloud API Key</label>
                            <input type="password" class="form-control" id="llamacloud_api_key" name="llamacloud_api_key"
                                   value="<?= htmlspecialchars($keys['llamacloud_api_key'] ?? '') ?>"
                                   placeholder="llx-...">
                            <div class="form-text">Used for parsing uploaded documents via LlamaParse. Required for document ingestion.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save API Keys</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Details Section -->
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
                        <dd class="col-sm-8"><?= htmlspecialchars($user['company_name'] ?? '') ?></dd>
                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Multi-Factor Auth Section -->
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
                    <p class="text-muted mb-3">Add an extra layer of security to your account.</p>
                    <a href="/settings/mfa/setup" class="btn btn-primary">Enable MFA</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Brand Name Section -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Brand Name</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:0.85rem;">This name appears in the sidebar, page titles, and email communications.</p>
                    <form method="POST" action="/settings/brand-name">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <div class="mb-3">
                            <label for="brand_name" class="form-label">Brand Name</label>
                            <input type="text" class="form-control" id="brand_name" name="brand_name"
                                   value="<?= htmlspecialchars($user['brand_name'] ?? 'Chatbot Assistant') ?>"
                                   maxlength="255" placeholder="Your company or brand name">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Brand Name</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Timezone Section -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Timezone</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:0.85rem;">All dates and times are shown in your selected timezone.</p>
                    <form method="POST" action="/settings/timezone">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <div class="mb-3">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach (\App\Util\DateTimeHelper::TIMEZONES as $tz => $label): ?>
                                <option value="<?= htmlspecialchars($tz) ?>" <?= ($userTimezone ?? 'UTC') === $tz ? 'selected' : '' ?>>
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
    </div>

    <div class="row">
        <!-- Audit Log Section -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">Audit History</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Review all changes made to your tenant — API key updates, user logins, chatbot changes, and more.</p>
                    <a href="/settings/audit-log" class="btn btn-outline-primary">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right:4px;vertical-align:text-bottom;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        View Audit Log
                    </a>
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
            <form method="POST" action="/settings/mfa/disable">
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
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
