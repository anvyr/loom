<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Anvyr\Loom\Exceptions\Handler;
use Anvyr\Loom\Http\AssetServer;
use Anvyr\Loom\Http\Request;

$request = Request::capture();

$app = require __DIR__ . '/../bootstrap/app.php';
$app->instance('request', $request);
$app->make(\Anvyr\Loom\Core\Tenancy\TenancyManager::class)->bootstrapFromRequest($request);
$app->boot();

$response = $app->make(AssetServer::class)->serve($request);
if ($response !== null) {
    $response->send();
    exit;
}

$router = $app->make('router');

$routeCacheFile = storage_path('cache/routes.php');
$cachedRoutes = file_exists($routeCacheFile) ? require $routeCacheFile : null;

if (is_array($cachedRoutes)) {
    $router->loadCachedRoutes($cachedRoutes);
} else {
    if ($app->has('modules')) {
        $app->make('modules')->loadRoutes();
    }

    $app->registerDefaultRoutes($router);
}

$handler = $app->make(Handler::class);
try {
    $response = $router->dispatch($request);
} catch (\Throwable $e) {
    $handler->report($e, $request);

    if (config('app.debug', false)) {
        throw $e;
    }

    $response = $handler->render($e, $request);
}

$response->send();
