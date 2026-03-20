<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Import\ImportPipeline;
use Herbarium\Import\ImportPipelineRegistry;
use Herbarium\Import\HttpFetcher;
use Herbarium\Webhooks\WebhookDispatcher;

$router->post('/api/admin/import/local', function () use ($audit) {
    $claims = JwtAuth::requireAdmin();
    $userId = (int) $claims->sub;

    $body    = json_decode(file_get_contents('php://input'), true);
    $xmlPath = $body['path'] ?? '/var/www/html/data/specimens.xml';

    $realPath = realpath($xmlPath);
    if ($realPath === false) {
        json_response(['error' => 'Local specimen data file not found'], 404);
    }
    if (!str_starts_with($realPath, '/var/www/html/')) {
        json_response(['error' => 'Access denied: path outside application directory'], 403);
    }

    $xmlContent = file_get_contents($realPath);
    $result     = parseSpecimensXml($xmlContent, $userId, 'local_file');

    if ($result['error']) {
        logImport($userId, 'local', $xmlPath, 0, 'failed');
        $audit->record('import_failed', $userId, "type=local,error={$result['error']}");
        WebhookDispatcher::dispatch('import.failed', ['error' => $result['error']]);
        json_response(['error' => $result['error']], 400);
    }

    logImport($userId, 'local', $xmlPath, $result['count']);
    $audit->record('import_success', $userId, "type=local,count={$result['count']}");
    WebhookDispatcher::dispatch('import.success', ['count' => $result['count']]);

    json_response([
        'message' => "Successfully imported {$result['count']} specimens from local archive",
        'count'   => $result['count'],
    ]);
});

$router->post('/api/admin/import/remote', function () use ($audit, $fetcher) {
    $claims = JwtAuth::requireAdmin();
    $userId = (int) $claims->sub;

    $body = json_decode(file_get_contents('php://input'), true);
    $url  = $body['url'] ?? '';

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        json_response(['error' => 'A valid URL is required'], 400);
    }

    if (!$fetcher->isReachable($url)) {
        $audit->record('import_failed', $userId, "type=remote,url={$url},error=unreachable");
        json_response(['error' => 'Remote URL is not reachable'], 502);
    }

    try {
        $xmlContent = $fetcher->get($url, ['Accept' => 'application/xml, text/xml']);
    } catch (\RuntimeException $e) {
        logImport($userId, 'remote', $url, 0, 'failed');
        $audit->record('import_failed', $userId, "type=remote,url={$url},error={$e->getMessage()}");
        WebhookDispatcher::dispatch('import.failed', ['error' => $e->getMessage()]);
        json_response(['error' => 'Failed to fetch remote XML: ' . $e->getMessage()], 502);
    }

    $result = parseSpecimensXml($xmlContent, $userId, 'remote');

    if ($result['error']) {
        logImport($userId, 'remote', $url, 0, 'failed');
        $audit->record('import_failed', $userId, "type=remote,url={$url}");
        WebhookDispatcher::dispatch('import.failed', ['error' => $result['error']]);
        json_response(['error' => $result['error']], 400);
    }

    logImport($userId, 'remote', $url, $result['count']);
    $audit->record('import_success', $userId, "type=remote,url={$url},count={$result['count']}");
    WebhookDispatcher::dispatch('import.success', ['count' => $result['count']]);

    json_response([
        'message' => "Successfully imported {$result['count']} specimens from remote source",
        'count'   => $result['count'],
    ]);
});

$router->post('/api/admin/import/xml', function () use ($audit) {
    $claims = JwtAuth::requireAdmin();
    $userId = (int) $claims->sub;

    $xmlContent = file_get_contents('php://input');

    if (empty($xmlContent)) {
        json_response(['error' => 'Request body must contain XML'], 400);
    }

    if (strpos($xmlContent, "\x00") !== false) {
        $audit->record('import_blocked', $userId, 'type=xml,reason=null_bytes');
        json_response(['error' => 'XML contains null bytes'], 400);
    }

    if (preg_match('/<!DOCTYPE\b/i', $xmlContent)) {
        $audit->record('import_blocked', $userId, 'type=xml,reason=doctype');
        json_response(['error' => 'XML with DOCTYPE declarations is not allowed'], 400);
    }

    if (strlen($xmlContent) > 200) {
        $audit->record('import_blocked', $userId, 'type=xml,reason=length');
        json_response(['error' => 'File too long'], 400);
    }

    $result = parseSpecimensXml($xmlContent, $userId, 'raw_xml', LIBXML_NOENT | LIBXML_NONET);

    if ($result['error']) {
        logImport($userId, 'xml', 'raw_upload', 0, 'failed');
        $audit->record('import_failed', $userId, "type=xml,error={$result['error']}");
        WebhookDispatcher::dispatch('import.failed', ['error' => $result['error']]);
        json_response(['error' => $result['error']], 400);
    }

    logImport($userId, 'xml', 'raw_upload', $result['count']);
    $audit->record('import_success', $userId, "type=xml,count={$result['count']}");
    WebhookDispatcher::dispatch('import.success', ['count' => $result['count']]);

    json_response([
        'message' => "Successfully imported {$result['count']} specimens from XML",
        'count'   => $result['count'],
    ]);
});

