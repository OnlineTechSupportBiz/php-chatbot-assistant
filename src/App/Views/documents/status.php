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
 * Document status view.
 *
 * Available variables:
 *   $chatbot    — array with chatbot data
 *   $document   — array with document record
 *   $chunkCount — int, count of chunks for this document
 *   $user       — authenticated user array
 */
$chatbotName = htmlspecialchars($chatbot['name'] ?? '');
$docName     = htmlspecialchars($document['original_name'] ?? '');
$status      = htmlspecialchars($document['status'] ?? 'unknown');
$statusClass = match($document['status'] ?? '') {
    'indexed' => 'bg-success',
    'failed'  => 'bg-danger',
    default   => 'bg-warning text-dark',
};

$pageTitle = 'Document Status — ' . $docName . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <div class="mb-3">
        <a href="/chatbots/<?= (int)$chatbot['id'] ?>/documents" class="btn btn-outline-light btn-sm">← Back to Documents</a>
    </div>

    <h1 class="mb-4">Document Details</h1>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <strong><?= $docName ?></strong>
                    <span class="badge <?= $statusClass ?> ms-2"><?= $status ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">File Name</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($document['filename'] ?? '') ?></dd>

                        <dt class="col-sm-3">MIME Type</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($document['mime_type'] ?? '') ?></dd>

                        <dt class="col-sm-3">Size</dt>
                        <dd class="col-sm-9"><?= number_format(($document['file_size'] ?? 0) / 1024, 1) ?> KB</dd>

                        <dt class="col-sm-3">Chunks</dt>
                        <dd class="col-sm-9"><?= (int)$chunkCount ?></dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9"><?= dt($document['created_at'] ?? '', 'M j, Y g:i A') ?></dd>

                        <dt class="col-sm-3">Updated</dt>
                        <dd class="col-sm-9"><?= dt($document['updated_at'] ?? '', 'M j, Y g:i A') ?></dd>

                        <?php if ($document['error_message']): ?>
                        <dt class="col-sm-3">Error</dt>
                        <dd class="col-sm-9 text-danger"><?= htmlspecialchars($document['error_message']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../dashboard/layout.php';
