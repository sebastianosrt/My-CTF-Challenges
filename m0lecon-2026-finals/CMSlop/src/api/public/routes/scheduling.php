<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Scheduling\ContentScheduler;

$router->get('/api/scheduled', function () {
    JwtAuth::requireAuth();

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));

    $result = ContentScheduler::list($page, $perPage);
    json_response($result);
});

$router->post('/api/pages/{id}/schedule', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body = json_decode(file_get_contents('php://input'), true);
    $action      = $body['action'] ?? '';
    $scheduledFor = $body['scheduled_for'] ?? '';

    if (empty($action) || empty($scheduledFor)) {
        json_response(['error' => 'action and scheduled_for are required'], 400);
    }

    $allowedActions = ['published', 'archived', 'draft'];
    if (!in_array($action, $allowedActions, true)) {
        json_response(['error' => 'Invalid action'], 400);
    }

    $schedId = ContentScheduler::schedule('page', (int) $id, $action, $scheduledFor, $userId);

    $audit->record('schedule_created', $userId, "page={$id},action={$action},for={$scheduledFor}");
    json_response(['message' => 'Action scheduled', 'id' => $schedId]);
});

$router->post('/api/specimens/{id}/schedule', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body = json_decode(file_get_contents('php://input'), true);
    $action      = $body['action'] ?? '';
    $scheduledFor = $body['scheduled_for'] ?? '';

    if (empty($action) || empty($scheduledFor)) {
        json_response(['error' => 'action and scheduled_for are required'], 400);
    }

    $schedId = ContentScheduler::schedule('specimen', (int) $id, $action, $scheduledFor, $userId);

    $audit->record('schedule_created', $userId, "specimen={$id},action={$action},for={$scheduledFor}");
    json_response(['message' => 'Action scheduled', 'id' => $schedId]);
});

$router->delete('/api/scheduled/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!ContentScheduler::cancel((int) $id)) {
        json_response(['error' => 'Scheduled action not found or already executed'], 404);
    }

    $audit->record('schedule_cancelled', $userId, "scheduled={$id}");
    json_response(['message' => 'Scheduled action cancelled']);
});
