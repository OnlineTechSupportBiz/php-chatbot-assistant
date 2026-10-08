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
 * Documents list view (the Documents tab).
 *
 * Port of components/admin/documents-tab.tsx: the dropzone lives directly in the
 * page (no modal dialog) using the `.dropzone*` classes, uploads render into
 * an `.upload-list`, and the documents table is a `.card.card-flush`.
 *
 * @var array $chatbot       The chatbot this document set belongs to
 * @var array $documents     List of document records
 * @var array $docStrategies Map doc id => retrieval strategy (unused by this table)
 * @var array $user          Authenticated user
 */
$pageTitle = 'Documents — ' . htmlspecialchars($chatbot['name'] ?? '') . ' - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

ob_start(); ?>
<div class="page-head">
    <div>
        <h1>Documents</h1>
        <p class="subtitle">Upload knowledge documents to train your chatbot — it answers from their content.</p>
    </div>
</div>

<div class="tab-bar">
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>">Settings</a>
    <a class="tab tab-active" href="/chatbots/<?= (int) $chatbot['id'] ?>/documents" aria-current="page">Documents</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/quick-answers">Quick answers</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/leads">Leads</a>
    <a class="tab" href="/chatbots/<?= (int) $chatbot['id'] ?>/conversations">Conversations</a>
</div>

<?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
    <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>Upload documents</h2>
    </div>
    <div class="dropzone" id="dropZone" role="button" tabindex="0" aria-label="Upload documents">
        <div class="dropzone-label">Drag and drop files here</div>
        <div class="dropzone-hint">or click to browse — PDF, DOCX, TXT, CSV, MD, HTML. Up to 25 MB each.</div>
    </div>
    <input type="file" id="fileInput" name="files[]" multiple
           accept=".pdf,.docx,.txt,.csv,.md,.html" hidden>
    <ul class="upload-list" id="fileQueue"></ul>
</div>

<div class="card card-flush">
    <div class="card-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <h2>Documents</h2>
        <div style="display:flex; gap:6px;">
            <button type="button" class="btn btn-sm" id="reprocessAllBtn"
                    data-csrf="<?= \App\Auth\Session::csrfToken() ?>"
                    data-url="/chatbots/<?= (int) $chatbot['id'] ?>/documents/reprocess">Reprocess all</button>
            <button type="button" class="btn btn-sm btn-danger" id="clearStoreBtn"
                    data-csrf="<?= \App\Auth\Session::csrfToken() ?>"
                    data-url="/chatbots/<?= (int) $chatbot['id'] ?>/documents/clear-store">Delete store</button>
        </div>
    </div>
    <?php if (empty($documents)): ?>
        <p class="card-note muted">No documents uploaded yet for this chatbot.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Status</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $doc): ?>
                    <?php
                        $docStatus = $doc['status'] ?? 'pending';
                        $statusClass = match ($docStatus) {
                            'indexed' => 'badge-success',
                            'failed'  => 'badge-danger',
                            default   => 'badge-warning',
                        };
                        $fileSize = ($doc['file_size'] ?? 0) / 1024;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['original_name'] ?? '') ?></td>
                        <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($docStatus)) ?></span></td>
                        <td class="muted"><?= number_format($fileSize, 1) ?> KB</td>
                        <td class="muted"><?= dt($doc['created_at'] ?? '') ?></td>
                        <td style="text-align: right;">
                            <div class="row" style="justify-content: flex-end;">
                                <form method="POST" action="/chatbots/<?= (int) $chatbot['id'] ?>/documents/<?= (int) $doc['id'] ?>/delete"
                                      onsubmit="return confirm('Delete this document?');">
                                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php $pageContent = ob_get_clean();

$csrfTokenValue = \App\Auth\Session::csrfToken();
$botIdValue     = (int) $chatbot['id'];

