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
 * Quick answers — list view with drag-to-reorder.
 *
 * @var array $chatbot      The chatbot
 * @var array $quickAnswers List of quick answer records
 * @var array $user         Authenticated user
 */
$pageTitle = 'Quick Answers — ' . htmlspecialchars($chatbot['name'] ?? '') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= (int)$chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
            <li class="breadcrumb-item active">Quick Answers</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Quick Answers</h1>
        <a href="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/create" class="btn btn-primary">+ New Quick Answer</a>
    </div>

    <p class="text-muted small mb-3">Define preset question-and-answer pairs — when a user's message matches a trigger phrase, the chatbot responds with your pre-written answer instead of generating one from its knowledge documents. Drag rows to reorder priority (top = highest priority).</p>

    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($quickAnswers)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3">No quick answers defined yet.</p>
                <a href="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/create" class="btn btn-primary">Create First Quick Answer</a>
            </div>
        </div>
    <?php else: ?>
        <form id="reorderForm" method="POST" action="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/reorder">
            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
            <input type="hidden" name="ids" id="orderedIds" value="">

            <!-- ── Desktop table (md+) ── -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-dark table-hover align-middle" id="qaTable">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Trigger</th>
                            <th>Answer Preview</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="qaTbody">
                        <?php foreach ($quickAnswers as $qa): ?>
                        <tr data-id="<?= (int)$qa['id'] ?>" draggable="true">
                            <td class="drag-handle text-center" style="cursor:grab;font-size:1.1rem;user-select:none;" title="Drag to reorder">⠿</td>
                            <td><code><?= htmlspecialchars($qa['trigger'] ?? '') ?></code></td>
                            <td><small><?= htmlspecialchars(mb_substr($qa['answer'] ?? '', 0, 80)) ?></small></td>
                            <td>
                                <?php if ($qa['is_active'] ?? 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/<?= (int)$qa['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/<?= (int)$qa['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this quick answer?');">
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
            <div class="d-md-none" id="qaMobileList">
                <?php foreach ($quickAnswers as $qa): ?>
                <div class="card mb-3" data-id="<?= (int)$qa['id'] ?>" draggable="true">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="drag-handle" style="cursor:grab;font-size:1.1rem;user-select:none;" title="Drag to reorder">⠿</span>
                                <code class="fw-semibold"><?= htmlspecialchars($qa['trigger'] ?? '') ?></code>
                            </div>
                            <span class="badge <?= ($qa['is_active'] ?? 1) ? 'bg-success' : 'bg-secondary' ?> flex-shrink-0 ms-2">
                                <?= ($qa['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <p class="small mb-2 text-muted" style="word-break:break-word;">
                            <?= htmlspecialchars(mb_substr($qa['answer'] ?? '', 0, 120)) ?>
                        </p>
                        <div class="d-flex gap-2">
                            <a href="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/<?= (int)$qa['id'] ?>/edit" class="btn btn-sm btn-outline-secondary flex-fill">Edit</a>
                            <form method="POST" action="/chatbots/<?= (int)$chatbot['id'] ?>/quick-answers/<?= (int)$qa['id'] ?>/delete" class="flex-fill" onsubmit="return confirm('Delete this quick answer?');">
                                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                <button class="btn btn-sm btn-outline-danger w-100">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-primary" id="saveOrderBtn" onclick="saveOrder()" disabled>Save Order</button>
                <span class="text-muted small align-self-center" id="orderChangedHint" style="display:none;">Order changed — click Save to apply</span>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function() {
    let dragSrcEl = null;

    function handleDragStart(e) {
        dragSrcEl = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.id);
        this.classList.add('dragging');
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const container = this.closest('#qaTbody') || this.closest('#qaMobileList');
        const items = container === this.closest('#qaTbody')
            ? [...container.querySelectorAll('tr[data-id]')]
            : [...container.querySelectorAll('.card[data-id]')];
        const afterElement = getDragAfterElement(container, e.clientY, items);
        if (afterElement == null) {
            container.appendChild(dragSrcEl);
        } else {
            container.insertBefore(dragSrcEl, afterElement);
        }
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
        markOrderChanged();
    }

    function handleDragEnter(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    function getDragAfterElement(container, y, items) {
        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function markOrderChanged() {
        document.getElementById('saveOrderBtn').disabled = false;
        document.getElementById('orderChangedHint').style.display = '';
    }

    window.saveOrder = function() {
        const tbody = document.getElementById('qaTbody');
        const mobileList = document.getElementById('qaMobileList');
        const ids = [];

        // Collect IDs in visual order from desktop table (primary source)
        tbody.querySelectorAll('tr[data-id]').forEach(el => ids.push(el.dataset.id));

        document.getElementById('orderedIds').value = JSON.stringify(ids);
        document.getElementById('saveOrderBtn').disabled = true;
        document.getElementById('saveOrderBtn').textContent = 'Saving...';
        document.getElementById('reorderForm').submit();
    };

    // Attach drag handlers to all draggable rows/cards
    document.addEventListener('DOMContentLoaded', function() {
        const draggables = document.querySelectorAll('[draggable="true"]');
        draggables.forEach(el => {
            el.addEventListener('dragstart', handleDragStart);
            el.addEventListener('dragover', handleDragOver);
            el.addEventListener('dragend', handleDragEnd);
            el.addEventListener('dragenter', handleDragEnter);
            el.addEventListener('dragleave', handleDragLeave);

            // Make the drag-handle the only way to start a drag (more intentional)
            const handle = el.querySelector('.drag-handle');
            if (handle) {
                handle.addEventListener('mousedown', function() {
                    el.draggable = true;
                });
            }
            el.addEventListener('dragstart', function() {
                el.draggable = true;
            });
        });

        // Enable dragging by grabbing the handle
        document.querySelectorAll('.drag-handle').forEach(h => {
            h.addEventListener('mousedown', function(e) {
                const row = this.closest('[data-id]');
                if (row) row.draggable = true;
            });
        });
        // Disable drag when mouse leaves the handle area
        document.addEventListener('mouseup', function() {
            document.querySelectorAll('[draggable="true"]').forEach(el => {
                // Keep draggable for dragstart to work, HTML5 needs it set
            });
        });
    });
})();
</script>

<style>
    .dragging {
        opacity: 0.4;
    }
    .drag-over {
        border-top: 2px solid #0d6efd !important;
    }
    tr.drag-over td {
        border-top: 2px solid #0d6efd;
    }
    .drag-handle:hover {
        cursor: grab;
    }
    .drag-handle:active {
        cursor: grabbing;
    }
</style>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
