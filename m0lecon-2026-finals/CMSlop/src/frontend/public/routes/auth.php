<?php

Flight::route('GET /login', function () {
    if (jwt()) {
        Flight::redirect('/');
        return;
    }
    Flight::render('login', ['flash' => getFlash()]);
});

Flight::route('POST /login', function () {
    global $api;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = $api->post('/api/auth/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if (isset($result['token'])) {
        setJwtCookie($result['token']);
        $_SESSION['user'] = $result['user'];
        flash('success', "Welcome back, {$result['user']['display_name']}!");
        Flight::redirect('/');
    } else {
        flash('error', $result['error'] ?? 'Login failed');
        Flight::redirect('/login');
    }
});

Flight::route('GET /register', function () {
    if (jwt()) {
        Flight::redirect('/');
        return;
    }
    Flight::render('register', ['flash' => getFlash()]);
});

Flight::route('POST /register', function () {
    global $api;
    $username    = $_POST['username'] ?? '';
    $password    = $_POST['password'] ?? '';
    $displayName = $_POST['display_name'] ?? '';

    $result = $api->post('/api/auth/register', [
        'username'     => $username,
        'password'     => $password,
        'display_name' => $displayName,
    ]);

    if (isset($result['token'])) {
        setJwtCookie($result['token']);
        $_SESSION['user'] = $result['user'];
        flash('success', 'Account created! Welcome to Herbarium.');
        Flight::redirect('/');
    } else {
        flash('error', $result['error'] ?? 'Registration failed');
        Flight::redirect('/register');
    }
});

Flight::route('GET /forgot-password', function () {
    if (jwt()) {
        Flight::redirect('/');
        return;
    }
    Flight::render('forgot_password', ['flash' => getFlash()]);
});

Flight::route('POST /forgot-password', function () {
    global $api;
    $username = $_POST['username'] ?? '';

    $result = $api->post('/api/auth/forgot-password', [
        'username' => $username,
    ]);

    flash('success', $result['message'] ?? 'If that account exists, a password reset link has been sent to the registered email address.');
    Flight::redirect('/forgot-password');
});

Flight::route('GET /reset-password', function () {
    if (jwt()) {
        Flight::redirect('/');
        return;
    }
    $token = $_GET['token'] ?? '';
    Flight::render('reset_password', ['flash' => getFlash(), 'token' => $token]);
});

Flight::route('POST /reset-password', function () {
    global $api;
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        flash('error', 'Passwords do not match');
        Flight::redirect('/reset-password?token=' . urlencode($token));
        return;
    }

    $result = $api->post('/api/auth/reset-password', [
        'token'    => $token,
        'password' => $password,
    ]);

    if (isset($result['message']) && !isset($result['error'])) {
        flash('success', 'Password reset successfully. Please sign in.');
        Flight::redirect('/login');
    } else {
        flash('error', $result['error'] ?? 'Password reset failed');
        Flight::redirect('/reset-password?token=' . urlencode($token));
    }
});

Flight::route('GET /logout', function () {
    clearJwtCookie();
    session_destroy();
    Flight::redirect('/login');
});