$pageScripts = <<<HEREDOC
<script>
(function () {
    var dropZone    = document.getElementById('dropZone');
    var fileInput   = document.getElementById('fileInput');
    var fileQueue   = document.getElementById('fileQueue');
    var csrfToken   = '$csrfTokenValue';
    var botId       = $botIdValue;
    var queue       = [];
    var uploading   = false;

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Build one <li class="upload-item"> with name + progress + pct + status.
    function makeItem(name) {
        var li = document.createElement('li');
        li.className = 'upload-item';
        li.innerHTML =
            '<span class="upload-name">' + escapeHtml(name) + '</span>' +
            '<progress class="upload-progress" value="0" max="100"></progress>' +
            '<span class="upload-pct">0%</span>' +
            '<span class="upload-status badge"></span>';
        fileQueue.appendChild(li);
        return li;
    }

    function setProgress(li, pct) {
        li.querySelector('.upload-progress').value = pct;
        li.querySelector('.upload-pct').textContent = pct + '%';
    }

    function setStatus(li, cls, text) {
        var s = li.querySelector('.upload-status');
        s.className = 'upload-status badge' + (cls ? ' ' + cls : '');
        s.textContent = text;
    }

    function noBar(li) {
        li.querySelector('.upload-progress').hidden = true;
        li.querySelector('.upload-pct').textContent = '';
    }

    // ── Add to queue ─────────────────────────────────────────────────────
    function addFiles(fileList) {
        var maxSize = 25 * 1024 * 1024;
        fileList.forEach(function (f) {
            var li = makeItem(f.name);
            if (f.size > maxSize) {
                noBar(li);
                setStatus(li, 'badge-danger', 'Too large (max 25 MB)');
                return;
            }
            var dup = queue.some(function (q) { return q.file.name === f.name && q.file.size === f.size; });
            if (dup) {
                noBar(li);
                setStatus(li, 'badge-warning', 'Duplicate');
                return;
            }
            queue.push({ file: f, li: li, state: 'pending' });
        });
        processQueue();
    }

    // ── Sequential upload + train pipeline ──────────────────────────────
    function processQueue() {
        if (uploading) return;
        var next = null;
        for (var i = 0; i < queue.length; i++) {
            if (queue[i].state === 'pending') { next = queue[i]; break; }
        }
        if (!next) return;
        uploading = true;
        next.state = 'uploading';
        uploadOne(next);
    }

    function uploadOne(item) {
        var li = item.li;
        setStatus(li, '', 'Uploading…');

        var fd = new FormData();
        fd.append('document', item.file);
        fd.append('_csrf', csrfToken);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/chatbots/' + botId + '/documents/store', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) setProgress(li, Math.round(e.loaded / e.total * 100));
        });

        xhr.addEventListener('load', function () {
            var resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) { resp = {}; }
            var docId = (xhr.status === 200 || xhr.status === 201) ? (resp.id || null) : null;

            if (!docId) {
                var errMsg = resp.error || 'Upload failed';
                var isDup = errMsg.toLowerCase().indexOf('already been uploaded') !== -1;
                setStatus(li, isDup ? 'badge-warning' : 'badge-danger', isDup ? 'Duplicate' : errMsg);
                item.state = 'error';
                finish();
                return;
            }

            setProgress(li, 100);
            setStatus(li, '', 'Training…');
            train(item, docId);
        });

        xhr.addEventListener('error', function () {
            setStatus(li, 'badge-danger', 'Network error');
            item.state = 'error';
            finish();
        });

        xhr.send(fd);
    }

    function train(item, docId) {
        var li = item.li;
        var txhr = new XMLHttpRequest();
        txhr.open('POST', '/chatbots/' + botId + '/documents/' + docId + '/train', true);
        txhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        txhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        txhr.addEventListener('load', function () {
            var tr;
            try { tr = JSON.parse(txhr.responseText); } catch (e) { tr = {}; }
            if (txhr.status === 200 && tr.ok) {
                setStatus(li, 'badge-success', 'Indexed');
            } else {
                setStatus(li, 'badge-warning', 'Uploaded — train failed');
            }
            item.state = 'done';
            finish();
        });

        txhr.addEventListener('error', function () {
            setStatus(li, 'badge-warning', 'Uploaded — train error');
            item.state = 'done';
            finish();
        });

        txhr.send('_csrf=' + encodeURIComponent(csrfToken));
    }

    function finish() {
        uploading = false;
        var allSettled = queue.length > 0 && queue.every(function (q) {
            return q.state === 'done' || q.state === 'error';
        });
        if (allSettled) {
            var anyError = queue.some(function (q) { return q.state === 'error'; });
            if (!anyError) setTimeout(function () { location.reload(); }, 1200);
            return;
        }
        processQueue();
    }

    // ── Drop / click ─────────────────────────────────────────────────────
    dropZone.addEventListener('click', function () { fileInput.click(); });
    dropZone.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('dropzone-active');
    });
    dropZone.addEventListener('dragleave', function () {
        dropZone.classList.remove('dropzone-active');
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('dropzone-active');
        addFiles(Array.from(e.dataTransfer.files));
    });

    fileInput.addEventListener('change', function () {
        addFiles(Array.from(this.files));
        this.value = '';
    });
})();

// ── Reprocess all / Delete store ────────────────────────────────────────
document.getElementById('reprocessAllBtn')?.addEventListener('click', async function () {
    var btn = this, url = btn.dataset.url, csrf = btn.dataset.csrf;
    btn.disabled = true;
    try {
        var res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({_csrf: csrf}) });
        var body = await res.json();
        if (body.ok === false) { alert(body.error || 'Reprocess failed.'); }
        else { alert(body.message || ('Reprocessed ' + body.count + ' document(s).')); }
        location.reload();
    } catch (e) { alert('Reprocess failed.'); btn.disabled = false; }
});
document.getElementById('clearStoreBtn')?.addEventListener('click', async function () {
    if (!window.confirm('Delete the entire document store? Every stored file, index, and embedding for this chatbot will be permanently removed.')) return;
    var btn = this, url = btn.dataset.url, csrf = btn.dataset.csrf;
    btn.disabled = true;
    try {
        var res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({_csrf: csrf}) });
        var body = await res.json();
        if (body.ok === false) { alert(body.error || 'Delete failed.'); location.reload(); }
        else { location.reload(); }
    } catch (e) { alert('Delete failed.'); btn.disabled = false; }
});
</script>
HEREDOC;

require __DIR__ . '/../dashboard/layout.php';
