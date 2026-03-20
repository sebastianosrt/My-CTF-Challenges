<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Herbarium') ?> — Herbarium</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            Herbarium
            <small>Headless CMS</small>
        </div>
        <nav>
            <div class="nav-section">Collection</div>
            <a href="/" class="<?= ($_SERVER['REQUEST_URI'] === '/') ? 'active' : '' ?>">Dashboard</a>
            <a href="/specimens" class="<?= (strpos($_SERVER['REQUEST_URI'], '/specimens') === 0) ? 'active' : '' ?>">Specimens</a>
            <a href="/collections" class="<?= (strpos($_SERVER['REQUEST_URI'], '/collections') === 0) ? 'active' : '' ?>">Collections</a>
            <a href="/annotations/search" class="<?= (strpos($_SERVER['REQUEST_URI'], '/annotations') === 0) ? 'active' : '' ?>">Annotations</a>

            <div class="nav-section">Content</div>
            <a href="/pages" class="<?= (strpos($_SERVER['REQUEST_URI'], '/pages') === 0) ? 'active' : '' ?>">Pages</a>
            <a href="/tags" class="<?= (strpos($_SERVER['REQUEST_URI'], '/tags') === 0) ? 'active' : '' ?>">Tags</a>
            <a href="/media" class="<?= (strpos($_SERVER['REQUEST_URI'], '/media') === 0) ? 'active' : '' ?>">Media</a>

            <a href="/profile" class="<?= (strpos($_SERVER['REQUEST_URI'], '/profile') === 0) ? 'active' : '' ?>">My Profile</a>

            <?php if (($user['role'] ?? '') === 'admin'): ?>
                <div class="nav-section">Administration</div>
                <a href="/admin/users" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/users') === 0) ? 'active' : '' ?>">Users</a>
                <a href="/admin/audit" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/audit') === 0) ? 'active' : '' ?>">Audit Log</a>
                <a href="/admin/settings" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/settings') === 0) ? 'active' : '' ?>">Settings</a>
                <a href="/admin/apikeys" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/apikeys') === 0) ? 'active' : '' ?>">API Keys</a>
                <a href="/admin/webhooks" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/webhooks') === 0) ? 'active' : '' ?>">Webhooks</a>
                <a href="/admin/scheduling" class="<?= (strpos($_SERVER['REQUEST_URI'], '/admin/scheduling') === 0) ? 'active' : '' ?>">Scheduling</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-badge">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar-placeholder">
                        <?= strtoupper(substr($user['username'] ?? '?', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="color:#fff;font-size:0.9rem;"><?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? 'User') ?></div>
                    <a href="/logout" style="color:#a0a0a0;font-size:0.75rem;">Sign out</a>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <?php if (!empty($flash)): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</div>
</body>
</html>
