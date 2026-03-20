<?php
$title = 'Revisions — ' . htmlspecialchars($entity_title ?? 'Content');
ob_start();
?>

<p style="margin-bottom:1rem;">
    <a href="<?= htmlspecialchars($back_url ?? '/pages') ?>">&larr; Back</a>
</p>

<h1>Revision History</h1>
<p class="subtitle"><?= htmlspecialchars($entity_title ?? 'Content') ?></p>

<?php if (empty($revisions)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No revisions found for this content.
        </p>
    </div>
<?php else: ?>
    <div class="revision-list">
        <?php foreach ($revisions as $rev): ?>
        <div class="revision-entry card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <div>
                    <strong>Revision #<?= (int)$rev['id'] ?></strong>
                    <span style="color:var(--text-muted);font-size:0.85rem;">
                        by <?= htmlspecialchars($rev['username'] ?? 'Unknown') ?>
                    </span>
                </div>
                <span style="color:var(--text-muted);font-size:0.85rem;">
                    <?= htmlspecialchars($rev['created_at']) ?>
                </span>
            </div>

            <?php if (!empty($rev['diff_summary'])): ?>
                <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:0.5rem;">
                    <?= htmlspecialchars($rev['diff_summary']) ?>
                </p>
            <?php endif; ?>

            <?php if ($rev['title'] !== null): ?>
                <div style="font-size:0.85rem;margin-bottom:0.3rem;">
                    <strong>Title:</strong> <?= htmlspecialchars($rev['title']) ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:flex-end;">
                <form method="POST" action="/revisions/<?= (int)$rev['id'] ?>/restore"
                      onsubmit="return confirm('Restore this revision? Current content will be overwritten.');">
                    <button type="submit" class="btn btn-sm btn-secondary">Restore</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
