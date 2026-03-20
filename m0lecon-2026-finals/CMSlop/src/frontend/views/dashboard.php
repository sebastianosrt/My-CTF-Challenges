<?php
$title = 'Dashboard';
ob_start();
?>

<h1>Dashboard</h1>
<p class="subtitle">Overview of the Herbarium CMS</p>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_specimens'] ?? 0) ?></div>
        <div class="stat-label">Specimens</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_families'] ?? 0) ?></div>
        <div class="stat-label">Families</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_pages'] ?? 0) ?></div>
        <div class="stat-label">Pages</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_tags'] ?? 0) ?></div>
        <div class="stat-label">Tags</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_genera'] ?? 0) ?></div>
        <div class="stat-label">Genera</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_imports'] ?? 0) ?></div>
        <div class="stat-label">Imports</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['total_media'] ?? 0) ?></div>
        <div class="stat-label">Media Files</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['pending_scheduled'] ?? 0) ?></div>
        <div class="stat-label">Scheduled</div>
    </div>
</div>

<div class="card">
    <h2>Quick Actions</h2>
    <div style="display:flex;gap:0.8rem;flex-wrap:wrap;">
        <a href="/pages/new" class="btn btn-primary">New Page</a>
        <a href="/tags" class="btn btn-secondary">Manage Tags</a>
        <a href="/specimens" class="btn btn-secondary">Browse Specimens</a>
        <a href="/media" class="btn btn-secondary">Upload Media</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
<a href="/admin/scheduling" class="btn btn-secondary">Scheduling</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($recent_pages)): ?>
<div class="card">
    <h2>Recent Pages</h2>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Author</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent_pages as $pg): ?>
            <tr>
                <td><a href="/pages/<?= (int)$pg['id'] ?>/edit"><strong><?= htmlspecialchars($pg['title']) ?></strong></a></td>
                <td><span class="badge badge-<?= $pg['status'] === 'published' ? 'published' : ($pg['status'] === 'draft' ? 'draft' : 'archived') ?>"><?= htmlspecialchars($pg['status']) ?></span></td>
                <td><?= htmlspecialchars($pg['author_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($pg['updated_at'] ?? $pg['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($stats['recent_imports'])): ?>
<div class="card">
    <h2>Recent Imports</h2>
    <table>
        <thead>
            <tr>
                <th>Source</th>
                <th>Detail</th>
                <th>Records</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['recent_imports'] as $imp): ?>
            <tr>
                <td><span class="badge badge-info"><?= htmlspecialchars($imp['source_type']) ?></span></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($imp['source_detail'] ?? '-') ?>
                </td>
                <td><?= (int)$imp['records_imported'] ?></td>
                <td>
                    <span class="badge badge-<?= $imp['status'] === 'success' ? 'success' : 'warning' ?>">
                        <?= htmlspecialchars($imp['status']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($imp['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
