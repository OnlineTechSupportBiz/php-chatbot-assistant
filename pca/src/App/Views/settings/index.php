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
<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p class="subtitle">Branding, API keys, and account preferences.</p>
    </div>
</div>

<?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
    <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($codes = \App\Auth\Session::getFlash('mfa_recovery_codes')): ?>
    <div class="alert alert-info">
        <strong>Recovery codes</strong> — Save these one-time use codes in a safe place. Each code can be used once if you lose access to your authenticator app.
        <pre class="code-block" style="margin-top:0.75rem;"><?php foreach ($codes as $code): ?><?= htmlspecialchars($code) ?>
<?php endforeach; ?></pre>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Account</h2>
    <div class="form-row">
        <div class="field">
            <div class="label">Name</div>
            <div class="muted"><?= htmlspecialchars($user['name'] ?? '') ?></div>
        </div>
        <div class="field">
            <div class="label">Email</div>
            <div class="muted"><?= htmlspecialchars($user['email'] ?? '') ?></div>
        </div>
        <div class="field">
            <div class="label">Company</div>
            <div class="muted"><?= htmlspecialchars($user['company_name'] ?? '') ?></div>
        </div>
        <div class="field">
            <div class="label">Role</div>
            <div class="muted"><?= htmlspecialchars(ucfirst($user['role'] ?? 'user')) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Branding</h2>
    <p class="muted" style="font-size:0.85rem;">This name appears in the sidebar, page titles, and email communications.</p>
    <form method="POST" action="/settings/brand-name">
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
        <div class="field">
            <label class="label" for="brand_name">Brand name</label>
            <input type="text" class="input" id="brand_name" name="brand_name"
                   value="<?= htmlspecialchars($user['brand_name'] ?? 'Chatbot Assistant') ?>"
                   maxlength="255" placeholder="Your company or brand name">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save brand name</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Timezone</h2>
    <p class="muted" style="font-size:0.85rem;">All dates and times are shown in your selected timezone.</p>
    <form method="POST" action="/settings/timezone">
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
        <div class="field">
            <label class="label" for="timezone">Timezone</label>
            <select class="select" id="timezone" name="timezone">
                <?php foreach (\App\Util\DateTimeHelper::TIMEZONES as $tz => $label): ?>
                <option value="<?= htmlspecialchars($tz) ?>" <?= ($userTimezone ?? 'UTC') === $tz ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save timezone</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>API keys</h2>
    <form method="POST" action="/settings/api-keys">
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

        <div class="field">
            <label class="label" for="openai_api_key">OpenAI API key</label>
            <input type="password" class="input" id="openai_api_key" name="openai_api_key"
                   value=""
                   placeholder="<?= htmlspecialchars($keys['openai_api_key_hint'] ?? 'sk-...') ?>">
            <div class="muted" style="font-size:0.8rem;">Used for embeddings and chat when no LLM models are configured below. Leave blank to keep the current key.</div>
        </div>

        <div class="field">
            <label class="label" for="llamacloud_api_key">LlamaCloud API key</label>
            <input type="password" class="input" id="llamacloud_api_key" name="llamacloud_api_key"
                   value=""
                   placeholder="<?= htmlspecialchars($keys['llamacloud_api_key_hint'] ?? 'llx-...') ?>">
            <div class="muted" style="font-size:0.8rem;">Used for parsing uploaded documents via LlamaParse. Required for document ingestion. Leave blank to keep the current key.</div>
        </div>

        <h2 style="margin-top:1.25rem;">Model management</h2>
        <p class="muted" style="margin-top:0;">
            Add one or more LLM models. The first is the primary; on a failure the app
            fails over to the next in the list. Every provider is OpenAI-compatible,
            so self-hosted or local servers (Ollama, LM Studio, vLLM) work the same way.
        </p>
        <div class="field">
            <label class="label" for="llm_base_url">LLM base URL</label>
            <input type="text" class="input" id="llm_base_url" name="llm_base_url"
                   value="<?= htmlspecialchars($keys['llm_base_url'] ?? '') ?>"
                   placeholder="https://api.openai.com/v1">
            <div class="muted" style="font-size:0.8rem;">Used for chat when no model list entry carries its own base URL. Leave blank for OpenAI.</div>
        </div>

        <div id="llm-model-rows">
            <?php if (($keys['llm_models'] ?? []) === []): ?>
                <p class="muted" id="no-models-note">No models configured yet. Add one to select it in a chatbot's settings.</p>
            <?php endif; ?>
            <?php foreach ($keys['llm_models'] as $i => $m): ?>
                <div class="form-row model-row">
                    <div class="field">
                        <label class="label"><?= $i === 0 ? 'Name / label (primary)' : 'Name / label (fallback)' ?></label>
                        <input class="input" name="llm_model_name[]" value="<?= htmlspecialchars($m['name']) ?>"
                               placeholder="<?= $i === 0 ? 'Primary' : 'Fallback' ?>">
                    </div>
                    <div class="field">
                        <label class="label">Base URL</label>
                        <input class="input" name="llm_model_base_url[]" value="<?= htmlspecialchars($m['base_url']) ?>"
                               placeholder="https://api.openai.com/v1">
                    </div>
                    <div class="field">
                        <label class="label">Model</label>
                        <input class="input" name="llm_model_id[]" value="<?= htmlspecialchars($m['model']) ?>"
                               placeholder="gpt-4.1-mini">
                    </div>
                    <div class="field">
                        <label class="label">API key</label>
                        <input class="input" type="password" autocomplete="off" name="llm_model_key[]" value=""
                               placeholder="<?= $m['hint'] !== null ? 'Leave blank to keep (' . htmlspecialchars($m['hint']) . ')' : 'Not set' ?>">
                    </div>
                    <div class="row" style="align-items:end;">
                        <button type="button" class="btn btn-sm" onclick="moveModelRow(this, -1)" title="Move up" aria-label="Move model up">↑</button>
                        <button type="button" class="btn btn-sm" onclick="moveModelRow(this, 1)" title="Move down" aria-label="Move model down">↓</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeModelRow(this)" title="Remove" aria-label="Remove model">×</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-actions" style="margin-top:0.5rem;">
            <button type="button" class="btn" onclick="addModelRow()">Add model</button>
        </div>

        <h2 style="margin-top:1.25rem;">Embeddings</h2>
        <p class="muted" style="margin-top:0;">Falls back to the LLM provider above, then to OpenAI, when left blank.</p>
        <div class="form-row">
            <div class="field">
                <label class="label" for="embedding_base_url">Base URL</label>
                <input type="text" class="input" id="embedding_base_url" name="embedding_base_url"
                       value="<?= htmlspecialchars($keys['embedding_base_url'] ?? '') ?>"
                       placeholder="https://api.openai.com/v1">
            </div>
            <div class="field">
                <label class="label" for="embedding_api_key">API key</label>
                <input type="password" class="input" id="embedding_api_key" name="embedding_api_key"
                       value="" autocomplete="off"
                       placeholder="<?= htmlspecialchars($keys['embedding_api_key_hint'] ?? 'Not set') ?>">
            </div>
            <div class="field">
                <label class="label" for="embedding_model">Model</label>
                <input type="text" class="input" id="embedding_model" name="embedding_model"
                       value="<?= htmlspecialchars($keys['embedding_model'] ?? '') ?>"
                       placeholder="text-embedding-3-small">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save provider settings</button>
        </div>
    </form>
