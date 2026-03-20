<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Settings\SettingsManager;

$router->get('/api/settings', RouteGuard::wrap(
    function () {
        $settings = SettingsManager::all();
        json_response(['settings' => $settings]);
    },
    [RouteGuard::admin()]
));

$router->put('/api/settings', RouteGuard::wrap(
    function () use ($audit) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $body = json_decode(file_get_contents('php://input'), true);
        $settings = $body['settings'] ?? [];

        if (empty($settings) || !is_array($settings)) {
            json_response(['error' => 'Settings object is required'], 400);
        }

        $updated = 0;
        foreach ($settings as $key => $value) {
            SettingsManager::set($key, (string) $value, $userId);
            $updated++;
        }

        $audit->record('settings_updated', $userId, "count={$updated}");
        json_response(['message' => "Updated {$updated} settings"]);
    },
    [RouteGuard::admin()]
));

$router->get('/api/public/settings', function () {
    $settings = SettingsManager::getPublic();
    json_response(['settings' => $settings]);
});
