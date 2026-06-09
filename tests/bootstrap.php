<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['CACHE_DRIVER'] = 'file';

// Default test base path. Individual tests may override this to sandbox helper paths.
$_ENV['LOOM_BASE_PATH'] = dirname(__DIR__);
$_SERVER['LOOM_BASE_PATH'] = dirname(__DIR__);
putenv('LOOM_BASE_PATH=' . dirname(__DIR__));
