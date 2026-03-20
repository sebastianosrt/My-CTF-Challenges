<?php

use Herbarium\Core\Database;
use Herbarium\Auth\JwtAuth;
use Herbarium\Auth\RouteGuard;
use Herbarium\Core\AuditLogger;
use Herbarium\Content\ReportGenerator;

$router->get('/api/stats', function () use ($audit) {
    JwtAuth::requireAuth();

    $specimens = Database::countRows('specimens');
    $families  = (int) Database::scalar("SELECT COUNT(DISTINCT family) FROM specimens");
    $genera    = (int) Database::scalar("SELECT COUNT(DISTINCT genus) FROM specimens");
    $imports   = Database::countRows('import_logs');
    $audits    = Database::countRows('audit_log');
    $pages     = Database::countRows('pages');
    $tags      = Database::countRows('tags');

    $recentImports = Database::prepared(
        "SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5"
    );

    $recentPages = Database::prepared(
        "SELECT p.*, u.username as author_name FROM pages p LEFT JOIN users u ON p.author_id = u.id ORDER BY p.updated_at DESC LIMIT 5"
    );

    $recentAudit = AuditLogger::recent(5);

    json_response([
        'total_specimens' => $specimens,
        'total_families'  => $families,
        'total_genera'    => $genera,
        'total_imports'   => $imports,
        'total_audits'    => $audits,
        'total_pages'     => $pages,
        'total_tags'      => $tags,
        'pending_audits'  => $audit->count(),
        'recent_imports'  => $recentImports,
        'recent_pages'    => $recentPages,
        'recent_audit'    => $recentAudit,
    ]);
});

$router->get('/api/reports/full', RouteGuard::wrap(
    function () use ($audit, $cache) {
        $claims = JwtAuth::extractFromHeader();
        $userId = $claims ? (int) $claims->sub : null;

        $report = $cache->remember('report:full', 300, function () {
            $gen = new ReportGenerator();
            return $gen->generate();
        });

        $audit->record('report_generated', $userId, 'type=full');
        json_response($report);
    },
    [RouteGuard::auth()]
));

$router->get('/api/reports/families', RouteGuard::wrap(
    function () use ($cache) {
        $data = $cache->remember('report:families', 300, function () {
            $gen = new ReportGenerator();
            return $gen->familyDistribution();
        });
        json_response(['families' => $data]);
    },
    [RouteGuard::auth()]
));

$router->get('/api/reports/timeline', RouteGuard::wrap(
    function () use ($cache) {
        $data = $cache->remember('report:timeline', 300, function () {
            $gen = new ReportGenerator();
            return $gen->collectionTimeline();
        });
        json_response(['timeline' => $data]);
    },
    [RouteGuard::auth()]
));

$router->get('/api/reports/collectors', RouteGuard::wrap(
    function () {
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
        $gen = new ReportGenerator();
        json_response(['collectors' => $gen->topCollectors($limit)]);
    },
    [RouteGuard::auth()]
));

$router->get('/api/reports/habitats', RouteGuard::wrap(
    function () use ($cache) {
        $data = $cache->remember('report:habitats', 300, function () {
            $gen = new ReportGenerator();
            return $gen->habitatSummary();
        });
        json_response(['habitats' => $data]);
    },
    [RouteGuard::auth()]
));

$router->get('/api/reports/sources', RouteGuard::wrap(
    function () {
        $gen = new ReportGenerator();
        json_response(['sources' => $gen->sourceBreakdown()]);
    },
    [RouteGuard::auth()]
));
