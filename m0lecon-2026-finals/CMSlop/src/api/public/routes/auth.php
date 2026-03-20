<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;

$router->post('/api/auth/login', RouteGuard::wrap(
    function () use ($audit) {
        $body     = json_decode(file_get_contents('php://input'), true);
        $username = $body['username'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($username) || empty($password)) {
            json_response(['error' => 'Username and password are required'], 400);
        }

        $rows = Database::prepared(
            "SELECT id, username, password, display_name, role, avatar FROM users WHERE username = ? LIMIT 1",
            [$username]
        );

        if (empty($rows) || !password_verify($password, $rows[0]['password'])) {
            $audit->record('login_failed', null, "username={$username}");
            json_response(['error' => 'Invalid credentials'], 401);
        }

        $user  = $rows[0];
        $token = JwtAuth::encode([
            'sub'      => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ]);

        $audit->record('login_success', (int) $user['id'], "username={$user['username']}");

        json_response([
            'token' => $token,
            'user'  => [
                'id'           => $user['id'],
                'username'     => $user['username'],
                'display_name' => $user['display_name'],
                'role'         => $user['role'],
                'avatar'       => $user['avatar'],
            ],
        ]);
    },
    [RouteGuard::rateLimit(20, 60)]
));

$router->post('/api/auth/register', RouteGuard::wrap(
    function () use ($audit) {
        $body     = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $display  = trim($body['display_name'] ?? '');

        if (empty($username) || empty($password)) {
            json_response(['error' => 'Username and password are required'], 400);
        }
        if (strlen($username) < 3 || strlen($username) > 50) {
            json_response(['error' => 'Username must be 3-50 characters'], 400);
        }

        $exists = Database::preparedExists(
            "SELECT 1 FROM users WHERE username = ?",
            [$username]
        );
        if ($exists) {
            json_response(['error' => 'Username already taken'], 409);
        }

        if (empty($display)) {
            $display = $username;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        Database::preparedExec(
            "INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, 'user')",
            [$username, $hash, $display]
        );
        $userId = (int) Database::lastInsertId();

        $audit->record('register', $userId, "username={$username}");

        $token = JwtAuth::encode([
            'sub'      => $userId,
            'username' => $username,
            'role'     => 'user',
        ]);

        json_response([
            'token' => $token,
            'user'  => [
                'id'           => $userId,
                'username'     => $username,
                'display_name' => $display,
                'role'         => 'user',
                'avatar'       => null,
            ],
        ], 201);
    },
    [RouteGuard::rateLimit(10, 60)]
));

$router->post('/api/auth/forgot-password', RouteGuard::wrap(
    function () use ($audit) {
        $body     = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');

        if (empty($username)) {
            json_response(['error' => 'Username is required'], 400);
        }

        $user = Database::preparedFirst(
            "SELECT id FROM users WHERE username = ? LIMIT 1",
            [$username]
        );

        if ($user) {
            Database::preparedExec(
                "DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at < NOW()",
                [$user['id']]
            );

            $token = bin2hex(random_bytes(32));
            Database::preparedExec(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
                [$user['id'], $token]
            );

            $audit->record('password_reset_requested', (int) $user['id'], "username={$username}");
        }

        json_response([
            'message' => 'If that account exists, a password reset link has been sent to the registered email address.',
        ]);
    },
    [RouteGuard::rateLimit(5, 60)]
));

$router->post('/api/auth/reset-password', RouteGuard::wrap(
    function () use ($audit) {
        $body     = json_decode(file_get_contents('php://input'), true);
        $token    = trim($body['token'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($token) || empty($password)) {
            json_response(['error' => 'Token and new password are required'], 400);
        }
        if (strlen($password) < 8) {
            json_response(['error' => 'Password must be at least 8 characters'], 400);
        }

        $row = Database::preparedFirst(
            "SELECT id, user_id FROM password_reset_tokens WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            [$token]
        );

        if (!$row) {
            json_response(['error' => 'Invalid or expired reset token'], 400);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        Database::preparedExec(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hash, $row['user_id']]
        );

        Database::preparedExec(
            "UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?",
            [$row['id']]
        );

        $audit->record('password_reset', (int) $row['user_id'], "via reset token");

        json_response(['message' => 'Password has been reset successfully']);
    },
    [RouteGuard::rateLimit(10, 60)]
));

$router->post('/api/auth/refresh', function () {
    $claims = JwtAuth::requireAuth();
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
        $newToken = JwtAuth::refresh($matches[1]);
        if ($newToken !== null) {
            json_response(['token' => $newToken]);
        }
    }

    json_response(['error' => 'Could not refresh token'], 400);
});
