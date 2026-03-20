<?php
Flight::route('GET /admin/users', function () {
    global $api;
    requireAdmin();
    $result = $api->get('/api/admin/users', jwt());
    Flight::render('admin/users', [
        'user'  => currentUser(),
        'users' => $result['users'] ?? [],
        'flash' => getFlash(),
    ]);
});

Flight::route('POST /admin/users/@id/role', function ($id) {
    global $api;
    requireAdmin();
    $role = $_POST['role'] ?? '';
    $result = $api->put("/api/admin/users/{$id}/role", ['role' => $role], jwt());

    if (isset($result['message'])) {
        flash('success', 'User role updated');
    } else {
        flash('error', $result['error'] ?? 'Failed to update role');
    }
    Flight::redirect('/admin/users');
});

Flight::route('GET /admin/audit', function () {
    global $api;
    requireAdmin();
    $page   = $_GET['page'] ?? 1;
    $action = $_GET['action'] ?? '';
    $query  = "/api/admin/audit?page={$page}&limit=50";
    if ($action) $query .= '&action=' . urlencode($action);

    $result = $api->get($query, jwt());
    Flight::render('admin/audit', [
        'user'       => currentUser(),
        'entries'    => $result['entries'] ?? [],
        'pagination' => $result['pagination'] ?? [],
        'action'     => $action,
        'flash'      => getFlash(),
    ]);
});

Flight::route('GET /admin/settings', function () {
    global $api;
    requireAdmin();
    $result = $api->get('/api/settings', jwt());
    Flight::render('admin/settings', [
        'user'     => currentUser(),
        'settings' => $result['settings'] ?? [],
        'flash'    => getFlash(),
    ]);
});

Flight::route('POST /admin/settings', function () {
    global $api;
    requireAdmin();
    $settings = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $settings[substr($key, 8)] = $value;
        }
    }

    $result = $api->put('/api/settings', ['settings' => $settings], jwt());

    if (isset($result['message'])) {
        flash('success', 'Settings saved');
    } else {
        flash('error', $result['error'] ?? 'Failed to save settings');
    }
    Flight::redirect('/admin/settings');
});

Flight::route('GET /admin/apikeys', function () {
    global $api;
    requireAdmin();
    $result = $api->get('/api/admin/apikeys', jwt());
    Flight::render('admin/apikeys', [
        'user'    => currentUser(),
        'apikeys' => $result['keys'] ?? [],
        'flash'   => getFlash(),
    ]);
});

Flight::route('POST /admin/apikeys', function () {
    global $api;
    requireAdmin();
    $data = [
        'name'        => $_POST['name'] ?? '',
        'permissions' => $_POST['permissions'] ?? 'read',
        'expires_at'  => $_POST['expires_at'] ?? null,
    ];

    $result = $api->post('/api/admin/apikeys', $data, jwt());

    if (isset($result['key'])) {
        flash('success', 'API key created. Key: ' . $result['key'] . ' (save it now, it will not be shown again)');
    } else {
        flash('error', $result['error'] ?? 'Failed to create API key');
    }
    Flight::redirect('/admin/apikeys');
});

Flight::route('POST /admin/apikeys/@id/revoke', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/admin/apikeys/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'API key revoked');
    } else {
        flash('error', $result['error'] ?? 'Failed to revoke API key');
    }
    Flight::redirect('/admin/apikeys');
});

Flight::route('GET /admin/webhooks', function () {
    global $api;
    requireAdmin();
    $result = $api->get('/api/admin/webhooks', jwt());
    Flight::render('admin/webhooks', [
        'user'     => currentUser(),
        'webhooks' => $result['webhooks'] ?? [],
        'flash'    => getFlash(),
    ]);
});

Flight::route('POST /admin/webhooks', function () {
    global $api;
    requireAdmin();
    $events = $_POST['events'] ?? [];
    $data = [
        'url'    => $_POST['url'] ?? '',
        'events' => implode(',', $events),
    ];

    $result = $api->post('/api/admin/webhooks', $data, jwt());

    if (isset($result['id'])) {
        flash('success', 'Webhook created');
    } else {
        flash('error', $result['error'] ?? 'Failed to create webhook');
    }
    Flight::redirect('/admin/webhooks');
});

Flight::route('POST /admin/webhooks/@id/delete', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/admin/webhooks/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Webhook deleted');
    } else {
        flash('error', $result['error'] ?? 'Failed to delete webhook');
    }
    Flight::redirect('/admin/webhooks');
});

Flight::route('POST /admin/webhooks/@id/test', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->post("/api/admin/webhooks/{$id}/test", [], jwt());

    if (isset($result['message'])) {
        flash('success', $result['message']);
    } else {
        flash('error', $result['error'] ?? 'Test delivery failed');
    }
    Flight::redirect('/admin/webhooks');
});

Flight::route('GET /admin/scheduling', function () {
    global $api;
    requireAdmin();
    $page = $_GET['page'] ?? 1;
    $result = $api->get("/api/scheduled?page={$page}&per_page=20", jwt());
    Flight::render('content/scheduling', [
        'user'       => currentUser(),
        'actions'    => $result['actions'] ?? [],
        'pagination' => $result['pagination'] ?? [],
        'flash'      => getFlash(),
    ]);
});

Flight::route('POST /admin/scheduled/@id/cancel', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/scheduled/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Scheduled action cancelled');
    } else {
        flash('error', $result['error'] ?? 'Failed to cancel scheduled action');
    }
    Flight::redirect('/admin/scheduling');
});
