<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\ApiKeys\ApiKeyManager;

$router->get('/api/admin/apikeys', RouteGuard::wrap(
    function () {
        $keys = ApiKeyManager::list();
        json_response(['keys' => $keys]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/apikeys', RouteGuard::wrap(
    function () use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $body = json_decode(file_get_contents('php://input'), true);
        $name = $body['name'] ?? '';
        $permissions = $body['permissions'] ?? 'read';
        $expiresAt = $body['expires_at'] ?? null;

        if (empty(trim($name))) {
            json_response(['error' => 'Key name is required'], 400);
        }

        $allowedPermissions = ['read', 'write', 'admin'];
        $permList = array_map('trim', explode(',', $permissions));
        foreach ($permList as $perm) {
            if (!in_array($perm, $allowedPermissions, true)) {
                json_response(['error' => "Invalid permission: {$perm}"], 400);
            }
        }

        $result = ApiKeyManager::create($name, $permissions, $userId, $expiresAt);

        $audit->record('apikey_created', $userId, "key={$result['id']},name={$name}");
        json_response($result, 201);
    },
    [RouteGuard::admin()]
));

$router->delete('/api/admin/apikeys/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        if (!ApiKeyManager::revoke((int) $id)) {
            json_response(['error' => 'API key not found'], 404);
        }

        $audit->record('apikey_revoked', $userId, "key={$id}");
        json_response(['message' => 'API key revoked']);
    },
    [RouteGuard::admin()]
));
