<?php
$title = 'Collections';
ob_start();
?>

<h1>My Collections</h1>
<p class="subtitle">Organize specimens into named collections</p>

<div class="card" style="margin-bottom:1.5rem;">
    <h2>Create Collection</h2>
    <form method="POST" action="/collections" style="display:flex;gap:0.75rem;align-items:flex-end;">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Spring Field Work">
        </div>
        <div class="form-group" style="flex:2;margin-bottom:0;">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" placeholder="Optional description">
        </div>
        <button type="submit" class="btn btn-primary">Create</button>
    </form>
</div>

<?php if (empty($collections)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No collections yet. Create one above to get started.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($collections as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td>
                        <a href="/collections/<?= (int)$c['id'] ?>">
                            <strong><?= htmlspecialchars($c['name']) ?></strong>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($c['description'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['created_at'] ?? '-') ?></td>
                    <td>
                        <form method="POST" action="/collections/<?= (int)$c['id'] ?>/delete"
                              onsubmit="return confirm('Delete this collection?');" style="margin:0;">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
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
