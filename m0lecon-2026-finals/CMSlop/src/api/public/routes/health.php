<?php

$router->get('/api/health', function () use ($cache) {
    json_response([
        'status'       => 'healthy',
        'service'      => 'herbarium-api',
        'version'      => '1.0.0',
        'cache_active' => $cache->count(),
    ]);
});
