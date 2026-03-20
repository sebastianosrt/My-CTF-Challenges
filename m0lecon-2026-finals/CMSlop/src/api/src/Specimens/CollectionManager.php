<?php

namespace Herbarium\Specimens;

use Herbarium\Core\Database;

class CollectionManager
{
    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function create(string $name, string $description = ''): int
    {
        Database::preparedExec(
            "INSERT INTO collections (name, description, user_id) VALUES (?, ?, ?)",
            [$name, $description, $this->userId]
        );
        return (int) Database::lastInsertId();
    }

    public function delete(int $collectionId): bool
    {
        $affected = Database::preparedExec(
            "DELETE FROM collections WHERE id = ? AND user_id = ?",
            [$collectionId, $this->userId]
        );
        if ($affected > 0) {
            Database::preparedExec(
                "DELETE FROM collection_specimens WHERE collection_id = ?",
                [$collectionId]
            );
            return true;
        }
        return false;
    }

    public function update(int $collectionId, string $name, string $description): bool
    {
        $affected = Database::preparedExec(
            "UPDATE collections SET name = ?, description = ? WHERE id = ? AND user_id = ?",
            [$name, $description, $collectionId, $this->userId]
        );
        return $affected > 0;
    }

    public function addSpecimen(int $collectionId, int $specimenId): bool
    {
        $owner = Database::prepared(
            "SELECT id FROM collections WHERE id = ? AND user_id = ?",
            [$collectionId, $this->userId]
        );
        if (empty($owner)) {
            return false;
        }

        Database::preparedExec(
            "INSERT IGNORE INTO collection_specimens (collection_id, specimen_id) VALUES (?, ?)",
            [$collectionId, $specimenId]
        );
        return true;
    }

    public function removeSpecimen(int $collectionId, int $specimenId): bool
    {
        $owner = Database::prepared(
            "SELECT id FROM collections WHERE id = ? AND user_id = ?",
            [$collectionId, $this->userId]
        );
        if (empty($owner)) {
            return false;
        }

        $affected = Database::preparedExec(
            "DELETE FROM collection_specimens WHERE collection_id = ? AND specimen_id = ?",
            [$collectionId, $specimenId]
        );
        return $affected > 0;
    }

    public function list(): array
    {
        return Database::prepared(
            "SELECT c.*, COUNT(cs.specimen_id) as specimen_count
             FROM collections c
             LEFT JOIN collection_specimens cs ON c.id = cs.collection_id
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.created_at DESC",
            [$this->userId]
        );
    }

    public function get(int $collectionId): ?array
    {
        $rows = Database::prepared(
            "SELECT c.* FROM collections c WHERE c.id = ? AND c.user_id = ?",
            [$collectionId, $this->userId]
        );
        if (empty($rows)) {
            return null;
        }
        $collection = $rows[0];
        $collection['specimens'] = Database::prepared(
            "SELECT s.*
             FROM specimens s
             INNER JOIN collection_specimens cs ON s.id = cs.specimen_id
             WHERE cs.collection_id = ?
             ORDER BY cs.added_at DESC",
            [$collectionId]
        );
        return $collection;
    }

    public function specimenCount(int $collectionId): int
    {
        return (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM collection_specimens WHERE collection_id = ?",
            [$collectionId]
        );
    }

    public function hasSpecimen(int $collectionId, int $specimenId): bool
    {
        $row = Database::prepared(
            "SELECT 1 FROM collection_specimens WHERE collection_id = ? AND specimen_id = ? LIMIT 1",
            [$collectionId, $specimenId]
        );
        return !empty($row);
    }

    public static function allPublic(int $limit = 20): array
    {
        return Database::prepared(
            "SELECT c.*, u.username, u.display_name, COUNT(cs.specimen_id) as specimen_count
             FROM collections c
             LEFT JOIN users u ON c.user_id = u.id
             LEFT JOIN collection_specimens cs ON c.id = cs.collection_id
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }
}
