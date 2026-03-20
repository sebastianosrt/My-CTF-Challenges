<?php

Flight::route('POST /revisions/@id/restore', function ($id) {
    global $api;
    requireLogin();

    $result = $api->post("/api/revisions/{$id}/restore", [], jwt());

    if (isset($result['message'])) {
        flash('success', 'Revision restored');
    } else {
        flash('error', $result['error'] ?? 'Failed to restore revision');
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '/pages';
    Flight::redirect($referer);
});
