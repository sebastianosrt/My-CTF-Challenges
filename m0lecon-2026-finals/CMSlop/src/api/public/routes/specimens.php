<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Specimens\SpecimenExporter;
use Herbarium\Specimens\SpecimenAnnotator;
use Herbarium\Content\RevisionStore;
use Herbarium\Content\TagManager;
use Herbarium\Content\SlugGenerator;
use Herbarium\Content\ContentLifecycle;
use Herbarium\Webhooks\WebhookDispatcher;

$router->get('/api/specimens', function () {
    JwtAuth::requireAuth();

    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $search = $_GET['search'] ?? '';
    $where  = '';
    $params = [];
    if (!empty($search)) {
        $like   = "%{$search}%";
        $where  = "WHERE common_name LIKE ? OR species LIKE ? OR family LIKE ?";
        $params = [$like, $like, $like];
    }

    $total = Database::prepared("SELECT COUNT(*) as cnt FROM specimens {$where}", $params);
    $rows  = Database::prepared(
        "SELECT * FROM specimens {$where} ORDER BY imported_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    json_response([
        'specimens'  => $rows,
        'pagination' => [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => (int) $total[0]['cnt'],
            'pages'    => (int) ceil($total[0]['cnt'] / $perPage),
        ],
    ]);
});

$router->post('/api/specimens', function () use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);

    $commonName = trim($body['common_name'] ?? '');
    if (empty($commonName)) {
        json_response(['error' => 'common_name is required'], 400);
    }

    $slug = SlugGenerator::unique($commonName, 'specimens');

    $fields = [
        'common_name', 'species', 'family', 'genus', 'location_found',
        'habitat', 'collected_date', 'collector', 'description', 'preservation_method',
    ];
    $cols   = ['common_name', 'slug', 'imported_by', 'source'];
    $vals   = [$commonName, $slug, $userId, 'manual'];
    $placeholders = ['?', '?', '?', '?'];

    foreach ($fields as $f) {
        if ($f === 'common_name') continue;
        if (isset($body[$f])) {
            $cols[]         = $f;
            $vals[]         = $body[$f];
            $placeholders[] = '?';
        }
    }

    Database::preparedExec(
        "INSERT INTO specimens (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")",
        $vals
    );
    $specimenId = (int) Database::lastInsertId();

    if (isset($body['tag_ids']) && is_array($body['tag_ids'])) {
        foreach ($body['tag_ids'] as $tagId) {
            TagManager::tag((int) $tagId, $specimenId, 'specimen');
        }
    }

    $audit->record('specimen_created', $userId, "specimen={$specimenId}");
    WebhookDispatcher::dispatch('specimen.created', ['id' => $specimenId]);

    $row = Database::preparedFirst("SELECT * FROM specimens WHERE id = ?", [$specimenId]);
    json_response(['message' => 'Specimen created', 'specimen' => $row], 201);
});

$router->get('/api/specimens/{id}', function (string $id) {
    JwtAuth::requireAuth();
    $id = (int) $id;

    $rows = Database::prepared("SELECT * FROM specimens WHERE id = ?", [$id]);
    if (empty($rows)) {
        json_response(['error' => 'Specimen not found'], 404);
    }

    json_response(['specimen' => $rows[0]]);
});

$router->get('/api/specimens/export/summary', RouteGuard::wrap(
    function () {
        $search   = $_GET['search'] ?? null;
        $exporter = SpecimenExporter::fromQuery($search);

        $family = $_GET['family'] ?? null;
        if ($family !== null && $family !== '') {
            $exporter->filter(function ($s) use ($family) {
                return strcasecmp($s['family'] ?? '', $family) === 0;
            });
        }

        $summary = $exporter->summarize();

        json_response([
            'summary'    => $summary,
            'specimens'  => $exporter->toArray(),
        ]);
    },
    [RouteGuard::auth(), RouteGuard::audit('export_summary')]
));

