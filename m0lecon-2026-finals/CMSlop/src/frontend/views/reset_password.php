<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Herbarium</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>Herbarium</h1>
        <p class="login-subtitle">Set New Password</p>

        <?php if (!empty($flash)): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/reset-password">
            <div class="form-group">
                <label for="token">Reset Token</label>
                <input type="text" id="token" name="token" required
                       value="<?= htmlspecialchars($token ?? '') ?>"
                       placeholder="Paste your reset token">
            </div>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Minimum 8 characters" minlength="8">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required
                       placeholder="Repeat your new password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                Reset Password
            </button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.85rem;">
            <a href="/login">Back to Sign In</a>
        </p>
    </div>
</div>
</body>
</html>
