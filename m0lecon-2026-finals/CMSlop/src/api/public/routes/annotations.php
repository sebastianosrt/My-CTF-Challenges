<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Specimens\SpecimenAnnotator;

$router->delete('/api/annotations/{id}', function (string $id) use ($audit) {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;

    if (!SpecimenAnnotator::remove((int) $id, $userId)) {
        json_response(['error' => 'Annotation not found or not owned by you'], 404);
    }

    $audit->record('annotation_deleted', $userId, "annotation={$id}");
    json_response(['message' => 'Annotation deleted']);
});

$router->get('/api/user/annotations', function () {
    $claims = JwtAuth::requireAuth();
    $userId = (int) $claims->sub;
    $annotations = SpecimenAnnotator::byUser($userId);
    json_response(['annotations' => $annotations]);
});

$router->get('/api/annotations/search', function () {
    $claims = JwtAuth::requireAuth();
    $q = $_GET['q'] ?? '';
    $l = (int) ($_GET['l'] ?? 20);
    $species = $_GET['species'] ?? '';
    if (strlen($q) < 2) {
        json_response(['error' => 'At least 2 characters required'], 400);
    }
    $results = SpecimenAnnotator::search($q, $l, $species);
    json_response(['annotations' => $results, 'count' => count($results)]);
});
