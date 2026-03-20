<?php

Flight::route('GET /specimens', function () {
    global $api;
    requireLogin();
    $page   = $_GET['page'] ?? 1;
    $search = $_GET['search'] ?? '';
    $query  = "/api/specimens?page={$page}";
    if ($search) {
        $query .= '&search=' . urlencode($search);
    }
    $result = $api->get($query, jwt());
    Flight::render('specimens/list', [
        'user'       => currentUser(),
        'specimens'  => $result['specimens'] ?? [],
        'pagination' => $result['pagination'] ?? [],
        'search'     => $search,
        'flash'      => getFlash(),
    ]);
});

Flight::route('GET /specimens/@id', function ($id) {
    global $api;
    requireLogin();
    $result = $api->get("/api/specimens/{$id}", jwt());
    if (isset($result['specimen'])) {
        $tagsResult = $api->get("/api/specimens/{$id}/tags", jwt());
        Flight::render('specimens/detail', [
            'user'     => currentUser(),
            'specimen' => $result['specimen'],
            'tags'     => $tagsResult['tags'] ?? [],
            'flash'    => getFlash(),
        ]);
    } else {
        flash('error', 'Specimen not found');
        Flight::redirect('/specimens');
    }
});

Flight::route('POST /specimens/@id/status', function ($id) {
    global $api;
    requireLogin();
    $status = $_POST['status'] ?? '';
    $result = $api->put("/api/specimens/{$id}/status", ['status' => $status], jwt());

    if (isset($result['message'])) {
        flash('success', $result['message']);
    } else {
        flash('error', $result['error'] ?? 'Status change failed');
    }
    Flight::redirect("/specimens/{$id}");
});

Flight::route('GET /specimens/@id/revisions', function ($id) {
    global $api;
    requireLogin();
    $specimen = $api->get("/api/specimens/{$id}", jwt());
    $revisions = $api->get("/api/specimens/{$id}/revisions", jwt());

    Flight::render('content/revisions', [
        'user'         => currentUser(),
        'entity_title' => $specimen['specimen']['common_name'] ?? 'Specimen #' . $id,
        'revisions'    => $revisions['revisions'] ?? [],
        'back_url'     => "/specimens/{$id}",
        'flash'        => getFlash(),
    ]);
});