</div>

<script>
// Multi-model rows: add / remove / reorder before the form is submitted. The
// server reads the parallel llm_model_* arrays by index.
function addModelRow() {
    var wrap = document.getElementById('llm-model-rows');
    var note = document.getElementById('no-models-note');
    if (note) note.remove();
    var div = document.createElement('div');
    div.className = 'form-row model-row';
    div.innerHTML =
        '<div class="field"><label class="label">Name / label</label>' +
        '<input class="input" name="llm_model_name[]" placeholder="Fallback"></div>' +
        '<div class="field"><label class="label">Base URL</label>' +
        '<input class="input" name="llm_model_base_url[]" placeholder="https://api.openai.com/v1"></div>' +
        '<div class="field"><label class="label">Model</label>' +
        '<input class="input" name="llm_model_id[]" placeholder="gpt-4.1-mini"></div>' +
        '<div class="field"><label class="label">API key</label>' +
        '<input class="input" type="password" autocomplete="off" name="llm_model_key[]" placeholder="Not set"></div>' +
        '<div class="row" style="align-items:end;">' +
        '<button type="button" class="btn btn-sm" onclick="moveModelRow(this, -1)" title="Move up" aria-label="Move model up">↑<\/button>' +
        '<button type="button" class="btn btn-sm" onclick="moveModelRow(this, 1)" title="Move down" aria-label="Move model down">↓<\/button>' +
        '<button type="button" class="btn btn-sm btn-danger" onclick="removeModelRow(this)" title="Remove" aria-label="Remove model">×</button>' +
        '</div>';
    wrap.appendChild(div);
}
function removeModelRow(btn) {
    var row = btn.closest('.model-row');
    if (row) row.remove();
}
function moveModelRow(btn, dir) {
    var row = btn.closest('.model-row');
    if (!row) return;
    var wrap = row.parentNode;
    var siblings = Array.prototype.filter.call(wrap.children, function (c) { return c.classList.contains('model-row'); });
    var i = siblings.indexOf(row);
    var j = i + dir;
    if (j < 0 || j >= siblings.length) return;
    wrap.insertBefore(row, dir < 0 ? siblings[j] : siblings[j].nextSibling);
}
</script>

<div class="card">
    <h2>Multi-factor authentication</h2>
    <?php if ($mfaEnabled): ?>
        <div class="row" style="margin-bottom:0.75rem;">
            <span class="badge badge-success">Enabled</span>
            <span class="muted">Authenticator app is configured.</span>
        </div>
        <form method="POST" action="/settings/mfa/disable"
              onsubmit="return confirm('Are you sure you want to disable MFA? Your account will be less secure.');">
            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
            <button type="submit" class="btn btn-danger">Disable MFA</button>
        </form>
    <?php else: ?>
        <p class="muted">Add an extra layer of security to your account.</p>
        <a href="/settings/mfa/setup" class="btn btn-primary">Enable MFA</a>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Audit log</h2>
    <p class="muted">Review all changes made to your tenant — API key updates, user logins, chatbot changes, and more.</p>
    <a href="/audit" class="btn btn-primary">View audit log</a>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
