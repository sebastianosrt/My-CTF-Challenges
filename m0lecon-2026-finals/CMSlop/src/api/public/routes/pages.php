<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Content\PageManager;
use Herbarium\Content\TagManager;
use Herbarium\Content\ContentLifecycle;
use Herbarium\Content\RevisionStore;
use Herbarium\Webhooks\WebhookDispatcher;

$router->get('/api/pages', function () {
    JwtAuth::requireAuth();

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $status  = $_GET['status'] ?? null;
    $search  = $_GET['search'] ?? null;

    $result = PageManager::list($page, $perPage, $status, $search);
    json_response($result);
});

$router->post('/api/pages', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body  = json_decode(file_get_contents('php://input'), true);
    $title = $body['title'] ?? '';
    $text  = $body['body'] ?? '';
    $status = $body['status'] ?? 'draft';

    if (empty(trim($title))) {
        json_response(['error' => 'Title is required'], 400);
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    $id = PageManager::create($title, $text, $userId, $status);

    if (!empty($body['tag_ids']) && is_array($body['tag_ids'])) {
        PageManager::attachTags($id, $body['tag_ids']);
    }

    $audit->record('page_created', $userId, "page={$id}");
    WebhookDispatcher::dispatch('page.created', ['id' => $id]);
    json_response(['message' => 'Page created', 'id' => $id, 'page' => PageManager::get($id)]);
});

$router->get('/api/pages/{id}', function (string $id) {
    JwtAuth::requireAuth();

    $page = PageManager::get((int) $id);
    if ($page === null) {
        json_response(['error' => 'Page not found'], 404);
    }

    json_response(['page' => $page]);
});

$router->put('/api/pages/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body  = json_decode(file_get_contents('php://input'), true);
    $title = $body['title'] ?? '';
    $text  = $body['body'] ?? '';

    if (empty(trim($title))) {
        json_response(['error' => 'Title is required'], 400);
    }

    if (!PageManager::update((int) $id, $title, $text, $userId)) {
        json_response(['error' => 'Page not found'], 404);
    }

    if (isset($body['tag_ids']) && is_array($body['tag_ids'])) {
        PageManager::attachTags((int) $id, $body['tag_ids']);
    }

    $audit->record('page_updated', $userId, "page={$id}");
    WebhookDispatcher::dispatch('page.updated', ['id' => (int)$id]);
    json_response(['message' => 'Page updated', 'page' => PageManager::get((int) $id)]);
});

$router->delete('/api/pages/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::requireAuth();
        $userId = (int) $claims->sub;

        if (!PageManager::delete((int) $id)) {
            json_response(['error' => 'Page not found'], 404);
        }

        $audit->record('page_deleted', $userId, "page={$id}");
        WebhookDispatcher::dispatch('page.deleted', ['id' => (int)$id]);
        json_response(['message' => 'Page deleted']);
    },
    [RouteGuard::admin()]
));

$router->put('/api/pages/{id}/status', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body   = json_decode(file_get_contents('php://input'), true);
    $status = $body['status'] ?? '';

    if (empty($status)) {
        json_response(['error' => 'Status is required'], 400);
    }

    if (!ContentLifecycle::transition('page', (int) $id, $status, $userId)) {
        json_response(['error' => 'Invalid status transition'], 400);
    }

    $audit->record('page_status_changed', $userId, "page={$id},status={$status}");
    WebhookDispatcher::dispatch('page.status_changed', ['id' => (int)$id, 'status' => $status]);
    json_response(['message' => 'Page status updated', 'page' => PageManager::get((int) $id)]);
});

$router->get('/api/pages/{id}/revisions', function (string $id) {
    JwtAuth::requireAuth();

    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $revisions = RevisionStore::forEntity('page', (int) $id, $limit);
    json_response(['revisions' => $revisions]);
});

$router->post('/api/pages/{id}/revisions/{revId}/restore', function (string $id, string $revId) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!RevisionStore::restore((int) $revId, $userId)) {
        json_response(['error' => 'Revision not found'], 404);
    }

    $audit->record('page_revision_restored', $userId, "page={$id},revision={$revId}");
    json_response(['message' => 'Revision restored', 'page' => PageManager::get((int) $id)]);
});

$router->post('/api/revisions/{revId}/restore', function (string $revId) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!RevisionStore::restore((int) $revId, $userId)) {
        json_response(['error' => 'Revision not found'], 404);
    }

    $revision = RevisionStore::get((int) $revId);
    $audit->record('revision_restored', $userId, "revision={$revId}");
    json_response(['message' => 'Revision restored']);
});
