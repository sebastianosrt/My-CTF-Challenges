<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Media\MediaManager;
use Herbarium\Webhooks\WebhookDispatcher;

$router->get('/api/media', function () {
    JwtAuth::requireAuth();

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $mimeFilter = $_GET['mime'] ?? null;

    $result = MediaManager::list($page, $perPage, $mimeFilter);
    json_response($result);
});

$router->post('/api/media', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!isset($_FILES['file'])) {
        json_response(['error' => 'No file uploaded'], 400);
    }

    $altText = $_POST['alt_text'] ?? '';
    $caption = $_POST['caption'] ?? '';

    $result = MediaManager::upload($_FILES['file'], $userId, $altText, $caption);

    if (isset($result['error'])) {
        json_response(['error' => $result['error']], 400);
    }

    $audit->record('media_uploaded', $userId, "media={$result['id']},file={$result['original_name']}");
    WebhookDispatcher::dispatch('media.uploaded', ['id' => $result['id']]);
    json_response($result, 201);
});

$router->get('/api/media/{id}', function (string $id) {
    JwtAuth::requireAuth();

    $media = MediaManager::get((int) $id);
    if ($media === null) {
        json_response(['error' => 'Media not found'], 404);
    }

    json_response(['media' => $media]);
});

$router->put('/api/media/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body = json_decode(file_get_contents('php://input'), true);
    $altText = $body['alt_text'] ?? '';
    $caption = $body['caption'] ?? '';

    if (!MediaManager::update((int) $id, $altText, $caption)) {
        json_response(['error' => 'Media not found'], 404);
    }

    $audit->record('media_updated', $userId, "media={$id}");
    json_response(['message' => 'Media updated']);
});

$router->delete('/api/media/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        if (!MediaManager::delete((int) $id)) {
            json_response(['error' => 'Media not found'], 404);
        }

        $audit->record('media_deleted', $userId, "media={$id}");
        WebhookDispatcher::dispatch('media.deleted', ['id' => (int)$id]);
        json_response(['message' => 'Media deleted']);
    },
    [RouteGuard::admin()]
));

$router->get('/api/media/file/{filename}', function (string $filename) {
    $path = MediaManager::getFilePath($filename);

    if ($path === null) {
        json_response(['error' => 'File not found'], 404);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);

    header("Content-Type: {$mime}");
    header("Cache-Control: public, max-age=86400");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
});
