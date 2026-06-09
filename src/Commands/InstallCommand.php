<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Migrations\MigrationRepository;
use Anvyr\Loom\Database\Migrations\Migrator;
use Anvyr\Loom\Database\Schema\Schema;

class InstallCommand extends Command
{
    private const MARKDOWN_PARSERS = ['commonmark', 'parsedown', 'html'];

    private bool $interactive = true;

    /** @var array<string, mixed> */
    private array $config = [];

    public static function category(): string
    {
        return 'Setup';
    }

    public function signature(): string
    {
        return 'install [--defaults] [--force] [--no-migrate] [--no-sample] [--parser=]';
    }

    public function description(): string
    {
        return 'Install Anvyr Loom (interactive setup wizard)';
    }

    public function handle(): int
    {
        $this->interactive = !$this->option('defaults');
        $force = (bool) $this->option('force');

        $this->printBanner();

        if ($this->option('defaults')) {
            $this->info('Running with --defaults (non-interactive mode)');
            $this->line('');
        }

        if (!$this->validateRequestedParserOption()) {
            return self::FAILURE;
        }

        if (!$force && $this->isInstalled()) {
            $this->warning('Anvyr Loom appears to be already installed.');
            if (!$this->interactive || !$this->confirm('Run setup again?', false)) {
                $this->line('Use --force to re-run installation.');
                return 0;
            }
        }

        $this->step(1, 5, 'Creating directories');
        $this->createDirectories();

        $this->step(2, 5, 'Database configuration');
        $connection = $this->configureDatabase();

        $this->step(3, 5, 'Database migrations');
        if (!$this->option('no-migrate')) {
            $this->runMigrations($connection);
        } else {
            $this->line('  Skipped (--no-migrate)');
        }

        $this->step(4, 5, 'Cache configuration');
        $this->configureCacheDriver();

        $this->step(5, 5, 'Additional options');
        if (!$this->configureAdditionalOptions()) {
            return self::FAILURE;
        }

        if (!$this->option('no-sample')) {
            $this->configureSampleContent();
        }

        $this->writeConfiguration();

        $this->printSuccess();

        return 0;
    }

    private function printBanner(): void
    {
        $this->line('');
        $this->line("\033[1;36mAnvyr Loom Setup\033[0m");
        $this->line("\033[36m─────────────────\033[0m");
        $this->line('');
    }

    private function step(int $current, int $total, string $title): void
    {
        $this->line('');
        $this->info("[{$current}/{$total}] {$title}");
    }

    private function isInstalled(): bool
    {
        return file_exists(base_path('user/config/app.php'))
            && is_dir(base_path('storage/cache'));
    }

    private function createDirectories(): void
    {
        $directories = [
            'storage/cache',
            'storage/logs',
            'storage/sessions',
            'storage/index',
            'user/modules',
            'user/config',
            'user/content/pages',
            'user/views/layouts',
        ];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $this->success('  Directories created');
    }

