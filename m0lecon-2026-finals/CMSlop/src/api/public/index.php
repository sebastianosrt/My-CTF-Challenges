<?php

require __DIR__ . '/../autoload.php';

use Herbarium\Core\Database;
use Herbarium\Core\Router;
use Herbarium\Core\AuditLogger;
use Herbarium\Import\HttpFetcher;
use Herbarium\Core\CacheStore;

Database::init();

$router  = new Router();
$audit   = AuditLogger::getInstance();
$fetcher = new HttpFetcher();
$cache   = CacheStore::getInstance();

\Herbarium\Scheduling\ContentScheduler::executeDue();

require __DIR__ . '/routes/_helpers.php';

require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/profile.php';
require __DIR__ . '/routes/specimens.php';
require __DIR__ . '/routes/taxonomy.php';
require __DIR__ . '/routes/import.php';
require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/pages.php';
require __DIR__ . '/routes/tags.php';
require __DIR__ . '/routes/collections.php';
require __DIR__ . '/routes/reports.php';
require __DIR__ . '/routes/annotations.php';
require __DIR__ . '/routes/public.php';
require __DIR__ . '/routes/health.php';
require __DIR__ . '/routes/media.php';
require __DIR__ . '/routes/settings.php';
require __DIR__ . '/routes/apikeys.php';
require __DIR__ . '/routes/webhooks.php';
require __DIR__ . '/routes/scheduling.php';

$router->run();