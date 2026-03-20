<?php

Flight::route('GET /collections', function () {
    global $api;
    requireLogin();
    $result = $api->get('/api/collections', jwt());
    Flight::render('collections/list', [
        'user'        => currentUser(),
        'collections' => $result['collections'] ?? [],
        'flash'       => getFlash(),
    ]);
});

Flight::route('POST /collections', function () {
    global $api;
    requireLogin();
    $result = $api->post('/api/collections', [
        'name'        => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
    ], jwt());

    if (isset($result['id'])) {
        flash('success', 'Collection created');
    } else {
        flash('error', $result['error'] ?? 'Failed to create collection');
    }
    Flight::redirect('/collections');
});

Flight::route('GET /collections/@id', function ($id) {
    global $api;
    requireLogin();
    $result = $api->get("/api/collections/{$id}", jwt());
    
    if (isset($result['error'])) {
        flash('error', $result['error']);
        Flight::redirect('/collections');
        return;
    }

    $collection = $result['collection'] ?? [];
    $specimens = $collection['specimens'] ?? [];

    $ts = $result['collection']['updated_at'] ?? $result['collection']['created_at'] ?? json_encode($result);
    $lastModified = new \DateTime($ts);

    Flight::render('collections/detail', [
        'user'       => currentUser(),
        'collection' => $collection,
        'specimens'  => $specimens,
        'flash'      => getFlash(),
        'lastModified' => $lastModified
    ]);
});

Flight::route('POST /collections/@id/delete', function ($id) {
    global $api;
    requireLogin();
    $result = $api->delete("/api/collections/{$id}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Collection deleted');
    } else {
        flash('error', $result['error'] ?? 'Failed to delete collection');
    }
    Flight::redirect('/collections');
});

Flight::route('POST /collections/@id/specimens', function ($id) {
    global $api;
    requireLogin();
    $result = $api->post("/api/collections/{$id}/specimens", [
        'specimen_id' => (int) ($_POST['specimen_id'] ?? 0),
    ], jwt());

    if (isset($result['message'])) {
        flash('success', 'Specimen added to collection');
    } else {
        flash('error', $result['error'] ?? 'Failed to add specimen');
    }
    Flight::redirect("/collections/{$id}");
});

Flight::route('POST /collections/@collectionId/specimens/@specimenId/remove', function ($collectionId, $specimenId) {
    global $api;
    requireLogin();
    $result = $api->delete("/api/collections/{$collectionId}/specimens/{$specimenId}", jwt());

    if (isset($result['message'])) {
        flash('success', 'Specimen removed from collection');
    } else {
        flash('error', $result['error'] ?? 'Failed to remove specimen');
    }
    Flight::redirect("/collections/{$collectionId}");
});
