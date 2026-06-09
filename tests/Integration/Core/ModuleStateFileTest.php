<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Core;

use Anvyr\Loom\Tests\Support\ApplicationTestCase;
use Anvyr\Loom\Tests\Support\Concerns\WritesTestFiles;

final class ModuleStateFileTest extends ApplicationTestCase
{
    use WritesTestFiles;

    public function test_state_file_can_enable_module(): void
    {
        $statePath = $this->sandboxPath('storage/modules.json');

        $this->writeJsonFile($statePath, ['enabled' => ['toggle-module']]);

        $loaded = $this->readJsonFile($statePath);

        $this->assertSame(['toggle-module'], $loaded['enabled']);
    }

    public function test_state_file_can_disable_module(): void
    {
        $statePath = $this->sandboxPath('storage/modules.json');

        $this->writeJsonFile($statePath, ['enabled' => ['toggle-module']]);
        $this->writeJsonFile($statePath, ['enabled' => []]);

        $loaded = $this->readJsonFile($statePath);

        $this->assertSame([], $loaded['enabled']);
    }
}
