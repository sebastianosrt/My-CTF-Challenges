<?php

Flight::route('GET /tags', function () {
    global $api;
    requireAdmin();
    $result = $api->get('/api/tags', jwt());
    Flight::render('content/tags', [
        'user'  => currentUser(),
        'tags'  => $result['tags'] ?? [],
        'flash' => getFlash(),
    ]);
});

Flight::route('POST /tags', function () {
    global $api;
    requireAdmin();
    $data = [
        'name'        => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
    ];

    $result = $api->post('/api/tags', $data, jwt());

    if (isset($result['id'])) {
        flash('success', 'Tag created');
    } else {
        flash('error', $result['error'] ?? 'Failed to create tag');
    }
    Flight::redirect('/tags');
});

Flight::route('POST /tags/@id/delete', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/tags/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Tag deleted');
    } else {
        flash('error', $result['error'] ?? 'Failed to delete tag');
    }
    Flight::redirect('/tags');
});
