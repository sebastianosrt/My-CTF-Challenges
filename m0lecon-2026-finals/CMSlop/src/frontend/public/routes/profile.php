<?php

Flight::route('GET /profile', function () {
    global $api;
    requireLogin();
    $profile = $api->get('/api/user/profile', jwt());
    Flight::render('profile', [
        'user'    => currentUser(),
        'profile' => $profile['user'] ?? currentUser(),
        'flash'   => getFlash(),
    ]);
});

Flight::route('POST /profile', function () {
    global $api;
    requireLogin();
    $result = $api->post('/api/user/profile', [
        'display_name' => $_POST['display_name'] ?? '',
    ], jwt());

    if (isset($result['message'])) {
        $_SESSION['user']['display_name'] = $_POST['display_name'];
        flash('success', 'Profile updated');
    } else {
        flash('error', $result['error'] ?? 'Update failed');
    }
    Flight::redirect('/profile');
});

Flight::route('POST /profile/avatar', function () {
    global $api;
    requireLogin();

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'No file selected or upload error');
        Flight::redirect('/profile');
        return;
    }

    $result = $api->uploadFile('/api/user/avatar', 'avatar', $_FILES['avatar'], jwt());

    if (isset($result['avatar'])) {
        $_SESSION['user']['avatar'] = $result['avatar'];
        flash('success', 'Avatar updated!');
    } else {
        flash('error', $result['error'] ?? 'Upload failed');
    }
    Flight::redirect('/profile');
});

Flight::route('POST /profile/password', function () {
    global $api;
    requireLogin();
    $result = $api->put('/api/user/password', [
        'current_password' => $_POST['current_password'] ?? '',
        'new_password'     => $_POST['new_password'] ?? '',
    ], jwt());

    if (isset($result['message'])) {
        flash('success', 'Password updated');
    } else {
        flash('error', $result['error'] ?? 'Password change failed');
    }
    Flight::redirect('/profile');
});

Flight::route('GET /avatars/@filename', function ($filename) {
    global $api;
    $data = $api->fetchRaw("/api/avatars/{$filename}", jwt());
    if ($data) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($data);
        header("Content-Type: {$mime}");
        header("Cache-Control: public, max-age=3600");
        echo $data;
    } else {
        Flight::halt(404, 'Not Found');
    }
});
