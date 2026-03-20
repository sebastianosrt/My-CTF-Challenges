<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Herbarium</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>Herbarium</h1>
        <p class="login-subtitle">Digital Specimen Archive</p>

        <?php if (!empty($flash)): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="e.g. linnaeus">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                Sign In
            </button>
        </form>

        <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
            <a href="/forgot-password">Forgot your password?</a>
        </p>
        <p style="text-align:center;margin-top:0.5rem;font-size:0.85rem;">
            Don't have an account? <a href="/register">Register</a>
        </p>
    </div>
</div>
</body>
</html>
