<?php

Flight::route('GET /media', function () {
    global $api;
    requireLogin();
    $page = $_GET['page'] ?? 1;
    $mime = $_GET['mime'] ?? '';
    $query = "/api/media?page={$page}";
    if ($mime) $query .= '&mime=' . urlencode($mime);

    $result = $api->get($query, jwt());
    Flight::render('content/media', [
        'user'       => currentUser(),
        'media'      => $result['media'] ?? [],
        'pagination' => $result['pagination'] ?? [],
        'mime_filter' => $mime,
        'flash'      => getFlash(),
    ]);
});

Flight::route('POST /media/upload', function () {
    global $api;
    requireLogin();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'No file selected or upload error');
        Flight::redirect('/media');
        return;
    }

    $result = $api->uploadFile('/api/media', 'file', $_FILES['file'], jwt());

    if (isset($result['id'])) {
        flash('success', 'File uploaded successfully');
    } else {
        flash('error', $result['error'] ?? 'Upload failed');
    }
    Flight::redirect('/media');
});

Flight::route('POST /media/@id/delete', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/media/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Media deleted');
    } else {
        flash('error', $result['error'] ?? 'Failed to delete media');
    }
    Flight::redirect('/media');
});
