<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Webhooks\WebhookManager;
use Herbarium\Webhooks\WebhookDispatcher;

$router->get('/api/admin/webhooks', RouteGuard::wrap(
    function () {
        $webhooks = WebhookManager::list();
        json_response(['webhooks' => $webhooks]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/webhooks', RouteGuard::wrap(
    function () use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $body   = json_decode(file_get_contents('php://input'), true);
        $url    = $body['url'] ?? '';
        $events = $body['events'] ?? '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_response(['error' => 'A valid URL is required'], 400);
        }

        if (empty($events)) {
            json_response(['error' => 'At least one event is required'], 400);
        }

        $id = WebhookManager::create($url, $events, $userId);

        $audit->record('webhook_created', $userId, "webhook={$id},url={$url}");
        json_response(['message' => 'Webhook created', 'id' => $id], 201);
    },
    [RouteGuard::admin()]
));

$router->put('/api/admin/webhooks/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $body     = json_decode(file_get_contents('php://input'), true);
        $url      = $body['url'] ?? '';
        $events   = $body['events'] ?? '';
        $isActive = (bool) ($body['is_active'] ?? true);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_response(['error' => 'A valid URL is required'], 400);
        }

        if (!WebhookManager::update((int) $id, $url, $events, $isActive)) {
            json_response(['error' => 'Webhook not found'], 404);
        }

        $audit->record('webhook_updated', $userId, "webhook={$id}");
        json_response(['message' => 'Webhook updated']);
    },
    [RouteGuard::admin()]
));

$router->delete('/api/admin/webhooks/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        if (!WebhookManager::delete((int) $id)) {
            json_response(['error' => 'Webhook not found'], 404);
        }

        $audit->record('webhook_deleted', $userId, "webhook={$id}");
        json_response(['message' => 'Webhook deleted']);
    },
    [RouteGuard::admin()]
));

$router->get('/api/admin/webhooks/{id}/logs', RouteGuard::wrap(
    function (string $id) {
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $logs  = WebhookManager::logs((int) $id, $limit);
        json_response(['logs' => $logs]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/webhooks/{id}/test', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $webhook = WebhookManager::get((int) $id);
        if ($webhook === null) {
            json_response(['error' => 'Webhook not found'], 404);
        }

        $dispatched = WebhookDispatcher::dispatch('test', [
            'message'    => 'This is a test webhook delivery',
            'webhook_id' => (int) $id,
            'triggered_by' => $userId,
        ]);

        $audit->record('webhook_test', $userId, "webhook={$id}");
        json_response(['message' => "Test delivery sent to {$dispatched} webhook(s)"]);
    },
    [RouteGuard::admin()]
));
