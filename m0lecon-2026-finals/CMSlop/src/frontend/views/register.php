<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Herbarium</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>Herbarium</h1>
        <p class="login-subtitle">Create a Researcher Account</p>

        <?php if (!empty($flash)): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/register">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="Letters, numbers, hyphens, underscores"
                       value="<?= htmlspecialchars($old_username ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="display_name">Display Name</label>
                <input type="text" id="display_name" name="display_name"
                       placeholder="How your name appears (optional)"
                       value="<?= htmlspecialchars($old_display_name ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="At least 8 characters">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                Create Account
            </button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.85rem;">
            Already have an account? <a href="/login">Sign in</a>
        </p>
    </div>
</div>
</body>
</html>
