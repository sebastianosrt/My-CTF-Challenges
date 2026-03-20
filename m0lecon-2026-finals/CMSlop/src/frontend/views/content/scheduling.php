<?php
$title = 'Scheduled Actions';
ob_start();
?>

<h1>Scheduled Actions</h1>
<p class="subtitle">View and manage content scheduling</p>

<?php if (empty($actions)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No scheduled actions found. Schedule actions from the page or specimen editor.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Entity</th>
                    <th>Action</th>
                    <th>Scheduled For</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actions as $act): ?>
                <tr>
                    <td>#<?= (int)$act['id'] ?></td>
                    <td>
                        <span class="badge badge-info"><?= htmlspecialchars($act['entity_type']) ?></span>
                        #<?= (int)$act['entity_id'] ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $act['action'] === 'published' ? 'published' : ($act['action'] === 'archived' ? 'archived' : 'draft') ?>">
                            <?= htmlspecialchars($act['action']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($act['scheduled_for']) ?></td>
                    <td>
                        <?php
                        $statusClass = 'warning';
                        if ($act['status'] === 'executed') $statusClass = 'success';
                        if ($act['status'] === 'failed') $statusClass = 'danger';
                        if ($act['status'] === 'cancelled') $statusClass = 'archived';
                        ?>
                        <span class="badge badge-<?= $statusClass ?>"><?= htmlspecialchars($act['status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($act['created_by_name'] ?? '-') ?></td>
                    <td>
                        <?php if ($act['status'] === 'pending'): ?>
                        <form method="POST" action="/admin/scheduled/<?= (int)$act['id'] ?>/cancel"
                              onsubmit="return confirm('Cancel this scheduled action?');"
                              style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                        </form>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:0.85rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
            <?php if ($i == $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/scheduling?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
