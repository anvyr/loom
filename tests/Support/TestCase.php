<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Core\Paths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Drivers\Cache\FileCache;
use Anvyr\Loom\Http\AssetServer;
use Anvyr\Loom\Validation\ValidationExtensionRegistry;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for rewritten suite.
 * Provides a per-test temp directory and default config overrides
 * so tests do not touch real user content or cache.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetGlobals();

        $this->tmpDir = sys_get_temp_dir() . '/loom-tests-' . bin2hex(random_bytes(6));
        $this->mkdir($this->tmpDir);

        $this->prepareFilesystem();
        $this->setBasePathOverride($this->basePathRoot());
        $this->resetFrameworkState();
        $this->initializeApplication();

        $this->seedConfig();
    }

    protected function tearDown(): void
    {
        $this->resetFrameworkState();
        $this->setBasePathOverride($this->frameworkRoot());
        $this->resetGlobals();

        $this->rrmdir($this->tmpDir);

        parent::tearDown();
    }

    protected function prepareFilesystem(): void
    {
    }

    protected function frameworkRoot(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);

        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    protected function basePathRoot(): string
    {
        return $this->frameworkRoot();
    }

    protected function configRoot(): string
    {
        return $this->frameworkRoot('config');
    }

    protected function userConfigRoot(): string
    {
        return $this->tmpDir . '/user/config';
    }

    protected function tenantConfigRoot(): ?string
    {
        return null;
    }

    protected function initializeApplication(): void
    {
        new Application($this->basePathRoot(), $this->buildConfigRepository(), $this->buildTenancyState());
    }

    protected function buildConfigRepository(): ConfigRepository
    {
        $this->mkdir($this->userConfigRoot());

        $tenantConfigPath = $this->tenantConfigRoot();

        if ($tenantConfigPath === null) {
            $tenantConfigPath = function (): ?string {
                if (!Application::hasInstance()) {
                    return null;
                }

                /** @var TenancyState $state */
                $state = Application::getInstance()->make(TenancyState::class);
                if (!$state->isEnabled() || $state->currentId() === null) {
                    return null;
                }

                $root = (string) ($state->config()['paths']['user_root'] ?? 'user/tenants');
                $tenantRoot = Paths::isAbsolute($root)
                    ? rtrim($root, '/\\')
                    : Paths::join($this->basePathRoot(), trim($root, '/\\'));

                return Paths::join(Paths::join($tenantRoot, (string) $state->currentId()), 'config');
            };
        }

        return new ConfigRepository(
            $this->configRoot(),
            null,
            $this->userConfigRoot(),
            $tenantConfigPath,
        );
    }

    protected function buildTenancyState(): TenancyState
    {
        return TenancyState::fromConfigFile($this->configRoot() . DIRECTORY_SEPARATOR . 'tenancy.php');
    }

    protected function resetFrameworkState(): void
    {
        if (Application::hasInstance()) {
            Application::getInstance()->make(ValidationExtensionRegistry::class)->clear();
            Application::getInstance()->make(AssetServer::class)->reset();
        }

        Application::clearInstance();

        $_SESSION = [];
    }

    protected function setBasePathOverride(string $path): void
    {
        $path = rtrim($path, '/\\');
        $_ENV['LOOM_BASE_PATH'] = $path;
        $_SERVER['LOOM_BASE_PATH'] = $path;
        putenv('LOOM_BASE_PATH=' . $path);
    }

    /** Reset superglobals that Request::capture reads to avoid cross-test leakage. */
    private function resetGlobals(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];
        $_SERVER = [];
    }

    /** Override defaults so file/cache drivers stay in tmp space. */
    protected function seedConfig(): void
    {
        $cachePath = $this->tmpDir . '/cache';
        $contentPath = $this->tmpDir . '/content/pages';
        $viewPath = $this->tmpDir . '/views';
        $logPath = $this->tmpDir . '/logs/loom.log';

        $this->mkdir($cachePath);
        $this->mkdir($cachePath . '/views');
        $this->mkdir($contentPath);
        $this->mkdir($viewPath);
        $this->mkdir(dirname($logPath));

        /** @var ConfigRepository $repository */
        $repository = Application::getInstance()->make(ConfigRepository::class);

        foreach ([
            'app.env' => 'testing',
            'app.debug' => true,
            'cache.default' => 'file',
            'cache.drivers.file.path' => $cachePath,
            'cache.prefix' => 'loom-test',
            'content.drivers.file.path' => $contentPath,
            'content.drivers.file.index.driver' => 'json',
            'content.drivers.file.index.json.path' => $cachePath . '/page-index.json',
            'content.drivers.file.index.sqlite.path' => $cachePath . '/page-index.sqlite',
            'view.path' => $viewPath,
            'view.compiled' => $cachePath . '/views',
            'logging.path' => $logPath,
        ] as $key => $value) {
            $repository->persist($key, $value);
        }
    }

    protected function pageIndexJsonPath(): string
    {
        return $this->tmpDir . '/cache/page-index.json';
    }

    protected function pageIndexSqlitePath(): string
    {
        return $this->tmpDir . '/cache/page-index.sqlite';
    }

    /**
     * Build a request by priming superglobals. Keeps tests isolated from real globals.
     */
    protected function makeRequest(string $method, string $uri, array $data = [], array $headers = []): \Anvyr\Loom\Http\Request
    {
        $_GET = $method === 'GET' ? $data : [];
        $_POST = $method !== 'GET' ? $data : [];
        $_FILES = [];
        $_COOKIE = [];
        $serverHeaders = [];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $_SERVER = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
        ], $serverHeaders);

        return \Anvyr\Loom\Http\Request::capture();
    }

    protected function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    protected function copyFile(string $source, string $destination): void
    {
        $this->mkdir(dirname($destination));
        copy($source, $destination);
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        $this->mkdir($destination);

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
                continue;
            }

            $this->copyFile($sourcePath, $destinationPath);
        }
    }

    /**
     * Create a FileCache instance pointed at the test temp directory.
     */
    protected function makeFileCache(string $prefix = 'test'): FileCache
    {
        return new FileCache([
            'path' => $this->tmpDir . '/cache',
            'prefix' => $prefix,
        ]);
    }

    /**
     * Run a callable while capturing its output.
     *
     * @return array{0: mixed, 1: string}  [$returnValue, $capturedOutput]
     */
    protected function captureOutput(callable $callback): array
    {
        ob_start();
        $result = $callback();
        $output = (string) ob_get_clean();

        return [$result, $output];
    }

    protected function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
