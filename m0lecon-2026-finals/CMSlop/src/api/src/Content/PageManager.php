<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class PageManager
{
    public static function create(string $title, string $body, int $authorId, string $status = 'draft'): int
    {
        $slug = SlugGenerator::unique($title, 'pages');

        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        Database::preparedExec(
            "INSERT INTO pages (title, slug, body, status, author_id, published_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$title, $slug, $body, $status, $authorId, $publishedAt]
        );

        $id = (int) Database::lastInsertId();

        RevisionStore::record('page', $id, $authorId, $title, $body, 'Page created');

        return $id;
    }

    public static function update(int $id, string $title, string $body, int $userId): bool
    {
        $page = self::get($id);
        if ($page === null) {
            return false;
        }

        $slug = SlugGenerator::unique($title, 'pages', $id);

        Database::preparedExec(
            "UPDATE pages SET title = ?, slug = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$title, $slug, $body, $id]
        );

        RevisionStore::record('page', $id, $userId, $title, $body, 'Page updated');

        return true;
    }

    public static function delete(int $id): bool
    {
        Database::preparedExec(
            "DELETE FROM taggables WHERE taggable_id = ? AND taggable_type = 'page'",
            [$id]
        );

        $affected = Database::preparedExec("DELETE FROM pages WHERE id = ?", [$id]);
        return $affected > 0;
    }

    public static function get(int $id): ?array
    {
        $page = Database::preparedFirst("SELECT * FROM pages WHERE id = ?", [$id]);
        if ($page === null) {
            return null;
        }

        $page['tags'] = TagManager::forEntity($id, 'page');
        return $page;
    }

    public static function getBySlug(string $slug): ?array
    {
        $page = Database::preparedFirst("SELECT * FROM pages WHERE slug = ?", [$slug]);
        if ($page === null) {
            return null;
        }

        $page['tags'] = TagManager::forEntity((int) $page['id'], 'page');
        return $page;
    }

    public static function list(int $page = 1, int $perPage = 20, ?string $status = null, ?string $search = null): array
    {
        $where = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($search !== null && $search !== '') {
            $like = "%{$search}%";
            $where[] = '(p.title LIKE ? OR p.body LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;

        $total = (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM pages p {$whereSql}",
            $params
        );

        $rows = Database::prepared(
            "SELECT p.*, u.username as author_name
             FROM pages p
             LEFT JOIN users u ON p.author_id = u.id
             {$whereSql}
             ORDER BY p.updated_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'pages'      => $rows,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    public static function attachTags(int $id, array $tagIds): void
    {
        Database::preparedExec(
            "DELETE FROM taggables WHERE taggable_id = ? AND taggable_type = 'page'",
            [$id]
        );

        foreach ($tagIds as $tagId) {
            TagManager::tag((int) $tagId, $id, 'page');
        }
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
