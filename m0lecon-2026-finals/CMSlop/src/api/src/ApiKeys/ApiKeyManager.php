<?php

namespace Herbarium\ApiKeys;

use Herbarium\Core\Database;

class ApiKeyManager
{
    public static function create(string $name, string $permissions, int $userId, ?string $expiresAt = null): array
    {
        $plainKey  = 'hbr_' . bin2hex(random_bytes(24));
        $keyHash   = hash('sha256', $plainKey);
        $keyPrefix = substr($plainKey, 0, 8);

        Database::preparedExec(
            "INSERT INTO api_keys (name, key_hash, key_prefix, permissions, created_by, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $keyHash, $keyPrefix, $permissions, $userId, $expiresAt]
        );

        $id = (int) Database::lastInsertId();

        return [
            'id'         => $id,
            'key'        => $plainKey,
            'key_prefix' => $keyPrefix,
            'name'       => $name,
            'permissions' => $permissions,
        ];
    }

    public static function verify(string $apiKey): ?array
    {
        $keyHash = hash('sha256', $apiKey);

        $row = Database::preparedFirst(
            "SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1",
            [$keyHash]
        );

        if ($row === null) {
            return null;
        }

        if ($row['expires_at'] !== null && $row['expires_at'] < date('Y-m-d H:i:s')) {
            return null;
        }

        return $row;
    }

    public static function list(): array
    {
        return Database::prepared(
            "SELECT ak.id, ak.name, ak.key_prefix, ak.permissions, ak.last_used_at,
                    ak.expires_at, ak.is_active, ak.created_at, u.username as created_by_name
             FROM api_keys ak
             LEFT JOIN users u ON ak.created_by = u.id
             ORDER BY ak.created_at DESC"
        );
    }

    public static function revoke(int $id): bool
    {
        $affected = Database::preparedExec(
            "UPDATE api_keys SET is_active = 0 WHERE id = ?",
            [$id]
        );
        return $affected > 0;
    }

    public static function updateLastUsed(int $id): void
    {
        Database::preparedExec(
            "UPDATE api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$id]
        );
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
