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
 * Admin audit log history view (paginated, filterable).
 *
 * @var array  $result    ['rows' => [...], 'total' => N, 'page' => N]
 * @var array  $user      Authenticated user
 * @var array  $userNames Maps user_id => name
 * @var array  $filters   ['actions' => [...], 'entities' => [...]]
 * @var int    $perPage   Items per page
 * @var string $action    Currently filtered action
 * @var string $entity    Currently filtered entity_type
 */

$result ??= ['rows' => [], 'total' => 0, 'page' => 1];
$userNames ??= [];
$filters ??= ['actions' => [], 'entities' => []];
$currentAction = htmlspecialchars($action);
$currentEntity = htmlspecialchars($entity);
$perPage = max(1, $perPage ?? 50);

$totalPages = max(1, (int) ceil($result['total'] / $perPage));
$currentPage = $result['page'];
$prevPage = max(1, $currentPage - 1);
$nextPage = min($totalPages, $currentPage + 1);
?><div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Audit Logs</h1>
            <p class="text-muted mb-0">
                <?= number_format($result['total']) ?> event<?= $result['total'] !== 1 ? 's' : '' ?> across all tenants
            </p>
        </div>
        <div>
            <a href="/admin" class="btn btn-outline-secondary">&larr; Back to Dashboard</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="/admin/audit-log" class="row gy-2 gx-3 align-items-end">
                <div class="col-auto">
                    <label for="action-filter" class="form-label small mb-1">Action</label>
                    <select name="action" id="action-filter" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($filters['actions'] as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"<?= $a === $currentAction ? ' selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a))) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="entity-filter" class="form-label small mb-1">Entity Type</label>
                    <select name="entity" id="entity-filter" class="form-select form-select-sm">
                        <option value="">All Entities</option>
                        <?php foreach ($filters['entities'] as $e): ?>
                        <option value="<?= htmlspecialchars($e) ?>"<?= $e === $currentEntity ? ' selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $e))) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="/admin/audit-log" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-3">
        <input type="search" id="audit-log-search" class="form-control form-control-sm" style="max-width:320px;" placeholder="Type to filter rows&hellip;">
    </div>

    <!-- Table -->
    <?php if (empty($result['rows'])): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <p class="text-muted mb-0">No audit log entries found<?= $currentAction || $currentEntity ? ' matching the selected filters' : '' ?>.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="audit-log-table">
                    <thead>
                        <tr>
                            <th style="width:160px;">Timestamp</th>
                            <th style="width:140px;">User</th>
                            <th>Action</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th style="width:130px;" class="d-none d-lg-table-cell">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $row): ?>
                        <?php
                            $oldVal = $row['old_value'] !== null ? json_decode($row['old_value'], true) : null;
                            $newVal = $row['new_value'] !== null ? json_decode($row['new_value'], true) : null;
                            $userName = $userNames[(int) $row['user_id']] ?? 'System';
                        ?>
                        <tr>
                            <td class="small text-nowrap">
                                <?= dt($row['created_at'], 'M j, Y') ?>
                                <br><span class="text-muted"><?= dt($row['created_at'], 'g:i A') ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($userName) ?></td>
                            <td>
                                <span class="badge bg-<?php
                                    $actionColor = match ($row['action']) {
                                        'create'          => 'success',
                                        'update', 'update_api_keys' => 'warning',
                                        'delete'          => 'danger',
                                        'login', 'logout' => 'info',
                                        'change_password' => 'warning',
                                        default           => 'secondary',
                                    };
                                    echo $actionColor;
                                ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['action']))) ?></span>
                            </td>
                            <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php if ($oldVal): ?>
                                <span class="text-danger" title="<?= htmlspecialchars(json_encode($oldVal, JSON_UNESCAPED_SLASHES)) ?>">
                                    <?= htmlspecialchars(mb_substr(json_encode($oldVal, JSON_UNESCAPED_SLASHES), 0, 60)) ?>&hellip;
                                </span>
                                <?php else: ?>
                                <span class="text-muted fst-italic">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php if ($newVal): ?>
                                <span class="text-success" title="<?= htmlspecialchars(json_encode($newVal, JSON_UNESCAPED_SLASHES)) ?>">
                                    <?= htmlspecialchars(mb_substr(json_encode($newVal, JSON_UNESCAPED_SLASHES), 0, 60)) ?>&hellip;
                                </span>
                                <?php else: ?>
                                <span class="text-muted fst-italic">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted d-none d-lg-table-cell">
                                <code class="small"><?= htmlspecialchars($row['ip_address'] ?? '&mdash;') ?></code>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Audit log pagination" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=1<?= $currentAction ? '&action=' . urlencode($currentAction) : '' ?><?= $currentEntity ? '&entity=' . urlencode($currentEntity) : '' ?>">&laquo; First</a>
            </li>
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $prevPage ?><?= $currentAction ? '&action=' . urlencode($currentAction) : '' ?><?= $currentEntity ? '&entity=' . urlencode($currentEntity) : '' ?>">&lsaquo; Prev</a>
            </li>

            <?php
            // Show up to 7 page buttons centered around current
            $startPage = max(1, $currentPage - 3);
            $endPage   = min($totalPages, $startPage + 6);
            if ($endPage - $startPage < 6) {
                $startPage = max(1, $endPage - 6);
            }
            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $currentAction ? '&action=' . urlencode($currentAction) : '' ?><?= $currentEntity ? '&entity=' . urlencode($currentEntity) : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>

            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $nextPage ?><?= $currentAction ? '&action=' . urlencode($currentAction) : '' ?><?= $currentEntity ? '&entity=' . urlencode($currentEntity) : '' ?>">Next &rsaquo;</a>
            </li>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $totalPages ?><?= $currentAction ? '&action=' . urlencode($currentAction) : '' ?><?= $currentEntity ? '&entity=' . urlencode($currentEntity) : '' ?>">Last &raquo;</a>
            </li>
        </ul>
    </nav>
    <p class="text-center text-muted small mt-2">
        Page <?= $currentPage ?> of <?= $totalPages ?> &middot; <?= number_format($result['total']) ?> total events
    </p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('audit-log-search');
    if (!searchInput) return;
    var table = document.getElementById('audit-log-table');
    if (!table) return;
    searchInput.addEventListener('keyup', function() {
        var query = this.value.toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().indexOf(query) > -1 ? '' : 'none';
        });
    });
});
</script>
