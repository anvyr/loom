<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class ConfigRepositoryOwnershipTest extends ApplicationTestCase
{
    public function test_application_binds_its_own_config_repository(): void
    {
        $this->mkdir($this->sandboxPath('user/config'));
        file_put_contents(
            $this->sandboxPath('user/config/app.php'),
            "<?php\n\nreturn ['name' => 'Sandbox App'];\n"
        );

        $app = $this->freshApplication();

        /** @var ConfigRepository $repository */
        $repository = $app->make(ConfigRepository::class);

        $this->assertSame($repository, $app->make('config'));
        $this->assertSame('Sandbox App', $repository->get('app.name'));
        $this->assertSame('Sandbox App', config('app.name'));
    }

    public function test_fresh_application_does_not_reuse_previous_in_memory_config_state(): void
    {
        file_put_contents(
            $this->sandboxPath('user/config/app.php'),
            "<?php\n\nreturn ['name' => 'Disk App'];\n"
        );

        $first = $this->freshApplication();
        $this->assertSame('Disk App', $first->make(ConfigRepository::class)->get('app.name'));

        config(['app.name' => 'Memory App']);

        $fresh = new Application($this->sandboxPath(), $this->buildConfigRepository());

        /** @var ConfigRepository $repository */
        $repository = $fresh->make(ConfigRepository::class);

        $this->assertNotSame($first->make(ConfigRepository::class), $repository);
        $this->assertSame('Disk App', $repository->get('app.name'));
    }
}
