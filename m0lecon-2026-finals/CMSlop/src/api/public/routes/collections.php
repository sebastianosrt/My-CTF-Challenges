<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Specimens\CollectionManager;

$router->get('/api/collections', function () {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $manager = new CollectionManager($userId);
    json_response(['collections' => $manager->list()]);
});

$router->post('/api/collections', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);
    $name   = $body['name'] ?? '';

    if (empty(trim($name))) {
        json_response(['error' => 'Collection name is required'], 400);
    }

    $manager = new CollectionManager($userId);
    $id = $manager->create($name, $body['description'] ?? '');

    $audit->record('collection_created', $userId, "collection={$id}");
    json_response(['message' => 'Collection created', 'id' => $id]);
});

$router->get('/api/collections/{id}', function (string $id) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $manager = new CollectionManager($userId);
    $collection = $manager->get((int) $id);

    if ($collection === null) {
        json_response(['error' => 'Collection not found'], 404);
    }

    json_response(['collection' => $collection]);
});

$router->put('/api/collections/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);

    $manager = new CollectionManager($userId);
    if (!$manager->update((int) $id, $body['name'] ?? '', $body['description'] ?? '')) {
        json_response(['error' => 'Collection not found or not owned by you'], 404);
    }

    $audit->record('collection_updated', $userId, "collection={$id}");
    json_response(['message' => 'Collection updated']);
});

$router->delete('/api/collections/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $manager = new CollectionManager($userId);

    if (!$manager->delete((int) $id)) {
        json_response(['error' => 'Collection not found or not owned by you'], 404);
    }

    $audit->record('collection_deleted', $userId, "collection={$id}");
    json_response(['message' => 'Collection deleted']);
});

$router->post('/api/collections/{id}/specimens', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);
    $specimenId = (int) ($body['specimen_id'] ?? 0);

    if ($specimenId <= 0) {
        json_response(['error' => 'specimen_id is required'], 400);
    }

    $manager = new CollectionManager($userId);
    if (!$manager->addSpecimen((int) $id, $specimenId)) {
        json_response(['error' => 'Collection not found or not owned by you'], 404);
    }

    $audit->record('collection_specimen_added', $userId, "collection={$id},specimen={$specimenId}");
    json_response(['message' => 'Specimen added to collection']);
});

$router->delete('/api/collections/{collectionId}/specimens/{specimenId}', function (string $collectionId, string $specimenId) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $manager = new CollectionManager($userId);

    if (!$manager->removeSpecimen((int) $collectionId, (int) $specimenId)) {
        json_response(['error' => 'Not found or not owned by you'], 404);
    }

    $audit->record('collection_specimen_removed', $userId, "collection={$collectionId},specimen={$specimenId}");
    json_response(['message' => 'Specimen removed from collection']);
});

$router->get('/api/collections/browse/all', RouteGuard::wrap(
    function () {
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
        json_response(['collections' => CollectionManager::allPublic($limit)]);
    },
    [RouteGuard::auth()]
));
