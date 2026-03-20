<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;

$router->get('/api/user/profile', function () {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $rows = Database::prepared(
        "SELECT id, username, display_name, role, avatar, created_at FROM users WHERE id = ?",
        [$userId]
    );

    if (empty($rows)) {
        json_response(['error' => 'User not found'], 404);
    }

    json_response(['user' => $rows[0]]);
});

$router->post('/api/user/profile', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);

    $displayName = $body['display_name'] ?? '';

    if (empty($displayName)) {
        json_response(['error' => 'Display name is required'], 400);
    }

    Database::preparedExec(
        "UPDATE users SET display_name = ? WHERE id = ?",
        [$displayName, $userId]
    );

    $audit->record('profile_update', $userId, "display_name={$displayName}");

    json_response(['message' => 'Profile updated']);
});

$router->put('/api/user/password', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);

    $current = $body['current_password'] ?? '';
    $newPass = $body['new_password'] ?? '';

    if (empty($current) || empty($newPass)) {
        json_response(['error' => 'Current password and new password are required'], 400);
    }
    if (strlen($newPass) < 8) {
        json_response(['error' => 'New password must be at least 8 characters'], 400);
    }

    $row = Database::preparedFirst("SELECT password FROM users WHERE id = ?", [$userId]);
    if ($row === null || !password_verify($current, $row['password'])) {
        json_response(['error' => 'Current password is incorrect'], 403);
    }

    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    Database::preparedExec("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId]);

    $audit->record('password_change', $userId, '');
    json_response(['message' => 'Password updated']);
});

$router->post('/api/user/avatar', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!isset($_FILES['avatar'])) {
        json_response(['error' => 'No file uploaded'], 400);
    }

    $file    = $_FILES['avatar'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed, true)) {
        json_response(['error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'], 400);
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        json_response(['error' => 'File too large. Maximum 2MB'], 400);
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = isset($extMap[$mimeType]) ? $extMap[$mimeType] : 'bin';

    $filename = "avatar_{$userId}" . ".{$ext}";
    $destPath = "/var/www/html/uploads/avatars/{$filename}";

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_response(['error' => 'Failed to save file'], 500);
    }

    // $rows = Database::prepared("SELECT avatar FROM users WHERE id = ?", [$userId]);
    // if (!empty($rows[0]['avatar'])) {
    //     $oldPath = "/var/www/html/uploads/avatars/" . basename($rows[0]['avatar']);
    //     if (file_exists($oldPath)) {
    //         @unlink($oldPath);
    //     }
    // }

    Database::preparedExec("UPDATE users SET avatar = ? WHERE id = ?", [$filename, $userId]);
    $audit->record('avatar_upload', $userId, "file={$filename}");

    json_response([
        'message' => 'Avatar uploaded successfully',
        'avatar'  => $filename,
    ]);
});

$router->get('/api/avatars/{filename}', function (string $filename) {
    $safeName = basename($filename);
    $path     = "/var/www/html/uploads/avatars/{$safeName}";

    if (!file_exists($path)) {
        json_response(['error' => 'Avatar not found'], 404);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);

    header("Content-Type: {$mime}");
    header("Cache-Control: public, max-age=86400");
    readfile($path);
    exit;
});
