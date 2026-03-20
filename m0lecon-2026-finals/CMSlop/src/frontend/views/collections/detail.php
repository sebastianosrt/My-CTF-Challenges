<?php
$title = htmlspecialchars($collection['name'] ?? 'Collection');
ob_start();
?>

<h1><?= htmlspecialchars($collection['name'] ?? 'Collection') ?></h1>
<p class="subtitle"><?= htmlspecialchars($collection['description'] ?? '') ?></p>

<div class="card" style="margin-bottom:1.5rem;">
    <h2>Add Specimen</h2>
    <form method="POST" action="/collections/<?= (int)$collection['id'] ?>/specimens" style="display:flex;gap:0.75rem;align-items:flex-end;">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label for="specimen_id">Specimen ID</label>
            <input type="number" id="specimen_id" name="specimen_id" required min="1" placeholder="Enter specimen ID">
        </div>
        <button type="submit" class="btn btn-primary">Add</button>
    </form>
</div>

<?php if (empty($specimens)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No specimens in this collection yet.
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($specimens as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td>
                        <a href="/specimens/<?= (int)$s['id'] ?>">
                            <strong><?= htmlspecialchars($s['common_name'] ?? '-') ?></strong>
                        </a>
                    </td>
                    <td><em><?= htmlspecialchars($s['species'] ?? '-') ?></em></td>
                    <td><?= htmlspecialchars($s['family'] ?? '-') ?></td>
                    <td>
                        <form method="POST" action="/collections/<?= (int)$collection['id'] ?>/specimens/<?= (int)$s['id'] ?>/remove" style="margin:0;">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div style="margin-top:1.5rem;">
    <a href="/collections" class="btn btn-secondary">Back to Collections</a>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