$router->get('/api/specimens/export/{format}', function (string $format) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $search = $_GET['search'] ?? null;

    $exporter = SpecimenExporter::fromQuery($search);

    $audit->record('export', $userId, "format={$format},count={$exporter->count()}");

    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="herbarium_export.csv"');
            echo $exporter->toCsv();
            exit;

        case 'xml':
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="herbarium_export.xml"');
            echo $exporter->toXml();
            exit;

        case 'json':
        default:
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="herbarium_export.json"');
            echo $exporter->toJson();
            exit;
    }
});

$router->post('/api/specimens/validate', RouteGuard::wrap(
    function () {
        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body) || !is_array($body)) {
            json_response(['error' => 'JSON body with specimen fields required'], 400);
        }

        $pipeline = new \Herbarium\Import\ImportPipeline([
            'required_fields'   => ['common_name'],
            'default_collector' => 'Herbarium Import',
            'allow_duplicates'  => false,
        ]);

        $sanitizer   = new \Herbarium\Processors\SanitizationProcessor(
            ['source' => 'preview'], $body, $pipeline->getComponents()
        );
        $validator   = new \Herbarium\Processors\ValidationProcessor(
            ['source' => 'preview'], $body, $pipeline->getComponents()
        );
        $normalizer  = new \Herbarium\Processors\NormalizationProcessor(
            ['source' => 'preview'], $body, $pipeline->getComponents()
        );
        $enricher    = new \Herbarium\Processors\EnrichmentProcessor(
            ['source' => 'preview'], $body, $pipeline->getComponents()
        );
        $dedup       = new \Herbarium\Processors\DeduplicationProcessor(
            ['source' => 'preview'], $body, $pipeline->getComponents()
        );

        $errors           = $validator->validate($body);
        $isValid          = $validator->isValid($body);
        $needsSanitize    = $sanitizer->needsSanitization($body);
        $needsNorm        = $normalizer->needsNormalization($body);
        $missingFields    = $enricher->getMissingFields($body);
        $defaults         = $enricher->getDefaults();
        $isDuplicate      = false;

        if (!empty($body['species']) && !empty($body['location_found'])) {
            $isDuplicate = $dedup->isDuplicate($body['species'], $body['location_found']);
        }

        $preview = $sanitizer->process($body);
        if ($preview !== null) {
            $preview = $normalizer->process($preview);
        }
        if ($preview !== null) {
            $preview = $enricher->process($preview);
        }

        json_response([
            'valid'            => $isValid,
            'errors'           => $errors,
            'needs_sanitize'   => $needsSanitize,
            'needs_normalize'  => $needsNorm,
            'missing_fields'   => $missingFields,
            'defaults'         => $defaults,
            'is_duplicate'     => $isDuplicate,
            'field_limits'     => $sanitizer->getLimits(),
            'preview'          => $preview,
        ]);
    },
    [RouteGuard::auth()]
));

$router->put('/api/specimens/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body = json_decode(file_get_contents('php://input'), true);
    $specimenId = (int) $id;

    $row = Database::preparedFirst("SELECT * FROM specimens WHERE id = ?", [$specimenId]);
    if ($row === null) {
        json_response(['error' => 'Specimen not found'], 404);
    }

    RevisionStore::record(
        'specimen',
        $specimenId,
        $userId,
        $row['common_name'],
        $row['description'],
        'Specimen updated'
    );

    $fields = ['common_name', 'species', 'family', 'genus', 'location_found',
               'habitat', 'collected_date', 'collector', 'description', 'preservation_method'];
    $sets = ['updated_at = CURRENT_TIMESTAMP'];
    $params = [];

    foreach ($fields as $field) {
        if (isset($body[$field])) {
            $sets[] = "{$field} = ?";
            $params[] = $body[$field];
        }
    }

    if (isset($body['common_name'])) {
        $slug = SlugGenerator::unique($body['common_name'], 'specimens', $specimenId);
        $sets[] = 'slug = ?';
        $params[] = $slug;
    }

    $params[] = $specimenId;
    Database::preparedExec(
        "UPDATE specimens SET " . implode(', ', $sets) . " WHERE id = ?",
        $params
    );

    if (isset($body['tag_ids']) && is_array($body['tag_ids'])) {
        Database::preparedExec(
            "DELETE FROM taggables WHERE taggable_id = ? AND taggable_type = 'specimen'",
            [$specimenId]
        );
        foreach ($body['tag_ids'] as $tagId) {
            TagManager::tag((int) $tagId, $specimenId, 'specimen');
        }
    }

    $audit->record('specimen_updated', $userId, "specimen={$id}");
    WebhookDispatcher::dispatch('specimen.updated', ['id' => (int)$id]);
    json_response(['message' => 'Specimen updated']);
});

