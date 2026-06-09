<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Module;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;

class InfoModuleCommand extends Command
{
    public static function category(): string
    {
        return 'Modules';
    }

    public function __construct(
        private readonly Application $app
    ) {
    }

    public function signature(): string
    {
        return 'module:info {name}';
    }

    public function description(): string
    {
        return 'Show detailed information about a module';
    }

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Module name is required.');
            return 1;
        }

        $moduleManager = $this->app->make(ModuleManager::class);
        $module = $moduleManager->get($name);

        if ($module === null) {
            $this->error("Module '{$name}' is not loaded.");
            return 1;
        }

        $manifest = $module->manifestObject();

        $this->line("\033[1m{$manifest->name}\033[0m \033[33m{$manifest->version}\033[0m");
        if ($manifest->description) {
            $this->line("  {$manifest->description}");
        }
        $this->line();

        $path = $module->path();

        $this->line("\033[1mPath\033[0m");
        $this->line("  {$path}");
        $this->line();

        $this->line("\033[1mEntry\033[0m");
        $this->line("  {$manifest->entry}");
        $this->line();

        if ($manifest->requires !== []) {
            $this->line("\033[1mRequires\033[0m");
            foreach ($manifest->requires as $dep => $constraint) {
                $this->line("  {$dep}: {$constraint}");
            }
            $this->line();
        }

        $this->printConfigInfo($name, $path);
        $this->printViewsInfo($path, $manifest->views);
        $this->printRoutesInfo($path, $manifest->routes);
        $this->printCommandsInfo($manifest->commands);

        return 0;
    }

    private function printConfigInfo(string $namespace, string $path): void
    {
        $configDir = $path . '/config';
        if (!is_dir($configDir)) {
            return;
        }

        $files = glob($configDir . '/*.php');
        if ($files === [] || $files === false) {
            return;
        }

        $this->line("\033[1mConfig\033[0m");
        foreach ($files as $file) {
            $fileName = basename($file, '.php');
            $this->line("  {$namespace}:{$fileName}");
        }
        $this->line();
    }

    private function printViewsInfo(string $path, ?string $viewsPath): void
    {
        if ($viewsPath === null || $viewsPath === '') {
            return;
        }

        $viewsDir = $path . '/' . ltrim($viewsPath, '/');
        $count = 0;

        $this->line("\033[1mViews\033[0m");
        $this->line("  {$viewsPath}");

        if (!is_dir($viewsDir)) {
            $this->line("  \033[33mmissing\033[0m");
            $this->line();
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        $this->line("  {$count} template(s)");
        $this->line();
    }

    /** @param array<string, string> $routes */
    private function printRoutesInfo(string $path, array $routes): void
    {
        if ($routes === []) {
            return;
        }

        $this->line("\033[1mRoutes\033[0m");
        foreach ($routes as $type => $relativePath) {
            $routePath = $path . '/' . ltrim($relativePath, '/');
            $status = file_exists($routePath) ? "\033[32mexists\033[0m" : "\033[33mmissing\033[0m";
            $this->line("  {$type}: {$relativePath} ({$status})");
        }
        $this->line();
    }

    /** @param array<string, string> $commands */
    private function printCommandsInfo(array $commands): void
    {
        if ($commands === []) {
            return;
        }

        $this->line("\033[1mCommands\033[0m");
        foreach ($commands as $signature => $class) {
            $this->line("  \033[32m{$signature}\033[0m → {$class}");
        }
        $this->line();
    }
}