$router->get('/api/admin/imports', RouteGuard::wrap(
    function () {
        $rows = Database::prepared(
            "SELECT il.*, u.username FROM import_logs il LEFT JOIN users u ON il.user_id = u.id ORDER BY il.created_at DESC LIMIT 50"
        );

        json_response(['imports' => $rows]);
    },
    [RouteGuard::admin(), RouteGuard::audit('view_import_logs')]
));

$router->post('/api/admin/import/remote/check', RouteGuard::wrap(
    function () use ($fetcher) {
        $body = json_decode(file_get_contents('php://input'), true);
        $url  = $body['url'] ?? '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            json_response(['error' => 'A valid URL is required'], 400);
        }

        $headResult = $fetcher->head($url, ['Accept' => 'application/xml, text/xml']);
        $reachable  = $headResult['status'] >= 200 && $headResult['status'] < 400;

        $contentType   = '';
        $contentLength = null;
        if (!empty($headResult['headers'])) {
            $ctHeader = $headResult['headers']['Content-Type'] ?? $headResult['headers']['content-type'] ?? [];
            $clHeader = $headResult['headers']['Content-Length'] ?? $headResult['headers']['content-length'] ?? [];
            $contentType   = is_array($ctHeader) ? ($ctHeader[0] ?? '') : $ctHeader;
            $contentLength = is_array($clHeader) ? ($clHeader[0] ?? null) : $clHeader;
        }

        json_response([
            'url'            => $url,
            'reachable'      => $reachable,
            'status'         => $headResult['status'],
            'content_type'   => $contentType,
            'content_length' => $contentLength !== null ? (int) $contentLength : null,
            'error'          => $headResult['error'] ?? null,
        ]);
    },
    [RouteGuard::admin()]
));

$router->get('/api/admin/pipeline', RouteGuard::wrap(
    function () {
        $pipeline = new ImportPipeline([
            'required_fields'   => ['common_name'],
            'default_collector' => 'Herbarium Import',
            'allow_duplicates'  => false,
        ]);

        $registry = new ImportPipelineRegistry(
            [
                'sanitize'    => \Herbarium\Processors\SanitizationProcessor::class,
                'normalize'   => \Herbarium\Processors\NormalizationProcessor::class,
                'validate'    => \Herbarium\Processors\ValidationProcessor::class,
                'enrich'      => \Herbarium\Processors\EnrichmentProcessor::class,
                'deduplicate' => \Herbarium\Processors\DeduplicationProcessor::class,
            ],
            ['source' => 'info'],
            [],
            $pipeline
        );

        $processors = [];
        foreach ($registry->getProcessorNames() as $name) {
            $proc = $registry[$name];
            $processors[] = [
                'key'       => $name,
                'class'     => $proc->getName(),
                'config'    => $proc->getConfig(),
            ];
        }

        json_response([
            'processors' => $processors,
            'components' => $pipeline->toArray(),
            'count'      => count($registry),
        ]);
    },
    [RouteGuard::admin()]
));

$router->post('/api/admin/duplicates', RouteGuard::wrap(
    function () {
        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body['species'])) {
            json_response(['error' => 'species field is required'], 400);
        }

        $pipeline = new ImportPipeline(['allow_duplicates' => false]);
        $dedup    = new \Herbarium\Processors\DeduplicationProcessor(
            [], $body, $pipeline->getComponents()
        );

        $existing = $dedup->getExisting($body['species']);

        json_response([
            'species'   => $body['species'],
            'count'     => count($existing),
            'specimens' => $existing,
        ]);
    },
    [RouteGuard::admin(), RouteGuard::audit('duplicate_check')]
));
