<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ModuleManager;
use Anvyr\Loom\Tests\Support\Concerns\CreatesModuleFixtures;
use Anvyr\Loom\Tests\Support\Concerns\WritesTestFiles;

abstract class ModuleManagerTestCase extends ApplicationTestCase
{
    use CreatesModuleFixtures;
    use WritesTestFiles;

    protected Application $app;
    protected ModuleManager $moduleManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mkdir($this->sandboxPath('modules'));

        config([
            'modules.paths' => [$this->sandboxPath('modules/*')],
            'modules.modules' => [],
            'modules.auto_discover' => true,
        ]);

        $this->app = $this->makeApplication();
        $this->moduleManager = new ModuleManager($this->app);
    }

    protected function modulesRoot(): string
    {
        return $this->sandboxPath('modules');
    }

    protected function createManagerModule(
        string $name,
        string $namespace,
        array $manifestOverrides = [],
        ?string $moduleSource = null,
    ): string {
        return $this->createModuleFixture(
            $name,
            $namespace,
            $manifestOverrides,
            $moduleSource,
            $this->modulesRoot(),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     */
    protected function writeCompiledModules(array $modules): void
    {
        $this->writeJsonFile($this->sandboxPath('storage/modules-compiled.json'), [
            'modules' => $modules,
        ]);
    }

    /**
     * @param array<string, string> $psr4
     */
    protected function writeAutoloadMap(array $psr4): void
    {
        $this->writeFile(
            $this->sandboxPath('storage/modules-autoload.php'),
            "<?php\n\nreturn " . var_export(['psr-4' => $psr4], true) . ";\n"
        );
    }
}
