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
 * Documents list view.
 *
 * @var array $chatbot   The chatbot this document set belongs to
 * @var array $documents List of document records
 * @var array $user      Authenticated user
 */
$pageTitle = 'Documents — ' . htmlspecialchars($chatbot['name'] ?? '') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="/chatbots">Chatbots</a></li>
                    <li class="breadcrumb-item"><a href="/chatbots/<?= (int)$chatbot['id'] ?>"><?= htmlspecialchars($chatbot['name']) ?></a></li>
                    <li class="breadcrumb-item active">Documents</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            + Upload Document
        </button>
    </div>

    <p class="text-muted small mb-3">Upload knowledge documents to train your chatbot — it learns from these files to answer questions about their content. Supports PDF, DOCX, TXT, CSV, MD, and HTML.</p>

    <?php
    $strategy = $chatbot['retrieval_strategy'] ?? 'traditional_rag';
    if ($strategy === 'page_index'): ?>
    <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" role="alert">
        <span style="font-size:1.1rem;">📑</span>
        <span><strong>PageIndex</strong> — documents will be parsed into a section tree. No embeddings or vector search needed.</span>
    </div>
    <?php else: ?>
    <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" role="alert">
        <span style="font-size:1.1rem;">🧩</span>
        <span><strong>Traditional RAG</strong> — documents will be chunked and embedded for vector similarity search.</span>
    </div>
    <?php endif; ?>

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

    <?php if (empty($documents)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3">No documents uploaded yet for this chatbot.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    Upload First Document
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- ── Desktop table (md+) ── -->
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Chunks</th>
                        <th>Strategy</th>
                        <th>Uploaded</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <?php $fileSize = ($doc['file_size'] ?? 0) / 1024; ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['original_name'] ?? '') ?></td>
                        <td class="d-none d-lg-table-cell"><code><?= htmlspecialchars($doc['mime_type'] ?? '—') ?></code></td>
                        <td class="d-none d-lg-table-cell"><?= number_format($fileSize, 1) ?> KB</td>
                        <td>
                            <?php $docStatus = $doc['status'] ?? 'pending'; ?>
                            <span class="badge <?= $docStatus === 'indexed' ? 'bg-success' : ($docStatus === 'failed' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                <?= htmlspecialchars(ucfirst($docStatus)) ?>
                            </span>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= (int)($doc['chunk_count'] ?? 0) ?></td>
                        <td class="d-none d-lg-table-cell"><?php
                            $strat = $docStrategies[(int)$doc['id']] ?? null;
                            if ($strat === 'page_index'): ?><span class="badge bg-info strategy-badge" style="font-size:0.7rem;">PageIndex</span><?php
                            elseif ($strat === 'traditional_rag'): ?><span class="badge bg-secondary strategy-badge" style="font-size:0.7rem;">RAG</span><?php
                            else: ?><span class="text-muted" style="font-size:0.7rem;">—</span><?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= dt($doc['created_at'] ?? '', 'M j, Y g:i A') ?></td>
                        <td class="text-end">
                            <a href="/chatbots/<?= (int)$chatbot['id'] ?>/documents/<?= (int)$doc['id'] ?>" class="btn btn-sm btn-outline-primary">Status</a>
                            <form method="POST" action="/chatbots/<?= (int)$chatbot['id'] ?>/documents/<?= (int)$doc['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this document?');">
                                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Mobile card list (sm-) ── -->
        <div class="d-lg-none">
            <?php foreach ($documents as $doc): ?>
            <?php $fileSize = ($doc['file_size'] ?? 0) / 1024; ?>
            <?php $docStatus = $doc['status'] ?? 'pending'; ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="fw-semibold" style="word-break:break-word;"><?= htmlspecialchars($doc['original_name'] ?? '') ?></span>
                        <span class="badge <?= $docStatus === 'indexed' ? 'bg-success' : ($docStatus === 'failed' ? 'bg-danger' : 'bg-warning text-dark') ?> flex-shrink-0 ms-2">
                            <?= htmlspecialchars(ucfirst($docStatus)) ?>
                        </span>
                    </div>
                    <div class="small text-muted mb-2">
                        <code><?= htmlspecialchars($doc['mime_type'] ?? '—') ?></code>
                        &middot;
                        <?= number_format($fileSize, 1) ?> KB
                        &middot;
                        <?= (int)($doc['chunk_count'] ?? 0) ?> chunks
                        &middot;
                        <?php
                            $strat = $docStrategies[(int)$doc['id']] ?? null;
                            if ($strat === 'page_index'): ?>PageIndex<?php
                            elseif ($strat === 'traditional_rag'): ?>RAG<?php
                            else: ?>—<?php endif; ?>
                        &middot;
                        <?= dt($doc['created_at'] ?? '', 'M j, Y g:i A') ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/chatbots/<?= (int)$chatbot['id'] ?>/documents/<?= (int)$doc['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill">Status</a>
                        <form method="POST" action="/chatbots/<?= (int)$chatbot['id'] ?>/documents/<?= (int)$doc['id'] ?>/delete" class="flex-fill" onsubmit="return confirm('Delete this document?');">
                            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                            <button class="btn btn-sm btn-danger w-100" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="dialog" aria-labelledby="upload-modal-title" aria-modal="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="upload-modal-title">Upload Documents</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" aria-live="polite" aria-atomic="true">
                <div class="drop-zone p-5 text-center border border-2 border-dashed rounded-4 mb-3" id="dropZone"
                     style="cursor:pointer;position:relative;border-color:var(--border-color);transition:var(--transition);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-muted);">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p class="mt-2 mb-0 text-muted">Drag & drop files here, or click to select</p>
                    <small class="text-muted">PDF, DOCX, TXT, CSV, MD, HTML — 25 MB max per file</small>
                    <input type="file" id="fileInput" name="files[]" multiple
                           accept=".pdf,.docx,.txt,.csv,.md,.html"
                           style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:2"
                           title="Click to select files">
                </div>

                <!-- File queue -->
                <div id="fileQueue" class="mb-3"></div>

                <!-- Upload progress -->
                <div id="uploadProgress" class="mb-3" style="display:none;">
                    <div class="d-flex justify-content-between mb-1">
                        <span id="progressText">Uploading…</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar"
                             style="width:0%;background:var(--accent,#2563eb);"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php $pageContent = ob_get_clean();

$csrfTokenValue = \App\Auth\Session::csrfToken();
$botIdValue     = (int) $chatbot['id'];

$pageScripts = <<<HEREDOC
<script>
(function() {
    var dropZone    = document.getElementById('dropZone');
    var fileInput   = document.getElementById('fileInput');
    var fileQueue   = document.getElementById('fileQueue');
    var progressEl  = document.getElementById('uploadProgress');
    var progressBar = document.getElementById('progressBar');
    var progressText = document.getElementById('progressText');
    var progressPct = document.getElementById('progressPercent');
    var csrfToken   = '$csrfTokenValue';
    var botId       = $botIdValue;
    var files       = [];

    // ── Drop / click ────────────────────────────────────────────────────
    // fileInput is overlaid on the drop zone (opacity:0, position:absolute, inset:0)
    // so clicking anywhere on the drop zone naturally opens the file picker.
    // The change event handler adds files to the queue.
    fileInput.addEventListener('change', function() {
        addFiles(Array.from(this.files));
        this.value = '';
    });

    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--accent)';
        dropZone.style.background = 'rgba(0,212,255,0.05)';
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.style.borderColor = 'var(--border-color)';
        dropZone.style.background = 'transparent';
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--border-color)';
        dropZone.style.background = 'transparent';
        addFiles(Array.from(e.dataTransfer.files));
    });

    // ── Upload ──────────────────────────────────────────────────────────
    function addFiles(newFiles) {
        var maxSize = 25 * 1024 * 1024;
        newFiles.forEach(function(f) {
            if (f.size > maxSize) {
                addQueueItem(f.name, '❌ Too large (max 25 MB)');
                return;
            }
            // Check duplicate by name
            var dup = files.some(function(ex) { return ex.name === f.name && ex.size === f.size; });
            if (dup) {
                addQueueItem(f.name, '⚠️ Duplicate');
                return;
            }
            files.push(f);
            addQueueItem(f.name, formatSize(f.size));
        });
        // Auto-start upload when files are added
        startUpload();
    }

    function addQueueItem(name, status) {
        var div = document.createElement('div');
        div.className = 'd-flex justify-content-between align-items-center py-1 px-2 rounded mb-1';
        div.style.background = 'rgba(255,255,255,0.03)';
        div.innerHTML = '<span>' + escapeHtml(name) + '</span><small class="text-muted">' + status + '</small>';
        fileQueue.appendChild(div);
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ── Upload ──────────────────────────────────────────────────────────
    function startUpload() {
        if (typeof startUpload.locked !== 'undefined' && startUpload.locked) return;
        startUpload.locked = true;
        if (files.length === 0) { startUpload.locked = false; return; }

        progressEl.style.display = 'block';
        progressText.textContent = 'Uploading files…';

        var total = files.length;
        var done  = 0;
        var results = [];

        // Rebuild queue: keep rejected items, re-add files-to-upload
        fileQueue.innerHTML = '';
        files.forEach(function(f) {
            addQueueItem(f.name, '⬆️ Pending…');
        });

        function uploadNext(index) {
            if (index >= total) {
                startUpload.locked = false;
                progressText.textContent = 'All done!';
                progressBar.style.width = '100%';
                progressPct.textContent = '100%';

                // Show a readable summary below the progress bar
                var summary = document.createElement('div');
                summary.className = 'mt-2 small';
                var ok = results.filter(function(r) { return r.ok && r.indexed; }).length;
                var uploaded = results.filter(function(r) { return r.ok && !r.indexed; }).length;
                var dup = results.filter(function(r) { return r.dup; }).length;
                var err = results.filter(function(r) { return !r.ok && !r.dup; }).length;
                var parts = [];
                if (ok)  parts.push('✅ ' + ok + ' indexed');
                if (uploaded) parts.push('⬆️ ' + uploaded + ' uploaded (train failed)');
                if (dup) parts.push('⚠️ ' + dup + ' duplicate');
                if (err) parts.push('❌ ' + err + ' failed');
                summary.textContent = parts.join(' · ');
                progressEl.appendChild(summary);

                // Reload only when all files were fully processed (uploaded + indexed)
                if (err === 0 && dup === 0 && uploaded === 0) {
                    setTimeout(function() { location.reload(); }, 1200);
                }
                return;
            }

            var f = files[index];
            var fd = new FormData();
            fd.append('document', f);
            fd.append('_csrf', csrfToken);

            // Update current queue item
            var items = fileQueue.querySelectorAll('div');
            if (items[index]) {
                items[index].querySelector('small').textContent = '⬆️ Uploading…';
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/chatbots/' + botId + '/documents/store', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var pct = Math.round((done + e.loaded / e.total) / total * 100);
                    progressBar.style.width = pct + '%';
                    progressPct.textContent = pct + '%';
                }
            });

            xhr.addEventListener('load', function() {
                done++;
                var pct = Math.round(done / total * 100);
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';

                var resp;
                try { resp = JSON.parse(xhr.responseText); } catch(e) { resp = {}; }

                var items = fileQueue.querySelectorAll('div');
                var docId = (xhr.status === 200 || xhr.status === 201) ? (resp.id || null) : null;

                if (docId) {
                    // Auto-train immediately after successful upload
                    if (items[index]) {
                        items[index].querySelector('small').textContent = '🧠 Training…';
                    }
                    progressText.textContent = 'Training ' + f.name + '…';

                    var trainXhr = new XMLHttpRequest();
                    trainXhr.open('POST', '/chatbots/' + botId + '/documents/' + docId + '/train', true);
                    trainXhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    trainXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    trainXhr.addEventListener('load', function() {
                        var trainResp;
                        try { trainResp = JSON.parse(trainXhr.responseText); } catch(e) { trainResp = {}; }
                        if (trainXhr.status === 200 && trainResp.ok) {
                            if (items[index]) {
                                items[index].querySelector('small').textContent = '✅ Indexed';
                            }
                            results.push({ok: true, dup: false, name: f.name, indexed: true, chunks: trainResp.chunks || 0});
                        } else {
                            var trainErr = trainResp.error || 'Training failed';
                            if (items[index]) {
                                items[index].querySelector('small').textContent = '✅ Uploaded ⚠️ ' + trainErr;
                            }
                            results.push({ok: true, dup: false, name: f.name, indexed: false, error: trainErr});
                        }
                        progressText.textContent = 'Uploaded ' + (done) + '/' + total + ' files';
                        uploadNext(index + 1);
                    });
                    trainXhr.addEventListener('error', function() {
                        if (items[index]) {
                            items[index].querySelector('small').textContent = '✅ Uploaded ⚠️ Train network error';
                        }
                        results.push({ok: true, dup: false, name: f.name, indexed: false, error: 'Training network error'});
                        progressText.textContent = 'Uploaded ' + (done) + '/' + total + ' files';
                        uploadNext(index + 1);
                    });
                    trainXhr.send('_csrf=' + encodeURIComponent(csrfToken));
                } else {
                    var errMsg = resp.error || 'Upload failed';
                    var isDup = errMsg.toLowerCase().indexOf('already been uploaded') !== -1;
                    if (items[index]) {
                        items[index].querySelector('small').textContent = isDup ? '⚠️ Duplicate' : '❌ ' + errMsg;
                    }
                    results.push({ok: false, dup: isDup, name: f.name, error: errMsg});
                    uploadNext(index + 1);
                }
            });

            xhr.addEventListener('error', function() {
                done++;
                var items = fileQueue.querySelectorAll('div');
                if (items[index]) {
                    items[index].querySelector('small').textContent = '❌ Network error';
                }
                results.push({ok: false, dup: false, name: f.name, error: 'Network error'});
                uploadNext(index + 1);
            });

            xhr.send(fd);
        }

        uploadNext(0);
    }

    // Reset on modal close
    document.getElementById('uploadModal').addEventListener('hidden.bs.modal', function() {
        startUpload.locked = false;
        files = [];
        fileQueue.innerHTML = '';
        progressEl.style.display = 'none';
        progressBar.style.width = '0%';
        progressPct.textContent = '0%';
    });
})();
</script>
HEREDOC;

require __DIR__ . '/../dashboard/layout.php';
