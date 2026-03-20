<?php

namespace Herbarium\ApiKeys;

class ApiKeyAuth
{
    public static function extractFromHeader(): ?array
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $keyData = ApiKeyManager::verify($apiKey);

        if ($keyData === null) {
            return null;
        }

        ApiKeyManager::updateLastUsed((int) $keyData['id']);

        return $keyData;
    }

    public static function requireApiKey(string $permission = 'read'): array
    {
        $keyData = self::extractFromHeader();

        if ($keyData === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Valid API key required (X-Api-Key header)']);
            exit;
        }

        $grantedPermissions = array_map('trim', explode(',', $keyData['permissions']));

        if (!in_array($permission, $grantedPermissions, true) && !in_array('admin', $grantedPermissions, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => "API key lacks '{$permission}' permission"]);
            exit;
        }

        return $keyData;
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
