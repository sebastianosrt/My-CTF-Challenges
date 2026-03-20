<?php

Flight::route('GET /pages', function () {
    global $api;
    requireLogin();
    $page   = $_GET['page'] ?? 1;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';

    $query = "/api/pages?page={$page}";
    if ($search) $query .= '&search=' . urlencode($search);
    if ($status) $query .= '&status=' . urlencode($status);

    $result = $api->get($query, jwt());
    Flight::render('content/pages', [
        'user'          => currentUser(),
        'pages'         => $result['pages'] ?? [],
        'pagination'    => $result['pagination'] ?? [],
        'search'        => $search,
        'status_filter' => $status,
        'flash'         => getFlash(),
    ]);
});

Flight::route('GET /pages/new', function () {
    global $api;
    requireLogin();
    $tagsResult = $api->get('/api/tags', jwt());
    Flight::render('content/page_edit', [
        'user'     => currentUser(),
        'page'     => null,
        'all_tags' => $tagsResult['tags'] ?? [],
        'flash'    => getFlash(),
    ]);
});

Flight::route('POST /pages', function () {
    global $api;
    requireLogin();
    $data = [
        'title'   => $_POST['title'] ?? '',
        'body'    => $_POST['body'] ?? '',
        'status'  => $_POST['status'] ?? 'draft',
        'tag_ids' => $_POST['tag_ids'] ?? [],
    ];

    $result = $api->post('/api/pages', $data, jwt());

    if (isset($result['id'])) {
        flash('success', 'Page created');
        Flight::redirect('/pages/' . $result['id'] . '/edit');
    } else {
        flash('error', $result['error'] ?? 'Failed to create page');
        Flight::redirect('/pages/new');
    }
});

Flight::route('GET /pages/@id/edit', function ($id) {
    global $api;
    requireLogin();
    $result = $api->get("/api/pages/{$id}", jwt());
    $tagsResult = $api->get('/api/tags', jwt());

    if (!isset($result['page'])) {
        flash('error', 'Page not found');
        Flight::redirect('/pages');
        return;
    }

    Flight::render('content/page_edit', [
        'user'     => currentUser(),
        'page'     => $result['page'],
        'all_tags' => $tagsResult['tags'] ?? [],
        'flash'    => getFlash(),
    ]);
});

Flight::route('POST /pages/@id', function ($id) {
    global $api;
    requireLogin();
    $data = [
        'title'   => $_POST['title'] ?? '',
        'body'    => $_POST['body'] ?? '',
        'tag_ids' => $_POST['tag_ids'] ?? [],
    ];

    $result = $api->put("/api/pages/{$id}", $data, jwt());

    if (isset($result['message'])) {
        flash('success', 'Page updated');
    } else {
        flash('error', $result['error'] ?? 'Failed to update page');
    }
    Flight::redirect("/pages/{$id}/edit");
});

Flight::route('POST /pages/@id/status', function ($id) {
    global $api;
    requireLogin();
    $status = $_POST['status'] ?? '';
    $result = $api->put("/api/pages/{$id}/status", ['status' => $status], jwt());

    if (isset($result['message'])) {
        flash('success', $result['message']);
    } else {
        flash('error', $result['error'] ?? 'Status change failed');
    }
    Flight::redirect("/pages/{$id}/edit");
});

Flight::route('POST /pages/@id/delete', function ($id) {
    global $api;
    requireAdmin();
    $result = $api->delete("/api/pages/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Page deleted');
    } else {
        flash('error', $result['error'] ?? 'Failed to delete page');
    }
    Flight::redirect('/pages');
});

Flight::route('POST /pages/@id/schedule', function ($id) {
    global $api;
    requireLogin();
    $data = [
        'action'        => $_POST['action'] ?? '',
        'scheduled_for' => $_POST['scheduled_for'] ?? '',
    ];

    $result = $api->post("/api/pages/{$id}/schedule", $data, jwt());

    if (isset($result['message'])) {
        flash('success', 'Action scheduled successfully');
    } else {
        flash('error', $result['error'] ?? 'Failed to schedule action');
    }
    Flight::redirect("/pages/{$id}/edit");
});

Flight::route('GET /pages/@id/revisions', function ($id) {
    global $api;
    requireLogin();
    $pageResult = $api->get("/api/pages/{$id}", jwt());
    $revisions = $api->get("/api/pages/{$id}/revisions", jwt());

    Flight::render('content/revisions', [
        'user'         => currentUser(),
        'entity_title' => $pageResult['page']['title'] ?? 'Page #' . $id,
        'revisions'    => $revisions['revisions'] ?? [],
        'back_url'     => "/pages/{$id}/edit",
        'flash'        => getFlash(),
    ]);
});
