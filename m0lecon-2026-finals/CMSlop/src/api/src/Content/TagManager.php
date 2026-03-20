<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class TagManager
{
    public static function create(string $name, string $description = ''): int
    {
        $slug = SlugGenerator::unique($name, 'tags');

        Database::preparedExec(
            "INSERT INTO tags (name, slug, description) VALUES (?, ?, ?)",
            [$name, $slug, $description]
        );

        return (int) Database::lastInsertId();
    }

    public static function update(int $id, string $name, string $description): bool
    {
        $slug = SlugGenerator::unique($name, 'tags', $id);

        $affected = Database::preparedExec(
            "UPDATE tags SET name = ?, slug = ?, description = ? WHERE id = ?",
            [$name, $slug, $description, $id]
        );

        return $affected > 0;
    }

    public static function delete(int $id): bool
    {
        Database::preparedExec("DELETE FROM taggables WHERE tag_id = ?", [$id]);
        $affected = Database::preparedExec("DELETE FROM tags WHERE id = ?", [$id]);
        return $affected > 0;
    }

    public static function get(int $id): ?array
    {
        return Database::preparedFirst("SELECT * FROM tags WHERE id = ?", [$id]);
    }

    public static function getBySlug(string $slug): ?array
    {
        return Database::preparedFirst("SELECT * FROM tags WHERE slug = ?", [$slug]);
    }

    public static function list(?string $search = null): array
    {
        if ($search !== null && $search !== '') {
            $like = "%{$search}%";
            return Database::prepared(
                "SELECT * FROM tags WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC",
                [$like, $like]
            );
        }

        return Database::prepared("SELECT * FROM tags ORDER BY name ASC");
    }

    public static function tag(int $tagId, int $entityId, string $entityType): bool
    {
        if (Database::preparedExists(
            "SELECT 1 FROM taggables WHERE tag_id = ? AND taggable_id = ? AND taggable_type = ?",
            [$tagId, $entityId, $entityType]
        )) {
            return true;
        }

        Database::preparedExec(
            "INSERT INTO taggables (tag_id, taggable_id, taggable_type) VALUES (?, ?, ?)",
            [$tagId, $entityId, $entityType]
        );

        return true;
    }

    public static function untag(int $tagId, int $entityId, string $entityType): bool
    {
        $affected = Database::preparedExec(
            "DELETE FROM taggables WHERE tag_id = ? AND taggable_id = ? AND taggable_type = ?",
            [$tagId, $entityId, $entityType]
        );
        return $affected > 0;
    }

    public static function forEntity(int $entityId, string $entityType): array
    {
        return Database::prepared(
            "SELECT t.* FROM tags t
             INNER JOIN taggables tg ON t.id = tg.tag_id
             WHERE tg.taggable_id = ? AND tg.taggable_type = ?
             ORDER BY t.name ASC",
            [$entityId, $entityType]
        );
    }

    public static function entities(int $tagId, string $entityType, int $limit = 20): array
    {
        if ($entityType === 'page') {
            return Database::prepared(
                "SELECT p.* FROM pages p
                 INNER JOIN taggables tg ON p.id = tg.taggable_id AND tg.taggable_type = 'page'
                 WHERE tg.tag_id = ?
                 ORDER BY p.updated_at DESC
                 LIMIT ?",
                [$tagId, $limit]
            );
        }

        if ($entityType === 'specimen') {
            return Database::prepared(
                "SELECT s.* FROM specimens s
                 INNER JOIN taggables tg ON s.id = tg.taggable_id AND tg.taggable_type = 'specimen'
                 WHERE tg.tag_id = ?
                 ORDER BY s.imported_at DESC
                 LIMIT ?",
                [$tagId, $limit]
            );
        }

        return [];
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
