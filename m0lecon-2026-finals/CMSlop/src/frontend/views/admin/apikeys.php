<?php
$title = 'API Keys';
ob_start();
?>

<h1>API Keys</h1>
<p class="subtitle">Manage API authentication keys</p>

<div class="card">
    <h2>Create New API Key</h2>
    <form method="POST" action="/admin/apikeys" style="display:flex;gap:0.5rem;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
            <label>Name</label>
            <input type="text" name="name" placeholder="Key name (e.g. Mobile App)" required>
        </div>
        <div class="form-group" style="flex:1;min-width:150px;margin-bottom:0;">
            <label>Permissions</label>
            <select name="permissions" style="padding:0.6rem;border:1px solid var(--border);border-radius:var(--radius);font-family:inherit;width:100%;">
                <option value="read">Read Only</option>
                <option value="read,write">Read & Write</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="form-group" style="min-width:180px;margin-bottom:0;">
            <label>Expires At (optional)</label>
            <input type="datetime-local" name="expires_at">
        </div>
        <button type="submit" class="btn btn-primary">Create Key</button>
    </form>
</div>

<?php if (empty($apikeys)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No API keys found. Create your first key above.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Key Prefix</th>
                    <th>Permissions</th>
                    <th>Status</th>
                    <th>Last Used</th>
                    <th>Expires</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apikeys as $key): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($key['name']) ?></strong></td>
                    <td>
                        <code class="key-prefix"><?= htmlspecialchars($key['key_prefix']) ?>...</code>
                    </td>
                    <td>
                        <?php foreach (explode(',', $key['permissions']) as $perm): ?>
                            <span class="badge badge-info"><?= htmlspecialchars(trim($perm)) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($key['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Revoked</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($key['last_used_at'] ?? 'Never') ?></td>
                    <td><?= $key['expires_at'] ? htmlspecialchars($key['expires_at']) : 'Never' ?></td>
                    <td>
                        <?= htmlspecialchars($key['created_at']) ?><br>
                        <span style="font-size:0.75rem;color:var(--text-muted);">by <?= htmlspecialchars($key['created_by_name'] ?? '-') ?></span>
                    </td>
                    <td>
                        <?php if ($key['is_active']): ?>
                        <form method="POST" action="/admin/apikeys/<?= (int)$key['id'] ?>/revoke"
                              onsubmit="return confirm('Revoke this API key? This cannot be undone.');"
                              style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
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
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
