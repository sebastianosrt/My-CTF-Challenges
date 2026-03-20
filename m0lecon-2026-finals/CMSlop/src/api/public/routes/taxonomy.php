<?php

use Herbarium\Auth\JwtAuth;
use Herbarium\Specimens\TaxonomyLookup;

$router->get('/api/taxonomy/search', function () use ($fetcher) {
    JwtAuth::requireAuth();

    $query = $_GET['q'] ?? '';
    $limit = min(20, max(1, (int) ($_GET['limit'] ?? 5)));

    if (empty($query)) {
        json_response(['error' => 'Query parameter "q" is required'], 400);
    }

    $taxonomy = new TaxonomyLookup($fetcher);
    $result   = $taxonomy->search($query, $limit);

    if ($result['error']) {
        json_response(['error' => $result['error'], 'results' => []], 502);
    }

    json_response($result);
});

$router->get('/api/taxonomy/detail/{key}', function (string $key) use ($fetcher) {
    JwtAuth::requireAuth();

    $taxonomy = new TaxonomyLookup($fetcher);
    $result   = $taxonomy->detail((int) $key);

    if ($result['error']) {
        json_response(['error' => $result['error']], 404);
    }

    json_response($result);
});

$router->get('/api/taxonomy/suggest', function () use ($fetcher) {
    JwtAuth::requireAuth();

    $prefix = $_GET['q'] ?? '';
    $limit  = min(20, max(1, (int) ($_GET['limit'] ?? 10)));

    if (strlen($prefix) < 2) {
        json_response(['error' => 'At least 2 characters required'], 400);
    }

    $taxonomy = new TaxonomyLookup($fetcher);
    $result   = $taxonomy->suggest($prefix, $limit);

    if ($result['error']) {
        json_response(['error' => $result['error'], 'suggestions' => []], 502);
    }

    json_response($result);
});

$router->get('/api/taxonomy/classify/{key}', function (string $key) use ($fetcher) {
    JwtAuth::requireAuth();

    $taxonomy = new TaxonomyLookup($fetcher);
    $result   = $taxonomy->classify((int) $key);

    if ($result['error']) {
        json_response(['error' => $result['error']], 502);
    }

    json_response($result);
});

$router->get('/api/taxonomy/match', function () use ($fetcher) {
    JwtAuth::requireAuth();

    $name = $_GET['name'] ?? '';
    if (empty($name)) {
        json_response(['error' => 'Query parameter "name" is required'], 400);
    }

    $taxonomy = new TaxonomyLookup($fetcher);
    $result   = $taxonomy->matchVernacular($name);

    json_response($result);
});
