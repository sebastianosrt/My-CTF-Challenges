<?php
$title = 'Media Library';
ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <div>
        <h1>Media Library</h1>
        <p class="subtitle">Upload and manage files</p>
    </div>
</div>

<div class="card">
    <h2>Upload File</h2>
    <form method="POST" action="/media/upload" enctype="multipart/form-data"
          style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
            <label>File</label>
            <input type="file" name="file" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>

<form method="GET" action="/media" class="search-bar" style="margin-bottom:1rem;">
    <select name="mime" style="padding:0.6rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;">
        <option value="">All Types</option>
        <option value="image/" <?= ($mime_filter ?? '') === 'image/' ? 'selected' : '' ?>>Images</option>
        <option value="application/pdf" <?= ($mime_filter ?? '') === 'application/pdf' ? 'selected' : '' ?>>PDFs</option>
        <option value="text/" <?= ($mime_filter ?? '') === 'text/' ? 'selected' : '' ?>>Text Files</option>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if (!empty($mime_filter)): ?>
        <a href="/media" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($media)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No media files found. Upload your first file above.
        </p>
    </div>
<?php else: ?>
    <div class="media-grid">
        <?php foreach ($media as $item): ?>
        <div class="media-card card">
            <?php if (strpos($item['mime_type'], 'image/') === 0): ?>
                <div class="media-preview">
                    <img src="/api/media/file/<?= htmlspecialchars($item['filename']) ?>" alt="<?= htmlspecialchars($item['alt_text'] ?? $item['original_name']) ?>"
                         onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'media-icon\'>IMG</div>';">
                </div>
            <?php else: ?>
                <div class="media-preview">
                    <div class="media-icon"><?= strtoupper(pathinfo($item['original_name'], PATHINFO_EXTENSION)) ?></div>
                </div>
            <?php endif; ?>
            <div class="media-info">
                <strong title="<?= htmlspecialchars($item['original_name']) ?>"><?= htmlspecialchars(mb_strimwidth($item['original_name'], 0, 30, '...')) ?></strong>
                <span style="font-size:0.75rem;color:var(--text-muted);">
                    <?= htmlspecialchars($item['mime_type']) ?> &middot;
                    <?= number_format($item['file_size'] / 1024, 1) ?> KB
                </span>
                <span style="font-size:0.75rem;color:var(--text-muted);">
                    by <?= htmlspecialchars($item['uploaded_by_name'] ?? '-') ?> &middot;
                    <?= htmlspecialchars($item['created_at'] ?? '') ?>
                </span>
            </div>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
            <form method="POST" action="/media/<?= (int)$item['id'] ?>/delete"
                  onsubmit="return confirm('Delete this file?');"
                  style="margin-top:0.5rem;">
                <button type="submit" class="btn btn-sm btn-danger" style="width:100%;">Delete</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
            <?php
            $qs = $mime_filter ? '&mime=' . urlencode($mime_filter) : '';
            if ($i == $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="/media?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
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
