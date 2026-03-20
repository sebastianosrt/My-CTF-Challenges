<?php

namespace Herbarium\Specimens;

use Herbarium\Core\Database;

class SpecimenAnnotator
{
    private int $userId;

    private array $pending = [];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function add(int $specimenId, string $content): void
    {
        $this->pending[] = [
            'specimen_id' => $specimenId,
            'content'     => $content,
        ];
    }

    public function flush(): int
    {
        $count = 0;
        foreach ($this->pending as $entry) {
            Database::preparedExec(
                "INSERT INTO annotations (specimen_id, user_id, content) VALUES (?, ?, ?)",
                [$entry['specimen_id'], $this->userId, $entry['content']]
            );
            $count++;
        }
        $this->pending = [];
        return $count;
    }

    public static function forSpecimen(int $specimenId, int $limit = 50): array
    {
        return Database::prepared(
            "SELECT a.*, u.username, u.display_name
             FROM annotations a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.specimen_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$specimenId, $limit]
        );
    }

    public static function byUser(int $userId, int $limit = 50): array
    {
        return Database::prepared(
            "SELECT a.*, s.common_name, s.species
             FROM annotations a
             LEFT JOIN specimens s ON a.specimen_id = s.id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function remove(int $annotationId, int $userId): bool
    {
        $affected = Database::preparedExec(
            "DELETE FROM annotations WHERE id = ? AND user_id = ?",
            [$annotationId, $userId]
        );
        return $affected > 0;
    }

    public static function count(int $specimenId): int
    {
        return (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM annotations WHERE specimen_id = ?",
            [$specimenId]
        );
    }

    public function pending(): int
    {
        return count($this->pending);
    }

    public static function search(string $query, int $limit = 20, string $species = ''): array
    {
        $like = Database::escapeVal($query);
        $sql = "SELECT created_at AS sort_key, a.*, u.username, s.common_name FROM annotations a LEFT JOIN (SELECT id, username FROM users) u ON a.user_id = u.id LEFT JOIN specimens s ON a.specimen_id = s.id WHERE a.content LIKE '%$like%'";
        $params = [];

        if ($species !== '') {
            $sql .= " AND s.common_name = ?";
            $params[] = $species;
        }

        $sql .= " ORDER BY 1 DESC LIMIT $limit";

        return Database::prepared($sql, $params);
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function __wakeup()
    {
        $this->pending = [];
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