    private function configureDatabase(): ?Connection
    {
        $drivers = ['sqlite', 'mysql', 'pgsql'];
        $default = 'sqlite';

        if ($this->interactive) {
            $this->line('  Available drivers:');
            $this->line("    \033[1m[1] sqlite\033[0m - Zero config, file-based (recommended)");
            $this->line('    [2] mysql  - MySQL/MariaDB server');
            $this->line('    [3] pgsql  - PostgreSQL server');

            $choice = (int) $this->ask('  Select database driver', '1');
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }

        $this->config['db.default'] = $driver;

        if ($driver === 'sqlite') {
            $dbPath = base_path('storage/database.sqlite');
            $this->config['db.connections.sqlite.database'] = $dbPath;

            if (!file_exists($dbPath)) {
                touch($dbPath);
            }

            $this->success('  Using SQLite: storage/database.sqlite');
        } else {
            if ($this->interactive) {
                $defaultHost = '127.0.0.1';
                $defaultPort = $driver === 'pgsql' ? '5432' : '3306';
                $defaultUser = $driver === 'pgsql' ? 'postgres' : 'root';

                $host = $this->ask('  Database host', $defaultHost);
                $port = $this->ask('  Database port', $defaultPort);
                $database = $this->ask('  Database name', 'loom');
                $username = $this->ask('  Username', $defaultUser);
                $password = $this->secret('  Password');

                $this->config["db.connections.{$driver}.host"] = $host;
                $this->config["db.connections.{$driver}.port"] = $port;
                $this->config["db.connections.{$driver}.database"] = $database;
                $this->config["db.connections.{$driver}.username"] = $username;
                $this->config["db.connections.{$driver}.password"] = $password;
            }

            $this->success("  Using {$driver}");
        }

        try {
            $connection = $this->buildConnection($driver);
            $connection->getPdo();
            $this->success('  Connection test: OK');
            return $connection;
        } catch (\Throwable $e) {
            $this->warning("  Connection test failed: {$e->getMessage()}");
            if ($this->interactive && $this->confirm('  Continue anyway?', true)) {
                return null;
            }
            if (!$this->interactive) {
                return null;
            }
            throw $e;
        }
    }

    private function buildConnection(string $driver): Connection
    {
        $config = config('db') ?? [];
        $config['default'] = $driver;

        foreach ($this->config as $key => $value) {
            if (str_starts_with($key, 'db.')) {
                $parts = explode('.', substr($key, 3));
                $ref = &$config;
                foreach ($parts as $part) {
                    if (!isset($ref[$part])) {
                        $ref[$part] = [];
                    }
                    $ref = &$ref[$part];
                }
                $ref = $value;
            }
        }

        return new Connection($config);
    }

    private function runMigrations(?Connection $connection): void
    {
        if ($connection === null) {
            $this->warning('  Skipped (no database connection)');
            return;
        }

        $runMigrations = true;
        if ($this->interactive) {
            $runMigrations = $this->confirm('  Run database migrations?', true);
        }

        if (!$runMigrations) {
            $this->line('  Skipped');
            return;
        }

        try {
            $schema = new Schema($connection);

            $repository = new MigrationRepository($connection, $schema);
            $migrator = new Migrator($connection, $schema, $repository);

            $path = base_path('database/migrations');
            if (is_dir($path)) {
                $migrator->run($path);
                $this->success('  Migrations completed');
            } else {
                $this->line('  No migrations found');
            }
        } catch (\Throwable $e) {
            $this->warning("  Migration failed: {$e->getMessage()}");
        }
    }

