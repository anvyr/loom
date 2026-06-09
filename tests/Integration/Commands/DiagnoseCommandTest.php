<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Commands;

use Anvyr\Loom\Commands\DiagnoseCommand;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class DiagnoseCommandTest extends ApplicationTestCase
{
    public function test_diagnose_command_outputs_json_report(): void
    {
        touch($this->sandboxPath('storage/database.sqlite'));

        $app = $this->requireBootstrappedApplication();

        /** @var DiagnoseCommand $command */
        $command = $app->make(DiagnoseCommand::class);
        $command->setArguments([]);
        $command->setOptions(['json' => true]);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());
        $output = trim($output);

        $this->assertSame(0, $exitCode, $output);
        $this->assertNotSame('', $output);

        $decoded = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('cache', $decoded);
        $this->assertArrayHasKey('storage', $decoded);
        $this->assertArrayHasKey('database', $decoded);
        $this->assertArrayHasKey('content_driver', $decoded);
        $this->assertArrayHasKey('modules', $decoded);
    }
}
