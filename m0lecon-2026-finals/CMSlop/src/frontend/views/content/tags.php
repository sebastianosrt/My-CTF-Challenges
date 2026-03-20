<?php
$title = 'Tags';
ob_start();
?>

<h1>Tag Management</h1>
<p class="subtitle">Organize content with tags</p>

<?php if (($user['role'] ?? '') === 'admin'): ?>
<div class="card">
    <h2>Create New Tag</h2>
    <form method="POST" action="/tags" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
            <label>Name</label>
            <input type="text" name="name" placeholder="Tag name" required>
        </div>
        <div class="form-group" style="flex:2;min-width:200px;margin-bottom:0;">
            <label>Description</label>
            <input type="text" name="description" placeholder="Optional description">
        </div>
        <button type="submit" class="btn btn-primary">Create Tag</button>
    </form>
</div>
<?php endif; ?>

<?php if (empty($tags)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No tags found.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Created</th>
                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                        <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><span class="tag-chip"><?= htmlspecialchars($tag['name']) ?></span></td>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($tag['slug']) ?></td>
                    <td><?= htmlspecialchars($tag['description'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($tag['created_at'] ?? '-') ?></td>
                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <td>
                        <form method="POST" action="/tags/<?= (int)$tag['id'] ?>/delete"
                              onsubmit="return confirm('Delete this tag?');"
                              style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
