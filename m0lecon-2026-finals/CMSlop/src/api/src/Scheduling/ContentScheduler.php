<?php

namespace Herbarium\Scheduling;

use Herbarium\Core\Database;
use Herbarium\Content\ContentLifecycle;

class ContentScheduler
{
    public static function schedule(string $entityType, int $entityId, string $action, string $scheduledFor, int $userId): int
    {
        Database::preparedExec(
            "INSERT INTO scheduled_actions (entity_type, entity_id, action, scheduled_for, created_by)
             VALUES (?, ?, ?, ?, ?)",
            [$entityType, $entityId, $action, $scheduledFor, $userId]
        );

        return (int) Database::lastInsertId();
    }

    public static function cancel(int $id): bool
    {
        $affected = Database::preparedExec(
            "UPDATE scheduled_actions SET status = 'cancelled' WHERE id = ? AND status = 'pending'",
            [$id]
        );
        return $affected > 0;
    }

    public static function pending(?string $entityType = null): array
    {
        if ($entityType !== null) {
            return Database::prepared(
                "SELECT sa.*, u.username as created_by_name
                 FROM scheduled_actions sa
                 LEFT JOIN users u ON sa.created_by = u.id
                 WHERE sa.status = 'pending' AND sa.entity_type = ?
                 ORDER BY sa.scheduled_for ASC",
                [$entityType]
            );
        }

        return Database::prepared(
            "SELECT sa.*, u.username as created_by_name
             FROM scheduled_actions sa
             LEFT JOIN users u ON sa.created_by = u.id
             WHERE sa.status = 'pending'
             ORDER BY sa.scheduled_for ASC"
        );
    }

    public static function forEntity(string $entityType, int $entityId): array
    {
        return Database::prepared(
            "SELECT sa.*, u.username as created_by_name
             FROM scheduled_actions sa
             LEFT JOIN users u ON sa.created_by = u.id
             WHERE sa.entity_type = ? AND sa.entity_id = ?
             ORDER BY sa.scheduled_for DESC",
            [$entityType, $entityId]
        );
    }

    public static function executeDue(): int
    {
        $now = date('Y-m-d H:i:s');
        $pending = Database::prepared(
            "SELECT * FROM scheduled_actions WHERE status = 'pending' AND scheduled_for <= ?",
            [$now]
        );

        $executed = 0;

        foreach ($pending as $action) {
            $success = ContentLifecycle::transition(
                $action['entity_type'],
                (int) $action['entity_id'],
                $action['action'],
                (int) $action['created_by']
            );

            if ($success) {
                Database::preparedExec(
                    "UPDATE scheduled_actions SET status = 'executed', executed_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [(int) $action['id']]
                );
                $executed++;
            } else {
                Database::preparedExec(
                    "UPDATE scheduled_actions SET status = 'failed' WHERE id = ?",
                    [(int) $action['id']]
                );
            }
        }

        return $executed;
    }

    public static function list(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $total = Database::countRows('scheduled_actions');

        $rows = Database::prepared(
            "SELECT sa.*, u.username as created_by_name
             FROM scheduled_actions sa
             LEFT JOIN users u ON sa.created_by = u.id
             ORDER BY sa.created_at DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );

        return [
            'actions'    => $rows,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }
}
