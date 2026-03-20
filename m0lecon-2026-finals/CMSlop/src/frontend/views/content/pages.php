<?php
$title = 'Pages';
ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <div>
        <h1>Pages</h1>
        <p class="subtitle">Manage CMS pages</p>
    </div>
    <a href="/pages/new" class="btn btn-primary">New Page</a>
</div>

<form method="GET" action="/pages" class="search-bar" style="margin-bottom:1rem;">
    <input type="text" name="search" placeholder="Search pages..."
           value="<?= htmlspecialchars($search ?? '') ?>">
    <select name="status" style="padding:0.6rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
        <option value="">All Statuses</option>
        <option value="draft" <?= ($status_filter ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= ($status_filter ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="archived" <?= ($status_filter ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if (!empty($search) || !empty($status_filter)): ?>
        <a href="/pages" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($pages)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No pages found. <a href="/pages/new">Create your first page.</a>
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $pg): ?>
                <tr>
                    <td>
                        <a href="/pages/<?= (int)$pg['id'] ?>/edit">
                            <strong><?= htmlspecialchars($pg['title']) ?></strong>
                        </a>
                    </td>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($pg['slug'] ?? '') ?></td>
                    <td>
                        <span class="badge badge-<?= $pg['status'] === 'published' ? 'published' : ($pg['status'] === 'draft' ? 'draft' : 'archived') ?>">
                            <?= htmlspecialchars($pg['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($pg['author_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($pg['updated_at'] ?? $pg['created_at'] ?? '-') ?></td>
                    <td>
                        <a href="/pages/<?= (int)$pg['id'] ?>/edit" class="btn btn-sm btn-secondary">Edit</a>
                        <a href="/pages/<?= (int)$pg['id'] ?>/revisions" class="btn btn-sm btn-secondary">History</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
            <?php
            $qs = '';
            if (!empty($search)) $qs .= '&search=' . urlencode($search);
            if (!empty($status_filter)) $qs .= '&status=' . urlencode($status_filter);
            if ($i == $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="/pages?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <span style="color:var(--text-muted);font-size:0.85rem;">
            (<?= $pagination['total'] ?> total)
        </span>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
