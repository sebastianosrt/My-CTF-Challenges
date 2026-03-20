<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class ContentLifecycle
{
    private static array $transitions = [
        'draft'     => ['published'],
        'published' => ['archived'],
        'archived'  => ['draft'],
    ];

    public static function transition(string $type, int $id, string $newStatus, int $userId): bool
    {
        $table = self::tableFor($type);
        if ($table === null) {
            return false;
        }

        $row = Database::preparedFirst("SELECT * FROM {$table} WHERE id = ?", [$id]);
        if ($row === null) {
            return false;
        }

        $currentStatus = $row['status'] ?? 'draft';
        if (!self::isValidTransition($currentStatus, $newStatus)) {
            return false;
        }

        $sets = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
        $params = [$newStatus];

        if ($newStatus === 'published') {
            $sets[] = 'published_at = CURRENT_TIMESTAMP';
        }

        $params = [$id];
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = ?";
        Database::preparedExec($sql, $params);

        RevisionStore::record(
            $type,
            $id,
            $userId,
            $row['title'] ?? $row['common_name'] ?? null,
            $row['body'] ?? $row['description'] ?? null,
            "Status changed: {$currentStatus} -> {$newStatus}"
        );

        return true;
    }

    public static function getAllowedTransitions(string $currentStatus): array
    {
        return self::$transitions[$currentStatus] ?? [];
    }

    public static function isValidTransition(string $from, string $to): bool
    {
        $allowed = self::$transitions[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    public static function getByStatus(string $type, string $status, int $limit = 20, int $offset = 0): array
    {
        $table = self::tableFor($type);
        if ($table === null) {
            return [];
        }

        return Database::prepared(
            "SELECT * FROM {$table} WHERE status = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?",
            [$status, $limit, $offset]
        );
    }

    public static function counts(string $type): array
    {
        $table = self::tableFor($type);
        if ($table === null) {
            return ['draft' => 0, 'published' => 0, 'archived' => 0];
        }

        $rows = Database::prepared(
            "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status"
        );

        $result = ['draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }

        return $result;
    }

    private static function tableFor(string $type): ?string
    {
        $map = [
            'page'     => 'pages',
            'specimen' => 'specimens',
        ];
        return $map[$type] ?? null;
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
