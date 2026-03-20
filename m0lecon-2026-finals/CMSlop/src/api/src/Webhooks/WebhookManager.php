<?php

namespace Herbarium\Webhooks;

use Herbarium\Core\Database;

class WebhookManager
{
    public static function create(string $url, string $events, int $userId): int
    {
        $secret = bin2hex(random_bytes(32));

        Database::preparedExec(
            "INSERT INTO webhooks (url, secret, events, created_by) VALUES (?, ?, ?, ?)",
            [$url, $secret, $events, $userId]
        );

        return (int) Database::lastInsertId();
    }

    public static function update(int $id, string $url, string $events, bool $isActive): bool
    {
        $affected = Database::preparedExec(
            "UPDATE webhooks SET url = ?, events = ?, is_active = ? WHERE id = ?",
            [$url, $events, $isActive ? 1 : 0, $id]
        );
        return $affected > 0;
    }

    public static function delete(int $id): bool
    {
        Database::preparedExec("DELETE FROM webhook_logs WHERE webhook_id = ?", [$id]);
        $affected = Database::preparedExec("DELETE FROM webhooks WHERE id = ?", [$id]);
        return $affected > 0;
    }

    public static function list(): array
    {
        return Database::prepared(
            "SELECT w.*, u.username as created_by_name
             FROM webhooks w
             LEFT JOIN users u ON w.created_by = u.id
             ORDER BY w.created_at DESC"
        );
    }

    public static function get(int $id): ?array
    {
        return Database::preparedFirst(
            "SELECT w.*, u.username as created_by_name
             FROM webhooks w
             LEFT JOIN users u ON w.created_by = u.id
             WHERE w.id = ?",
            [$id]
        );
    }

    public static function getForEvent(string $event): array
    {
        $rows = Database::prepared(
            "SELECT * FROM webhooks WHERE is_active = 1"
        );

        return array_values(array_filter($rows, function ($row) use ($event) {
            $events = array_map('trim', explode(',', $row['events']));
            return in_array($event, $events, true) || in_array('*', $events, true);
        }));
    }

    public static function logs(int $webhookId, int $limit = 50): array
    {
        return Database::prepared(
            "SELECT * FROM webhook_logs WHERE webhook_id = ? ORDER BY created_at DESC LIMIT ?",
            [$webhookId, $limit]
        );
    }

    public static function logDelivery(int $webhookId, string $event, string $payload, ?int $responseStatus, ?string $responseBody, bool $success): void
    {
        Database::preparedExec(
            "INSERT INTO webhook_logs (webhook_id, event, payload, response_status, response_body, success)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$webhookId, $event, $payload, $responseStatus, $responseBody, $success ? 1 : 0]
        );
    }
}
