<?php
$title = htmlspecialchars($specimen['common_name'] ?? 'Specimen');
ob_start();
?>

<p style="margin-bottom:1rem;">
    <a href="/specimens">&larr; Back to catalog</a>
</p>

<div style="display:flex;justify-content:space-between;align-items:flex-start;">
    <div>
        <h1><?= htmlspecialchars($specimen['common_name'] ?? 'Unknown') ?></h1>
        <p class="subtitle">
            <em><?= htmlspecialchars($specimen['species'] ?? '') ?></em>
            — <?= htmlspecialchars($specimen['family'] ?? '') ?>
            <span class="badge badge-<?= ($specimen['status'] ?? 'published') === 'published' ? 'published' : (($specimen['status'] ?? '') === 'draft' ? 'draft' : 'archived') ?>" style="margin-left:0.5rem;">
                <?= htmlspecialchars($specimen['status'] ?? 'published') ?>
            </span>
        </p>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <?php
        $sStatus = $specimen['status'] ?? 'published';
        $sTransitions = [];
        if ($sStatus === 'draft') $sTransitions[] = ['published', 'Publish'];
        if ($sStatus === 'published') $sTransitions[] = ['archived', 'Archive'];
        if ($sStatus === 'archived') $sTransitions[] = ['draft', 'Revert to Draft'];
        foreach ($sTransitions as $tr): ?>
        <form method="POST" action="/specimens/<?= (int)$specimen['id'] ?>/status">
            <input type="hidden" name="status" value="<?= $tr[0] ?>">
            <button type="submit" class="btn btn-sm <?= $tr[0] === 'published' ? 'btn-primary' : 'btn-secondary' ?>"><?= $tr[1] ?></button>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($tags)): ?>
<div style="margin-bottom:1rem;">
    <?php foreach ($tags as $tag): ?>
        <span class="tag-chip"><?= htmlspecialchars($tag['name']) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Taxonomic Information</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Common Name</label>
            <span><?= htmlspecialchars($specimen['common_name'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Species</label>
            <span><em><?= htmlspecialchars($specimen['species'] ?? '-') ?></em></span>
        </div>
        <div class="detail-item">
            <label>Genus</label>
            <span><?= htmlspecialchars($specimen['genus'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Family</label>
            <span><?= htmlspecialchars($specimen['family'] ?? '-') ?></span>
        </div>
    </div>
</div>

<div class="card">
    <h2>Collection Details</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Location Found</label>
            <span><?= htmlspecialchars($specimen['location_found'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Habitat</label>
            <span><?= htmlspecialchars($specimen['habitat'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Date Collected</label>
            <span><?= htmlspecialchars($specimen['collected_date'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Collector</label>
            <span><?= htmlspecialchars($specimen['collector'] ?? '-') ?></span>
        </div>
    </div>
</div>

<div class="card">
    <h2>Description</h2>
    <p style="line-height:1.8;"><?= htmlspecialchars($specimen['description'] ?? 'No description available.') ?></p>
</div>

<div class="card">
    <h2>Preservation & Archive</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <label>Preservation Method</label>
            <span><?= htmlspecialchars($specimen['preservation_method'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Import Source</label>
            <span class="badge badge-info"><?= htmlspecialchars($specimen['source'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Archived On</label>
            <span><?= htmlspecialchars($specimen['imported_at'] ?? '-') ?></span>
        </div>
        <div class="detail-item">
            <label>Specimen ID</label>
            <span>#<?= (int)($specimen['id'] ?? 0) ?></span>
        </div>
    </div>
</div>

<div class="card">
    <h2>History</h2>
    <a href="/specimens/<?= (int)$specimen['id'] ?>/revisions" class="btn btn-secondary">View Revision History</a>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
