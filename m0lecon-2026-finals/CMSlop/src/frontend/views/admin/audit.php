<?php
$title = 'Audit Log';
ob_start();
?>

<h1>Audit Log</h1>
<p class="subtitle">System activity and security events</p>

<form method="GET" action="/admin/audit" class="search-bar" style="margin-bottom:1rem;">
    <input type="text" name="action" placeholder="Filter by action (e.g. login_success, page_created)..."
           value="<?= htmlspecialchars($action ?? '') ?>">
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if (!empty($action)): ?>
        <a href="/admin/audit" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<?php if (empty($entries)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No audit entries found.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>User</th>
                    <th>Detail</th>
                    <th>IP</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td>#<?= (int)$entry['id'] ?></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($entry['action']) ?></span></td>
                    <td><?= htmlspecialchars($entry['username'] ?? '-') ?></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?= htmlspecialchars($entry['detail'] ?? '') ?>">
                        <?= htmlspecialchars($entry['detail'] ?? '-') ?>
                    </td>
                    <td style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($entry['ip_address'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($entry['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['total'] ?? 0) > ($pagination['limit'] ?? 50)): ?>
    <div class="pagination">
        <?php
        $totalPages = (int) ceil($pagination['total'] / $pagination['limit']);
        for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
            $qs = $action ? '&action=' . urlencode($action) : '';
            if ($i == $pagination['page']): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="/admin/audit?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
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
