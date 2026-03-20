<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Herbarium</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>Herbarium</h1>
        <p class="login-subtitle">Reset Your Password</p>

        <?php if (!empty($flash)): ?>
            <div class="flash flash-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/forgot-password">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="Enter your username">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                Request Reset Token
            </button>
        </form>

        <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
            Already have a token? <a href="/reset-password">Reset password</a>
        </p>
        <p style="text-align:center;margin-top:0.5rem;font-size:0.85rem;">
            <a href="/login">Back to Sign In</a>
        </p>
    </div>
</div>
</body>
</html>
