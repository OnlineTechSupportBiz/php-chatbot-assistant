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
 * Quick answers — list view (the Quick answers tab).
 *
 * Port of components/admin/quick-answers-tab.tsx. The HTML5 drag-to-reorder JS
 * and "Save Order" form are gone; each row instead has ↑/↓ buttons, each a small
 * POST form that sends the full ordered id list (JSON) to the existing reorder
 * endpoint — QuickAnswer::reorder() assigns priority by position (0 = highest).
 *
 * @var array $chatbot      The chatbot
 * @var array $quickAnswers List of quick answer records
 * @var array $user         Authenticated user
 */
$pageTitle = 'Quick answers — ' . htmlspecialchars($chatbot['name'] ?? '') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

$qaIds = array_map(static fn(array $qa): int => (int) $qa['id'], $quickAnswers);
$qaCount = count($qaIds);

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Quick answers</h1>
        <p class="subtitle">Canned answers that fire on an exact trigger phrase, without an LLM call.</p>
    </div>
    <a href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/create" class="btn btn-primary">Add</a>
</div>

<div class="tab-bar">
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>">Overview</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/documents">Documents</a>
    <a class="tab tab-active" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers" aria-current="page">Quick answers</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/leads">Leads</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations">Conversations</a>
</div>

<?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
    <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (empty($quickAnswers)): ?>
    <div class="empty">No quick answers yet. They answer common questions without an LLM call. <a href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/create">Add the first one</a>.</div>
<?php else: ?>
    <div class="card card-flush">
        <table class="table">
            <thead>
                <tr>
                    <th>Trigger</th>
                    <th>Answer</th>
                    <th>Order</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quickAnswers as $i => $qa): ?>
                    <?php
                        // Full ordered id list with this row swapped one slot up / down.
                        $upIds = $qaIds;
                        if ($i > 0) {
                            [$upIds[$i - 1], $upIds[$i]] = [$upIds[$i], $upIds[$i - 1]];
                        }
                        $downIds = $qaIds;
                        if ($i < $qaCount - 1) {
                            [$downIds[$i + 1], $downIds[$i]] = [$downIds[$i], $downIds[$i + 1]];
                        }
                        $answerText = (string) ($qa['answer'] ?? '');
                    ?>
                    <tr>
                        <td><span class="mono"><?= htmlspecialchars($qa['trigger'] ?? '') ?></span></td>
                        <td class="muted"><?= htmlspecialchars(mb_substr($answerText, 0, 80)) ?><?= mb_strlen($answerText) > 80 ? '…' : '' ?></td>
                        <td>
                            <div class="row" style="gap: 0.25rem;">
                                <form method="POST" action="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/reorder">
                                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                    <input type="hidden" name="ids" value="<?= htmlspecialchars(json_encode($upIds)) ?>">
                                    <button type="submit" class="btn btn-sm" title="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                                </form>
                                <form method="POST" action="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/reorder">
                                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                    <input type="hidden" name="ids" value="<?= htmlspecialchars(json_encode($downIds)) ?>">
                                    <button type="submit" class="btn btn-sm" title="Move down" <?= $i === $qaCount - 1 ? 'disabled' : '' ?>>↓</button>
                                </form>
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <div class="row" style="justify-content: flex-end;">
                                <a class="btn btn-sm" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/<?= (int) $qa['id'] ?>/edit">Edit</a>
                                <form method="POST" action="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers/<?= (int) $qa['id'] ?>/delete"
                                      onsubmit="return confirm('Delete this quick answer?');">
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
<?php endif; ?>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
