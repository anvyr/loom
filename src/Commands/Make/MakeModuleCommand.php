<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Make;

class MakeModuleCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:module {name}';
    }

    public function description(): string
    {
        return 'Create a new module structure';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Module name is required.');
            return 1;
        }

        $moduleName = $name;
        $className = $this->formatClassName($name);
        $slug = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $moduleName));
        $composerAutoload = [
            'psr-4' => [
                "{$className}\\" => 'src/',
            ],
        ];

        $basePath = base_path("user/modules/{$moduleName}");

        if (is_dir($basePath)) {
            $this->error("Module '{$moduleName}' already exists.");
            return 1;
        }

        $this->info("Creating module structure for {$moduleName}...");
        mkdir($basePath, 0755, true);
        mkdir("{$basePath}/src", 0755, true);
        mkdir("{$basePath}/config", 0755, true);
        mkdir("{$basePath}/resources/views", 0755, true);
        mkdir("{$basePath}/routes", 0755, true);

        $manifest = [
            'name' => $slug,
            'version' => '1.0.0',
            'path' => '.',
            'entry' => "{$className}\\Module",
            'description' => "The {$moduleName} module.",
            'requires' => [
                'core' => '>=2.2.0',
            ],
            'commands' => new \stdClass(),
            'routes' => [
                'web' => 'routes/web.php',
                'api' => 'routes/api.php',
            ],
            'views' => 'resources/views',
        ];

        file_put_contents(
            "{$basePath}/module.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $stub = <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Anvyr\Loom\Core\BaseModule;

class Module extends BaseModule
{
}
PHP;

        $content = $this->replacePlaceholders($stub, [
            '{{ namespace }}' => $className,
        ]);

        file_put_contents("{$basePath}/src/Module.php", $content);

        $routeStub = <<<'PHP'
    <?php

    declare(strict_types=1);

    use Anvyr\Loom\Core\Application;
    use Anvyr\Loom\Http\Routing\Router;

    return static function (Router $router, Application $app): void {
    };
    PHP;

        file_put_contents("{$basePath}/routes/web.php", $routeStub);
        file_put_contents("{$basePath}/routes/api.php", $routeStub);

        $configStub = <<<'PHP'
<?php

return [
];
PHP;

        file_put_contents("{$basePath}/config/general.php", $configStub);

        $composer = [
            'name' => 'loom-modules/' . strtolower($moduleName),
            'description' => $manifest['description'],
            'type' => 'loom-module',
            'autoload' => $composerAutoload,
        ];

        file_put_contents(
            "{$basePath}/composer.json",
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->success("Module [{$moduleName}] created successfully in user/modules/{$moduleName}.");
        $this->info("Run 'php loom module:enable {$slug}' to activate it.");

        return 0;
    }
}