    private function configureCacheDriver(): void
    {
        $drivers = ['file', 'apcu', 'redis'];
        $default = 'file';

        if ($this->interactive) {
            $this->line('  Available drivers:');
            $this->line("    \033[1m[1] file\033[0m  - File-based cache (works everywhere)");
            $this->line('    [2] apcu  - In-memory (requires APCu extension)');
            $this->line('    [3] redis - Redis server (requires connection)');

            $choice = (int) $this->ask('  Select cache driver', '1');
            $driver = $drivers[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }

        $this->config['cache.default'] = $driver;

        if ($driver === 'redis' && $this->interactive) {
            $host = $this->ask('  Redis host', '127.0.0.1');
            $port = $this->ask('  Redis port', '6379');
            $this->config['cache.drivers.redis.host'] = $host;
            $this->config['cache.drivers.redis.port'] = (int) $port;
        }

        $this->success("  Cache driver: {$driver}");
    }

    private function configureAdditionalOptions(): bool
    {
        if (!$this->configureMarkdownParser()) {
            return false;
        }

        $this->configureMultiTenancy();

        return true;
    }

    private function configureMarkdownParser(): bool
    {
        $availability = self::markdownParserAvailability();
        $default = self::defaultMarkdownParser($availability);
        $requestedDriver = $this->option('parser');
        $hasExplicitSelection = is_string($requestedDriver) && trim($requestedDriver) !== '';

        if ($hasExplicitSelection) {
            $driver = self::normalizeMarkdownParser($requestedDriver);
        } elseif ($this->interactive) {
            $this->line('  Markdown parser:');
            $cm = $availability['commonmark'] ? '' : " \033[33m(not installed)\033[0m";
            $pd = $availability['parsedown'] ? '' : " \033[33m(not installed)\033[0m";
            $this->line("    [1] commonmark - Full-featured{$cm}");
            $this->line("    [2] parsedown  - Fast & simple{$pd}");
            $this->line('    [3] html       - No parsing (raw HTML only)');

            $defaultIndex = array_search($default, self::MARKDOWN_PARSERS, true) + 1;
            $choice = (int) $this->ask('  Select parser', (string) $defaultIndex);
            $driver = self::MARKDOWN_PARSERS[$choice - 1] ?? $default;
        } else {
            $driver = $default;
        }

        if (!is_string($driver)) {
            $this->error('  Unable to determine a valid markdown parser.');
            return false;
        }

        $resolution = self::resolveMarkdownParserSelection($driver, $availability, $hasExplicitSelection);

        if ($resolution['error'] !== null) {
            $this->error('  ' . $resolution['error']);
            $this->line("  Or run ./install.sh --parser={$driver}");
            return false;
        }

        if ($resolution['warning'] !== null) {
            $this->warning('  ' . $resolution['warning']);

            $package = self::markdownParserPackage($driver);
            if ($package !== null) {
                $this->line("  Install it later with: composer require {$package}");
            }
        }

        $driver = $resolution['driver'];

        $this->config['content.parser.driver'] = $driver;

        $label = $hasExplicitSelection ? 'Markdown parser selected' : 'Markdown parser';
        $this->success("  {$label}: {$driver}");

        return true;
    }

    private function validateRequestedParserOption(): bool
    {
        $requestedDriver = $this->option('parser');

        if ($requestedDriver === null) {
            return true;
        }

        if (!is_string($requestedDriver) || trim($requestedDriver) === '') {
            $this->error('The --parser option requires a value.');
            return false;
        }

        $driver = self::normalizeMarkdownParser($requestedDriver);
        if ($driver === null) {
            $supported = implode(', ', self::MARKDOWN_PARSERS);
            $this->error("Unsupported parser '{$requestedDriver}'. Use one of: {$supported}.");
            return false;
        }

        $resolution = self::resolveMarkdownParserSelection($driver, self::markdownParserAvailability(), true);
        if ($resolution['error'] !== null) {
            $this->error($resolution['error']);
            $this->line("Or run ./install.sh --parser={$driver}");
            return false;
        }

        return true;
    }

    /**
     * @return array{commonmark: bool, parsedown: bool, html: true}
     */
    private static function markdownParserAvailability(): array
    {
        return [
            'commonmark' => class_exists(\League\CommonMark\MarkdownConverter::class),
            'parsedown' => class_exists('Parsedown'),
            'html' => true,
        ];
    }

    /**
     * @param array{commonmark: bool, parsedown: bool, html: bool} $availability
     */
    private static function defaultMarkdownParser(array $availability): string
    {
        if ($availability['commonmark'] === true) {
            return 'commonmark';
        }

        if ($availability['parsedown'] === true) {
            return 'parsedown';
        }

        return 'html';
    }

    private static function normalizeMarkdownParser(mixed $driver): ?string
    {
        if (!is_string($driver)) {
            return null;
        }

        $normalized = strtolower(trim($driver));

        return in_array($normalized, self::MARKDOWN_PARSERS, true) ? $normalized : null;
    }

    private static function markdownParserPackage(string $driver): ?string
    {
        return match ($driver) {
            'commonmark' => 'league/commonmark',
            'parsedown' => 'erusev/parsedown',
            default => null,
        };
    }

    /**
     * @param array{commonmark: bool, parsedown: bool, html: bool} $availability
     * @return array{driver: string, warning: ?string, error: ?string}
     */
    private static function resolveMarkdownParserSelection(string $driver, array $availability, bool $hasExplicitSelection): array
    {
        if (($availability[$driver] ?? false) === true) {
            return [
                'driver' => $driver,
                'warning' => null,
                'error' => null,
            ];
        }

        if ($hasExplicitSelection) {
            $package = self::markdownParserPackage($driver);
            $error = "The '{$driver}' parser is not installed.";

            if ($package !== null) {
                $error .= " Install it with: composer require {$package}";
            }

            return [
                'driver' => $driver,
                'warning' => null,
                'error' => $error,
            ];
        }

        $fallback = self::defaultMarkdownParser($availability);

        return [
            'driver' => $fallback,
            'warning' => $driver === $fallback
                ? null
                : "The '{$driver}' parser is not installed; using '{$fallback}' instead.",
            'error' => null,
        ];
    }

    private function configureMultiTenancy(): void
    {
        $enabled = false;

        if ($this->interactive) {
            $enabled = $this->confirm('  Enable multi-tenancy?', false);
        }

        $this->config['tenancy.enabled'] = $enabled;

        if ($enabled && $this->interactive) {
            $this->line('  Tenant resolver:');
            $this->line("    \033[1m[1] host\033[0m - Based on hostname/subdomain");
            $this->line('    [2] path - Based on URL path segment');

            $choice = $this->ask('  Select resolver', '1');
            $resolver = $choice === '2' ? 'path' : 'host';
            $this->config['tenancy.resolver'] = $resolver;

            $this->success("  Multi-tenancy: enabled ({$resolver} resolver)");
            $this->line('  Configure tenant IDs and host/path maps in user/config/tenancy.php');
        } else {
            $this->line('  Multi-tenancy: disabled');
        }
    }

    private function configureSampleContent(): void
    {
        $createSamples = true;

        if ($this->interactive) {
            $createSamples = $this->confirm('  Create sample content?', true);
        }

        if (!$createSamples) {
            return;
        }

        $userContentPath = base_path('user/content/pages');
        $stubContentPath = base_path('src/stubs/defaults/content/pages');

        if (is_dir($stubContentPath)) {
            $files = glob($stubContentPath . '/*') ?: [];
            foreach ($files as $file) {
                $filename = basename($file);
                $target = $userContentPath . '/' . $filename;
                if (!file_exists($target)) {
                    copy($file, $target);
                }
            }
        }

        $userViewsPath = base_path('user/views');
        $stubViewsPath = base_path('src/stubs/defaults/views');

        if (is_dir($stubViewsPath)) {
            $this->recursiveCopy($stubViewsPath, $userViewsPath);
        }

        $this->success('  Sample content created');
    }

    private function writeConfiguration(): void
    {
        $this->ensureUserConfig();
        $this->updateUserConfigFiles();
        $this->success('  Configuration saved');
    }

    private function ensureUserConfig(): void
    {
        $userConfigDir = base_path('user/config');
        if (!is_dir($userConfigDir)) {
            mkdir($userConfigDir, 0755, true);
        }

        $defaultConfigDir = config_path('');
        $files = glob(rtrim($defaultConfigDir, '/') . '/*.php') ?: [];

        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename === 'version.php') {
                continue;
            }
            $target = $userConfigDir . '/' . $filename;
            if (!file_exists($target)) {
                copy($file, $target);
            }
        }
    }

    private function updateUserConfigFiles(): void
    {
        if (isset($this->config['db.default'])) {
            $this->updateConfigValue('db.php', "'default'", $this->config['db.default']);
        }

        $driver = $this->config['db.default'] ?? 'sqlite';
        if ($driver !== 'sqlite') {
            $connPrefix = "db.connections.{$driver}.";
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                if (isset($this->config[$connPrefix . $key])) {
                    $this->updateNestedConfigValue(
                        'db.php',
                        ['connections', $driver, $key],
                        $this->config[$connPrefix . $key]
                    );
                }
            }
        }

        if (isset($this->config['cache.default'])) {
            $this->updateConfigValue('cache.php', "'default'", $this->config['cache.default']);
        }

        if (isset($this->config['content.parser.driver'])) {
            $this->updateParserDriver($this->config['content.parser.driver']);
        }

        if (isset($this->config['tenancy.enabled'])) {
            $this->updateConfigValue('tenancy.php', "'enabled'", $this->config['tenancy.enabled']);
        }

        if (isset($this->config['tenancy.resolver'])) {
            $this->updateConfigValue('tenancy.php', "'resolver'", $this->config['tenancy.resolver']);
        }
    }

    private function updateConfigValue(string $file, string $key, mixed $value): void
    {
        $path = base_path('user/config/' . $file);
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }
        $literalValue = $this->configLiteral($value);

        // Match pattern: 'key' => env(..., value) or 'key' => value
        $pattern = "/({$key}\s*=>\s*)(?:env\(\s*['\"][^'\"]+['\"]\s*,\s*)?(?:['\"][^'\"]*['\"]|true|false|null|-?\d+(?:\.\d+)?)\)?/";
        $replacement = '$1' . $literalValue;

        $updated = preg_replace($pattern, $replacement, $contents, 1);

        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }

    /** @param non-empty-list<string> $keys */
    private function updateNestedConfigValue(string $file, array $keys, mixed $value): void
    {
        $path = base_path('user/config/' . $file);
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }
        $literalValue = $this->configLiteral($value);

        $lastKey = array_pop($keys);
        $prefix = '';

        foreach ($keys as $key) {
            $prefix .= "'" . preg_quote((string) $key, '/') . "'\s*=>\s*\[.*?";
        }

        $lastKeyPattern = preg_quote((string) $lastKey, '/');
        $valuePattern = "(?:env\(\s*['\"][^'\"]+['\"]\s*,\s*)?(?:['\"][^'\"]*['\"]|true|false|null|-?\d+(?:\.\d+)?)\)?";
        $pattern = "/({$prefix}'{$lastKeyPattern}'\s*=>\s*){$valuePattern}/s";

        $updated = preg_replace($pattern, '$1' . $literalValue, $contents, 1);

        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }

    private function configLiteral(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value), is_float($value) => (string) $value,
            default => "'" . addslashes((string) $value) . "'",
        };
    }

    private function updateParserDriver(string $driver): void
    {
        $path = base_path('user/config/content.php');
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        // Replace the parser driver value — handles both env() wrapper and bare string.
        $updated = preg_replace(
            "/^(\s*'driver'\s*=>\s*)(?:env\(\s*'[^']*'\s*,\s*)?'[a-z]+'\)?,?\s*$/m",
            '$1\'' . $driver . '\',',
            $contents,
            1
        );

        if ($updated !== null && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }

    private function printSuccess(): void
    {
        $this->line('');
        $this->line("\033[32m╔══════════════════════════════════════╗\033[0m");
        $this->line("\033[32m║  Anvyr Loom installed successfully!  ║\033[0m");
        $this->line("\033[32m╚══════════════════════════════════════╝\033[0m");
        $this->line('');
        $this->info('Next steps:');
        $this->line("  1. Review config in \033[36muser/config/\033[0m");
        $this->line("  2. Start server:    \033[36mloom serve\033[0m");
        $this->line("  3. Visit:           \033[36mhttp://localhost:8000\033[0m");
        if (($this->config['tenancy.enabled'] ?? false) === true) {
            $this->line("  4. Configure tenants in \033[36muser/config/tenancy.php\033[0m");
        }
        $this->line('');
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) {
            return;
        }

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;

                if (is_dir($srcPath)) {
                    $this->recursiveCopy($srcPath, $dstPath);
                } elseif (!file_exists($dstPath)) {
                    copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
    }
}
