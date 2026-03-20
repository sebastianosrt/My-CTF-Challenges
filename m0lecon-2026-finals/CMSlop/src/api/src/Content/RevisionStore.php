<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class RevisionStore
{
    public static function record(
        string $entityType,
        int $entityId,
        int $userId,
        ?string $title,
        ?string $body,
        string $diffSummary = ''
    ): int {
        Database::preparedExec(
            "INSERT INTO revisions (entity_type, entity_id, user_id, title, body, diff_summary)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$entityType, $entityId, $userId, $title, $body, $diffSummary]
        );
        return (int) Database::lastInsertId();
    }

    public static function forEntity(string $entityType, int $entityId, int $limit = 20): array
    {
        return Database::prepared(
            "SELECT r.*, u.username
             FROM revisions r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.entity_type = ? AND r.entity_id = ?
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$entityType, $entityId, $limit]
        );
    }

    public static function get(int $id): ?array
    {
        return Database::preparedFirst(
            "SELECT r.*, u.username
             FROM revisions r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.id = ?",
            [$id]
        );
    }

    public static function restore(int $revisionId, int $userId): bool
    {
        $revision = self::get($revisionId);
        if ($revision === null) {
            return false;
        }

        $type = $revision['entity_type'];
        $entityId = (int) $revision['entity_id'];

        if ($type === 'page') {
            $sets = [];
            $params = [];

            if ($revision['title'] !== null) {
                $sets[] = 'title = ?';
                $params[] = $revision['title'];
            }
            if ($revision['body'] !== null) {
                $sets[] = 'body = ?';
                $params[] = $revision['body'];
            }
            $sets[] = 'updated_at = CURRENT_TIMESTAMP';

            if (empty($sets)) {
                return false;
            }

            $params[] = $entityId;
            Database::preparedExec(
                "UPDATE pages SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
        } elseif ($type === 'specimen') {
            $sets = [];
            $params = [];

            if ($revision['title'] !== null) {
                $sets[] = 'common_name = ?';
                $params[] = $revision['title'];
            }
            if ($revision['body'] !== null) {
                $sets[] = 'description = ?';
                $params[] = $revision['body'];
            }
            $sets[] = 'updated_at = CURRENT_TIMESTAMP';

            if (empty($sets)) {
                return false;
            }

            $params[] = $entityId;
            Database::preparedExec(
                "UPDATE specimens SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
        } else {
            return false;
        }

        self::record(
            $type,
            $entityId,
            $userId,
            $revision['title'],
            $revision['body'],
            "Restored from revision #{$revisionId}"
        );

        return true;
    }

    public static function prune(string $entityType, int $entityId, int $keep = 20): int
    {
        $total = (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM revisions WHERE entity_type = ? AND entity_id = ?",
            [$entityType, $entityId]
        );

        if ($total <= $keep) {
            return 0;
        }

        $toDelete = $total - $keep;
        return Database::preparedExec(
            "DELETE FROM revisions WHERE id IN (
                SELECT id FROM revisions
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY created_at ASC
                LIMIT ?
            )",
            [$entityType, $entityId, $toDelete]
        );
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