$router->delete('/api/specimens/{id}', RouteGuard::wrap(
    function (string $id) use ($audit) {
        $claims  = JwtAuth::requireAuth();
        $userId  = (int) $claims->sub;
        $specimenId = (int) $id;

        $row = Database::preparedFirst("SELECT id FROM specimens WHERE id = ?", [$specimenId]);
        if ($row === null) {
            json_response(['error' => 'Specimen not found'], 404);
        }

        Database::preparedExec("DELETE FROM taggables WHERE taggable_id = ? AND taggable_type = 'specimen'", [$specimenId]);
        Database::preparedExec("DELETE FROM annotations WHERE specimen_id = ?", [$specimenId]);
        Database::preparedExec("DELETE FROM collection_specimens WHERE specimen_id = ?", [$specimenId]);
        Database::preparedExec("DELETE FROM specimens WHERE id = ?", [$specimenId]);

        $audit->record('specimen_deleted', $userId, "specimen={$id}");
        WebhookDispatcher::dispatch('specimen.deleted', ['id' => $specimenId]);
        json_response(['message' => 'Specimen deleted']);
    },
    [RouteGuard::admin()]
));

$router->put('/api/specimens/{id}/status', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    $body   = json_decode(file_get_contents('php://input'), true);
    $status = $body['status'] ?? '';

    if (empty($status)) {
        json_response(['error' => 'Status is required'], 400);
    }

    if (!ContentLifecycle::transition('specimen', (int) $id, $status, $userId)) {
        json_response(['error' => 'Invalid status transition'], 400);
    }

    $audit->record('specimen_status_changed', $userId, "specimen={$id},status={$status}");
    WebhookDispatcher::dispatch('specimen.status_changed', ['id' => (int)$id, 'status' => $status]);
    json_response(['message' => 'Specimen status updated']);
});

$router->get('/api/specimens/{id}/tags', function (string $id) {
    JwtAuth::requireAuth();

    $tags = TagManager::forEntity((int) $id, 'specimen');
    json_response(['tags' => $tags]);
});

$router->get('/api/specimens/{id}/revisions', function (string $id) {
    JwtAuth::requireAuth();

    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
    $revisions = RevisionStore::forEntity('specimen', (int) $id, $limit);
    json_response(['revisions' => $revisions]);
});

$router->get('/api/specimens/{id}/annotations', function (string $id) {
    JwtAuth::requireAuth();
    $annotations = SpecimenAnnotator::forSpecimen((int) $id);
    json_response(['annotations' => $annotations, 'count' => count($annotations)]);
});

$router->post('/api/specimens/{id}/annotations', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $body   = json_decode(file_get_contents('php://input'), true);
    $content = $body['content'] ?? '';

    if (empty(trim($content))) {
        json_response(['error' => 'Annotation content is required'], 400);
    }

    $annotator = new SpecimenAnnotator($userId);
    $annotator->add((int) $id, $content);
    $annotator->flush();

    $audit->record('annotation_added', $userId, "specimen={$id}");
    json_response([
        'message' => 'Annotation added',
        'count'   => SpecimenAnnotator::count((int) $id),
    ]);
});
