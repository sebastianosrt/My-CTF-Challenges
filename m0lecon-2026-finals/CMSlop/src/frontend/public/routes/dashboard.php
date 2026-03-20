<?php

Flight::route('GET /', function () {
    global $api;
    requireLogin();
    $stats = $api->get('/api/stats', jwt());
    $mediaResult = $api->get('/api/media?per_page=1', jwt());
    $stats['total_media'] = $mediaResult['pagination']['total'] ?? 0;
    $scheduledResult = $api->get('/api/scheduled', jwt());
    $pendingCount = 0;
    foreach (($scheduledResult['actions'] ?? []) as $action) {
        if (($action['status'] ?? '') === 'pending') {
            $pendingCount++;
        }
    }
    $stats['pending_scheduled'] = $pendingCount;

    Flight::render('dashboard', [
        'user'         => currentUser(),
        'stats'        => $stats,
        'recent_pages' => $stats['recent_pages'] ?? [],
        'flash'        => getFlash(),
    ]);
});
