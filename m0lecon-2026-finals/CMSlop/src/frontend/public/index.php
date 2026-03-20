<?php

require __DIR__ . '/../vendor/autoload.php';

use Herbarium\ApiClient;

$api = new ApiClient();

session_start();
define('JWT_COOKIE', 'herbarium_token');

function jwt(): ?string
{
    return $_COOKIE[JWT_COOKIE] ?? null;
}

function setJwtCookie(string $token): void
{
    setcookie(JWT_COOKIE, $token, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[JWT_COOKIE] = $token;
}

function clearJwtCookie(): void
{
    setcookie(JWT_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[JWT_COOKIE]);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!jwt()) {
        Flight::redirect('/login');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ((currentUser()['role'] ?? '') !== 'admin') {
        Flight::redirect('/');
        exit;
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
Flight::set('flight.views.path', __DIR__ . '/../views');
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/dashboard.php';
require __DIR__ . '/routes/profile.php';
require __DIR__ . '/routes/specimens.php';
require __DIR__ . '/routes/pages.php';
require __DIR__ . '/routes/tags.php';
require __DIR__ . '/routes/revisions.php';
require __DIR__ . '/routes/collections.php';
require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/media.php';

Flight::start();
