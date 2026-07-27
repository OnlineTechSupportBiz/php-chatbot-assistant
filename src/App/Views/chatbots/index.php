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

ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Chatbots</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1 class="h3 mb-0">Chatbots</h1>
        <a href="/chatbots/create" class="btn btn-primary">+ New Chatbot</a>
    </div>
    <p class="text-muted mb-3">Create and manage your AI chatbots. Each chatbot gets its own widget token, configuration settings, and knowledge base.</p>

    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($chatbots)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3">You haven't created any chatbots yet.</p>
                <a href="/chatbots/create" class="btn btn-primary">Create Your First Chatbot</a>
            </div>
        </div>
    <?php else: ?>
        <!-- ── Desktop table (md+) ── -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Industry</th>
                        <th>Widget Token</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chatbots as $bot): ?>
                    <tr>
                        <td>
                            <a href="/chatbots/<?= (int)$bot['id'] ?>" class="text-decoration-none fw-medium">
                                <?= htmlspecialchars($bot['name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php $botStatus = $bot['status'] ?? 'active'; ?>
                            <span class="badge <?= $botStatus === 'active' ? 'bg-success' : ($botStatus === 'paused' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= htmlspecialchars(ucfirst($botStatus)) ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= htmlspecialchars(ucfirst($bot['industry'] ?? '—')) ?></td>
                        <td class="d-none d-lg-table-cell"><code><?= htmlspecialchars($bot['widget_token'] ?? '—') ?></code></td>
                        <td class="d-none d-lg-table-cell"><?= dt($bot['created_at'] ?? '', 'M j, Y g:i A') ?></td>
                        <td class="text-end">
                            <a href="/chatbots/<?= (int)$bot['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="/chatbots/<?= (int)$bot['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <button type="button" class="btn btn-sm btn-outline-secondary clone-btn"
                                    data-bot-id="<?= (int)$bot['id'] ?>"
                                    data-bot-name="<?= htmlspecialchars($bot['name'], ENT_QUOTES) ?>">
                                Clone
                            </button>
                            <form method="POST" action="/chatbots/<?= (int)$bot['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this chatbot and all its data (messages, documents, conversations, leads)? This cannot be undone.');">
                                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Mobile card list (sm-) ── -->
        <div class="d-md-none">
            <?php foreach ($chatbots as $bot): ?>
            <?php $botStatus = $bot['status'] ?? 'active'; ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <a href="/chatbots/<?= (int)$bot['id'] ?>" class="text-decoration-none fw-semibold fs-5">
                            <?= htmlspecialchars($bot['name']) ?>
                        </a>
                        <span class="badge <?= $botStatus === 'active' ? 'bg-success' : ($botStatus === 'paused' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                            <?= htmlspecialchars(ucfirst($botStatus)) ?>
                        </span>
                    </div>
                    <div class="small text-muted mb-2">
                        <?= htmlspecialchars(ucfirst($bot['industry'] ?? '—')) ?>
                        &middot;
                        Created <?= dt($bot['created_at'] ?? '', 'M j, Y g:i A') ?>
                    </div>
                    <div class="small mb-2">
                        <span class="text-muted">Token:</span>
                        <code class="user-select-all" style="font-size:0.7rem;word-break:break-all;"><?= htmlspecialchars($bot['widget_token'] ?? '—') ?></code>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/chatbots/<?= (int)$bot['id'] ?>" class="btn btn-sm btn-outline-secondary flex-fill">View</a>
                        <a href="/chatbots/<?= (int)$bot['id'] ?>/edit" class="btn btn-sm btn-outline-secondary flex-fill">Edit</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill clone-btn"
                                data-bot-id="<?= (int)$bot['id'] ?>"
                                data-bot-name="<?= htmlspecialchars($bot['name'], ENT_QUOTES) ?>">
                            Clone
                        </button>
                        <form method="POST" action="/chatbots/<?= (int)$bot['id'] ?>/delete" class="flex-fill" onsubmit="return confirm('Delete this chatbot and all its data? This cannot be undone.');">
                            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                            <button class="btn btn-sm btn-outline-danger w-100">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();

// ── Clone modal ───────────────────────────────────────────────────────────
$cloneModal = <<<'MODAL'
<!-- Clone Chatbot Modal -->
<div class="modal fade" id="cloneModal" tabindex="-1" aria-labelledby="cloneModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="" id="cloneForm" class="modal-content">
      <input type="hidden" name="_csrf" value="MODAL_CSRF">
      <div class="modal-header">
        <h5 class="modal-title" id="cloneModalLabel">Clone Chatbot</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Create a copy of <strong id="cloneSourceName"></strong> with a new name and a fresh widget token.</p>
        <div class="mb-3">
          <label for="newName" class="form-label">New chatbot name</label>
          <input type="text" class="form-control" id="newName" name="new_name" required placeholder="e.g. Support Bot v2">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning">Clone Chatbot</button>
      </div>
    </form>
  </div>
</div>
MODAL;

// Inject the CSRF token into the modal
$cloneModal = str_replace(
    'MODAL_CSRF',
    htmlspecialchars(\App\Auth\Session::csrfToken(), ENT_QUOTES),
    $cloneModal
);

// Inject the modal HTML (right before </body> via pageScripts)
$pageScripts = ($pageScripts ?? '') . $cloneModal . <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
  var cloneBtns = document.querySelectorAll('.clone-btn');
  var cloneModal = document.getElementById('cloneModal');
  if (!cloneModal) return;
  var modal = new bootstrap.Modal(cloneModal);
  var form = document.getElementById('cloneForm');
  var nameSpan = document.getElementById('cloneSourceName');
  var nameInput = document.getElementById('newName');

  cloneBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var botId = btn.getAttribute('data-bot-id');
      var botName = btn.getAttribute('data-bot-name');
      nameSpan.textContent = botName;
      nameInput.value = botName + ' (copy)';
      form.action = '/chatbots/' + botId + '/clone';
      modal.show();
    });
  });
});
</script>
JS;

require __DIR__ . '/../dashboard/layout.php';
