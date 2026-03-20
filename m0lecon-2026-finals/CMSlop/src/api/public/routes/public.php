<?php

use Herbarium\Core\Database;
use Herbarium\Content\PageManager;
use Herbarium\Content\TagManager;
use Herbarium\ApiKeys\ApiKeyAuth;

$router->get('/api/public/pages', function () {
    ApiKeyAuth::extractFromHeader();
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $total = (int) Database::preparedScalar(
        "SELECT COUNT(*) FROM pages WHERE status = 'published'"
    );

    $rows = Database::prepared(
        "SELECT id, title, slug, body, published_at, updated_at
         FROM pages WHERE status = 'published'
         ORDER BY published_at DESC
         LIMIT ? OFFSET ?",
        [$perPage, $offset]
    );

    foreach ($rows as &$row) {
        $row['tags'] = TagManager::forEntity((int) $row['id'], 'page');
    }
    unset($row);

    json_response([
        'pages'      => $rows,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / max($perPage, 1)),
        ],
    ]);
});

$router->get('/api/public/pages/{slug}', function (string $slug) {
    ApiKeyAuth::extractFromHeader();
    $page = PageManager::getBySlug($slug);

    if ($page === null || $page['status'] !== 'published') {
        json_response(['error' => 'Page not found'], 404);
    }

    json_response(['page' => $page]);
});

$router->get('/api/public/specimens', function () {
    ApiKeyAuth::extractFromHeader();
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $search = $_GET['search'] ?? '';
    $where  = "WHERE status = 'published'";
    $params = [];

    if (!empty($search)) {
        $like = "%{$search}%";
        $where .= " AND (common_name LIKE ? OR species LIKE ? OR family LIKE ?)";
        $params = [$like, $like, $like];
    }

    $total = (int) Database::preparedScalar(
        "SELECT COUNT(*) FROM specimens {$where}",
        $params
    );

    $rows = Database::prepared(
        "SELECT id, common_name, species, family, genus, slug, location_found, habitat,
                collected_date, collector, description, preservation_method, imported_at
         FROM specimens {$where}
         ORDER BY imported_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    json_response([
        'specimens'  => $rows,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / max($perPage, 1)),
        ],
    ]);
});

$router->get('/api/public/specimens/{slug}', function (string $slug) {
    ApiKeyAuth::extractFromHeader();
    $specimen = Database::preparedFirst(
        "SELECT * FROM specimens WHERE slug = ? AND status = 'published'",
        [$slug]
    );

    if ($specimen === null) {
        json_response(['error' => 'Specimen not found'], 404);
    }

    $specimen['tags'] = TagManager::forEntity((int) $specimen['id'], 'specimen');
    json_response(['specimen' => $specimen]);
});

$router->get('/api/public/tags', function () {
    ApiKeyAuth::extractFromHeader();
    $tags = TagManager::list();
    json_response(['tags' => $tags]);
});

$router->get('/api/public/tags/{slug}', function (string $slug) {
    ApiKeyAuth::extractFromHeader();
    $tag = TagManager::getBySlug($slug);

    if ($tag === null) {
        json_response(['error' => 'Tag not found'], 404);
    }

    $tagId = (int) $tag['id'];

    $pages = Database::prepared(
        "SELECT p.id, p.title, p.slug, p.published_at
         FROM pages p
         INNER JOIN taggables tg ON p.id = tg.taggable_id AND tg.taggable_type = 'page'
         WHERE tg.tag_id = ? AND p.status = 'published'
         ORDER BY p.published_at DESC
         LIMIT 20",
        [$tagId]
    );

    $specimens = Database::prepared(
        "SELECT s.id, s.common_name, s.slug, s.species, s.family
         FROM specimens s
         INNER JOIN taggables tg ON s.id = tg.taggable_id AND tg.taggable_type = 'specimen'
         WHERE tg.tag_id = ? AND s.status = 'published'
         ORDER BY s.imported_at DESC
         LIMIT 20",
        [$tagId]
    );

    json_response([
        'tag'       => $tag,
        'pages'     => $pages,
        'specimens' => $specimens,
    ]);
});
