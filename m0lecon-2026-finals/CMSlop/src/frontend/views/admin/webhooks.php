<?php
$title = 'Webhooks';
ob_start();

$availableEvents = [
    'page.created', 'page.updated', 'page.deleted', 'page.status_changed',
    'specimen.updated', 'specimen.status_changed',
    'media.uploaded', 'media.deleted',
    'tag.created', 'tag.deleted',
    'import.success', 'import.failed',
    'test', '*',
];
?>

<h1>Webhooks</h1>
<p class="subtitle">Send event notifications to external services</p>

<div class="card">
    <h2>Create New Webhook</h2>
    <form method="POST" action="/admin/webhooks">
        <div class="form-group">
            <label>Payload URL</label>
            <input type="url" name="url" placeholder="https://example.com/webhook" required>
        </div>
        <div class="form-group">
            <label>Events</label>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.3rem;">
                <?php foreach ($availableEvents as $event): ?>
                    <label style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.85rem;cursor:pointer;">
                        <input type="checkbox" name="events[]" value="<?= htmlspecialchars($event) ?>">
                        <code><?= htmlspecialchars($event) ?></code>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create Webhook</button>
    </form>
</div>

<?php if (empty($webhooks)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No webhooks configured. Create your first webhook above.
        </p>
    </div>
<?php else: ?>
    <?php foreach ($webhooks as $wh): ?>
    <div class="card webhook-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <strong style="font-size:0.9rem;word-break:break-all;"><?= htmlspecialchars($wh['url']) ?></strong>
                <div style="margin-top:0.3rem;">
                    <?php if ($wh['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:0.5rem;">
                    <?php foreach (explode(',', $wh['events']) as $ev): ?>
                        <code style="font-size:0.75rem;background:#f5f5f5;padding:0.1rem 0.4rem;border-radius:3px;margin-right:0.3rem;"><?= htmlspecialchars(trim($ev)) ?></code>
                    <?php endforeach; ?>
                </div>
                <span style="font-size:0.75rem;color:var(--text-muted);display:block;margin-top:0.5rem;">
                    Created by <?= htmlspecialchars($wh['created_by_name'] ?? '-') ?> on <?= htmlspecialchars($wh['created_at']) ?>
                </span>
            </div>
            <div style="display:flex;gap:0.3rem;flex-shrink:0;">
                <form method="POST" action="/admin/webhooks/<?= (int)$wh['id'] ?>/test">
                    <button type="submit" class="btn btn-sm btn-secondary">Test</button>
                </form>
                <form method="POST" action="/admin/webhooks/<?= (int)$wh['id'] ?>/delete"
                      onsubmit="return confirm('Delete this webhook?');">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
