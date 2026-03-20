<?php
$title = 'My Profile';
ob_start();
?>

<h1>My Profile</h1>
<p class="subtitle">Manage your researcher profile</p>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <div class="card">
        <h2>Avatar</h2>
        <div style="text-align:center;">
            <?php if (!empty($profile['avatar'])): ?>
                <img src="/avatars/<?= htmlspecialchars($profile['avatar']) ?>"
                     alt="Your avatar" class="avatar-preview">
            <?php else: ?>
                <div class="avatar-placeholder-lg" style="margin:0 auto;">
                    <?= strtoupper(substr($profile['username'] ?? '?', 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
        <form method="POST" action="/profile/avatar" enctype="multipart/form-data" style="margin-top:1rem;">
            <div class="form-group">
                <label for="avatar">Upload new avatar</label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.8rem;">
                Max 2MB. Accepted: JPEG, PNG, GIF, WebP.
            </p>
            <button type="submit" class="btn btn-primary btn-sm">Upload Avatar</button>
        </form>
    </div>

    <div class="card">
        <h2>Profile Details</h2>
        <form method="POST" action="/profile">
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?= htmlspecialchars($profile['username'] ?? '') ?>" disabled
                       style="background:#f5f0eb;cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name"
                       value="<?= htmlspecialchars($profile['display_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?= htmlspecialchars(ucfirst($profile['role'] ?? 'user')) ?>" disabled
                       style="background:#f5f0eb;cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label>Member Since</label>
                <input type="text" value="<?= htmlspecialchars($profile['created_at'] ?? '-') ?>" disabled
                       style="background:#f5f0eb;cursor:not-allowed;">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:1.5rem;">
    <h2>Change Password</h2>
    <form method="POST" action="/profile/password">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required
                   placeholder="At least 8 characters">
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
