<?php
$title = 'Specimens';
ob_start();
?>

<h1>Specimen Catalog</h1>
<p class="subtitle">Browse the archived botanical specimens</p>

<form method="GET" action="/specimens" class="search-bar">
    <input type="text" name="search" placeholder="Search by name, species, or family..."
           value="<?= htmlspecialchars($search ?? '') ?>">
    <select name="status" style="padding:0.6rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
        <option value="">All Statuses</option>
        <option value="published" <?= ($status_filter ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= ($status_filter ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="archived" <?= ($status_filter ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if (!empty($search) || !empty($status_filter)): ?>
        <a href="/specimens" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($specimens)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No specimens found. <?= !empty($search) ? 'Try a different search term.' : 'The archive is empty.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Common Name</th>
                    <th>Species</th>
                    <th>Family</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specimens as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td>
                        <a href="/specimens/<?= (int)$s['id'] ?>">
                            <strong><?= htmlspecialchars($s['common_name']) ?></strong>
                        </a>
                    </td>
                    <td><em><?= htmlspecialchars($s['species'] ?? '-') ?></em></td>
                    <td><?= htmlspecialchars($s['family'] ?? '-') ?></td>
                    <td>
                        <span class="badge badge-<?= ($s['status'] ?? 'published') === 'published' ? 'published' : (($s['status'] ?? '') === 'draft' ? 'draft' : 'archived') ?>">
                            <?= htmlspecialchars($s['status'] ?? 'published') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($s['location_found'] ?? '-') ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($s['source'] ?? '-') ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
            <?php
            $qs = $search ? "&search=" . urlencode($search) : '';
            if ($i == $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="/specimens?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
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
