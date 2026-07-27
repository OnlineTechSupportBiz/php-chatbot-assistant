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
 * Quick answer create/edit form.
 *
 * @var array       $chatbot — the chatbot this scopes to
 * @var array|null  $answer  — existing quick answer row (null = create mode)
 */
$isEdit = $answer !== null;
$backUrl = "/chatbots/{$chatbot['id']}/quick-answers";
$actionUrl = $isEdit
    ? "/chatbots/{$chatbot['id']}/quick-answers/{$answer['id']}"
    : "/chatbots/{$chatbot['id']}/quick-answers";
$submitText = $isEdit ? 'Update Quick Answer' : 'Create Quick Answer';
$title = $isEdit ? 'Edit Quick Answer' : 'New Quick Answer';

$old = \App\Auth\Session::getFlash('old') ?: [];
$trigger   = $old['trigger'] ?? ($answer['trigger'] ?? '');
$answerText = $old['answer'] ?? ($answer['answer'] ?? '');
$isActive  = $answer['is_active'] ?? 1;

$pageTitle = $title . ' — ' . htmlspecialchars($chatbot['name']);

ob_start(); ?>
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
            <li class="breadcrumb-item"><a href="/chatbots/<?= $chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($backUrl) ?>">Quick Answers</a></li>
            <li class="breadcrumb-item active"><?= $title ?></li>
        </ol>
    </nav>

    <h1 class="h3 mb-3"><?= $title ?></h1>

    <?php if ($errors = \App\Auth\Session::getFlash('errors')): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= htmlspecialchars($actionUrl) ?>">
                <?php \App\Auth\Session::csrfField(); ?>

                <div class="mb-3">
                    <label for="trigger" class="form-label">Trigger Text</label>
                    <input type="text" class="form-control" id="trigger" name="trigger"
                           value="<?= htmlspecialchars($trigger) ?>" maxlength="255" required
                           placeholder="e.g., hours, pricing, refund policy">
                    <div class="form-text">When a user types exactly this, the quick answer fires.</div>
                </div>

                <div class="mb-3">
                    <label for="answer" class="form-label">Answer</label>
                    <textarea class="form-control" id="answer" name="answer" rows="5" required
                              placeholder="The canned response to show..."><?= htmlspecialchars($answerText) ?></textarea>
                    <div class="form-text">Markdown supported.</div>
                </div>

                <div class="row">
                    <?php if ($isEdit): ?>
                    <div class="col-md-4 mb-3">
                        <label for="is_active" class="form-label">Active</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" <?= $isActive ? 'selected' : '' ?>>Yes</option>
                            <option value="0" <?= !$isActive ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $submitText ?></button>
                    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
