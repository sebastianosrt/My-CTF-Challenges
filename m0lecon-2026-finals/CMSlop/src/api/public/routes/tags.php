<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Content\TagManager;
use Herbarium\Webhooks\WebhookDispatcher;

$router->get('/api/tags', function () {
    JwtAuth::requireAuth();

    $search = $_GET['search'] ?? null;
    $tags = TagManager::list($search);
    json_response(['tags' => $tags]);
});

$router->post('/api/tags', RouteGuard::wrap(
    function () use ($audit) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        $body = json_decode(file_get_contents('php://input'), true);
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';

        if (empty(trim($name))) {
            json_response(['error' => 'Tag name is required'], 400);
        }

        $id = TagManager::create($name, $description);

        $audit->record('tag_created', $userId, "tag={$id},name={$name}");
        WebhookDispatcher::dispatch('tag.created', ['id' => $id, 'name' => $name]);
        json_response(['message' => 'Tag created', 'id' => $id, 'tag' => TagManager::get($id)]);
    },
    [RouteGuard::admin()]
));

$router->put('/api/tags/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        $body = json_decode(file_get_contents('php://input'), true);
        $name = $body['name'] ?? '';
        $description = $body['description'] ?? '';

        if (empty(trim($name))) {
            json_response(['error' => 'Tag name is required'], 400);
        }

        if (!TagManager::update((int) $id, $name, $description)) {
            json_response(['error' => 'Tag not found'], 404);
        }

        $audit->record('tag_updated', $userId, "tag={$id},name={$name}");
        json_response(['message' => 'Tag updated', 'tag' => TagManager::get((int) $id)]);
    },
    [RouteGuard::admin()]
));

$router->delete('/api/tags/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        if (!TagManager::delete((int) $id)) {
            json_response(['error' => 'Tag not found'], 404);
        }

        $audit->record('tag_deleted', $userId, "tag={$id}");
        WebhookDispatcher::dispatch('tag.deleted', ['id' => (int)$id]);
        json_response(['message' => 'Tag deleted']);
    },
    [RouteGuard::admin()]
));

$router->post('/api/tags/{id}/attach', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body      = json_decode(file_get_contents('php://input'), true);
    $entityId  = (int) ($body['entity_id'] ?? 0);
    $type      = $body['type'] ?? '';

    if ($entityId <= 0 || !in_array($type, ['specimen', 'page'], true)) {
        json_response(['error' => 'Valid entity_id and type (specimen|page) are required'], 400);
    }

    TagManager::tag((int) $id, $entityId, $type);

    $audit->record('tag_attached', $userId, "tag={$id},entity={$entityId},type={$type}");
    json_response(['message' => 'Tag attached']);
});

$router->post('/api/tags/{id}/detach', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body      = json_decode(file_get_contents('php://input'), true);
    $entityId  = (int) ($body['entity_id'] ?? 0);
    $type      = $body['type'] ?? '';

    if ($entityId <= 0 || !in_array($type, ['specimen', 'page'], true)) {
        json_response(['error' => 'Valid entity_id and type (specimen|page) are required'], 400);
    }

    TagManager::untag((int) $id, $entityId, $type);

    $audit->record('tag_detached', $userId, "tag={$id},entity={$entityId},type={$type}");
    json_response(['message' => 'Tag detached']);
});

$router->get('/api/tags/{id}/entities', function (string $id) {
    JwtAuth::requireAuth();

    $type  = $_GET['type'] ?? 'page';
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

    if (!in_array($type, ['specimen', 'page'], true)) {
        json_response(['error' => 'Type must be specimen or page'], 400);
    }

    $entities = TagManager::entities((int) $id, $type, $limit);
    $tag = TagManager::get((int) $id);

    json_response(['tag' => $tag, 'entities' => $entities, 'type' => $type]);
});
