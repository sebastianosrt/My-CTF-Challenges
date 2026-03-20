<?php
$title = 'User Management';
ob_start();
?>

<h1>User Management</h1>
<p class="subtitle">Manage user accounts and roles</p>

<?php if (empty($users)): ?>
    <div class="card">
        <p style="text-align:center;color:var(--text-muted);padding:2rem 0;">
            No users found.
        </p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Display Name</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <?php if (!empty($u['avatar'])): ?>
                                <img src="/avatars/<?= htmlspecialchars($u['avatar']) ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                                <div style="width:24px;height:24px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:#fff;font-weight:bold;">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($u['display_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $u['role'] === 'admin' ? 'admin' : 'info' ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
                    <td>
                        <form method="POST" action="/admin/users/<?= (int)$u['id'] ?>/role" style="display:inline-flex;gap:0.3rem;align-items:center;">
                            <select name="role" style="padding:0.3rem;border:1px solid var(--border);border-radius:var(--radius);font-size:0.8rem;">
                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-secondary">Update</button>
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
