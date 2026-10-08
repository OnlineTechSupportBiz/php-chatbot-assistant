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
 * Chatbot list view.
 *
 * @var array $user       Authenticated user
 * @var array $chatbots   List of chatbot records
 */
$pageTitle = 'Chatbots - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
// Submitted values from a failed create, so the inline form keeps the input.
$old = \App\Auth\Session::getFlash('old') ?? [];

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Chatbots</h1>
        <p class="subtitle">Create and manage your customer-facing assistants.</p>
    </div>
    <button type="button" class="btn btn-primary" id="newChatbotBtn">New chatbot</button>
</div>

<div class="card" id="newChatbotCard" hidden>
    <h2>New chatbot</h2>
    <?php foreach ((\App\Auth\Session::getFlash('errors') ?? []) as $err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>
    <form method="POST" action="/chatbots">
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
        <div class="field">
            <label class="label" for="name">Name</label>
            <input class="input" id="name" name="name" required placeholder="Support Bot"
                   value="<?= htmlspecialchars($old['name'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="industry">Industry</label>
            <input class="input" id="industry" name="industry" placeholder="e.g. SaaS, healthcare, retail"
                   value="<?= htmlspecialchars($old['industry'] ?? '') ?>">
        </div>
        <div class="field">
            <label class="label" for="system_prompt">System prompt</label>
            <textarea class="textarea" id="system_prompt" name="system_prompt"
                      placeholder="Instructions for how the assistant should behave"><?= htmlspecialchars($old['system_prompt'] ?? '') ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create</button>
            <button type="button" class="btn" id="cancelNewChatbot">Cancel</button>
        </div>
    </form>
</div>

<?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
    <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($chatbots)): ?>
    <div class="empty">No chatbots yet. Create your first one to get started.</div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Industry</th>
                    <th>Status</th>
                    <th>Widget token</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatbots as $bot): ?>
                    <?php
                        $botStatus = $bot['status'] ?? 'active';
                        $statusClass = $botStatus === 'active' ? 'badge-success' : ($botStatus === 'paused' ? 'badge-warning' : '');
                    ?>
                    <tr>
                        <td>
                            <a href="/chatbots/<?= (int) $bot['id'] ?>"><?= htmlspecialchars($bot['name']) ?></a>
                        </td>
                        <td class="muted"><?= htmlspecialchars(ucfirst($bot['industry'] ?? '—')) ?></td>
                        <td>
                            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($botStatus)) ?></span>
                        </td>
                        <td><span class="mono"><?= htmlspecialchars($bot['widget_token'] ?? '—') ?></span></td>
                        <td class="muted"><?= dt($bot['created_at'] ?? '', 'M j, Y') ?></td>
                        <td>
                            <div class="row">
                                <button type="button" class="btn btn-sm clone-btn"
                                        data-bot-id="<?= (int) $bot['id'] ?>"
                                        data-bot-name="<?= htmlspecialchars($bot['name'], ENT_QUOTES) ?>">
                                    Clone
                                </button>
                                <form method="POST" action="/chatbots/<?= (int) $bot['id'] ?>/delete"
                                      onsubmit="return confirm('Delete this chatbot and all its data (messages, documents, conversations, leads)? This cannot be undone.');">
                                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Reused by the inline clone handler below: window.prompt supplies the new
         name, then the form POSTs it to /chatbots/{id}/clone. -->
    <form method="POST" action="" id="cloneForm" hidden>
        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
        <input type="hidden" name="new_name" id="cloneNewName" value="">
    </form>
<?php endif; ?>
<?php
$pageContent = ob_get_clean();

$pageScripts = ($pageScripts ?? '') . <<<'JS'
<script>
(function () {
    var btn = document.getElementById('newChatbotBtn');
    var card = document.getElementById('newChatbotCard');
    var cancel = document.getElementById('cancelNewChatbot');
    if (!btn || !card) return;
    function open() {
        card.hidden = false;
        btn.hidden = true;
        var first = card.querySelector('input');
        if (first) first.focus();
    }
    btn.addEventListener('click', open);
    // A failed create redirects back with ?new=1 — reopen the form.
    if (new URLSearchParams(location.search).get('new') === '1' || card.querySelector('.alert-error')) {
        open();
    }
    if (cancel) cancel.addEventListener('click', function () {
        card.hidden = true;
        btn.hidden = false;
    });
})();
</script>
JS;

$pageScripts .= <<<'JS'
<script>
document.querySelectorAll('.clone-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var name = window.prompt('Name for the cloned chatbot', btn.getAttribute('data-bot-name') + ' copy');
        if (!name) return;
        var form = document.getElementById('cloneForm');
        if (!form) return;
        form.action = '/chatbots/' + btn.getAttribute('data-bot-id') + '/clone';
        document.getElementById('cloneNewName').value = name;
        form.submit();
    });
});
</script>
JS;

require __DIR__ . '/../dashboard/layout.php';
