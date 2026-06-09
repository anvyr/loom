<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Core;

use Anvyr\Loom\Tests\Support\ModuleManagerTestCase;

final class ModuleDiscoveryTest extends ModuleManagerTestCase
{
    public function test_discovers_modules_from_filesystem(): void
    {
        $this->createManagerModule('test-module', 'TestModule', [
            'version' => '1.0.0',
            'entry' => 'TestModule\\Module',
        ]);

        $discovered = $this->moduleManager->discover();

        $this->assertArrayHasKey('test-module', $discovered);
        $this->assertSame('1.0.0', $discovered['test-module']['version']);
    }

    public function test_validate_rejects_missing_entry(): void
    {
        $issues = $this->moduleManager->validate('broken-module', [
            'name' => 'broken-module',
            'version' => '1.0.0',
        ]);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('entry', $issues[0]);
    }

    public function test_validate_rejects_incompatible_core_version(): void
    {
        $issues = $this->moduleManager->validate('future-module', [
            'name' => 'future-module',
            'version' => '1.0.0',
            'entry' => 'FutureModule\\Module',
            'requires' => ['core' => '^99.0'],
        ]);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('core', $issues[0]);
    }

    public function test_validate_rejects_missing_dependency(): void
    {
        $issues = $this->moduleManager->validate('needs-dependency', [
            'name' => 'needs-dependency',
            'version' => '1.0.0',
            'entry' => 'NeedsDependency\\Module',
            'requires' => ['other-module' => '^1.0'],
        ]);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('other-module', implode(' ', $issues));
    }

    public function test_validate_checks_dependency_version(): void
    {
        $this->createManagerModule('base-module', 'BaseModule', [
            'version' => '0.5.0',
            'entry' => 'BaseModule\\Module',
        ]);

        $discovered = $this->moduleManager->discover();
        $issues = $this->moduleManager->validate('needs-base', [
            'name' => 'needs-base',
            'version' => '1.0.0',
            'entry' => 'NeedsBase\\Module',
            'requires' => ['base-module' => '^1.0'],
        ], $discovered);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('base-module', implode(' ', $issues));
    }

    public function test_validate_detects_incompatible_dependency_after_version_change(): void
    {
        $this->createManagerModule('core-module', 'CoreModule', [
            'version' => '1.0.0',
            'entry' => 'CoreModule\\Module',
        ]);
        $this->createManagerModule('dependent-module', 'DependentModule', [
            'version' => '1.0.0',
            'entry' => 'DependentModule\\Module',
            'requires' => ['core-module' => '^1.0'],
        ]);

        $discovered = $this->moduleManager->discover();
        $issues = $this->moduleManager->validate('dependent-module', $discovered['dependent-module'], $discovered);
        $this->assertSame([], $issues);

        $discovered['core-module']['version'] = '2.0.0';

        $issuesAfterChange = $this->moduleManager->validate(
            'dependent-module',
            $discovered['dependent-module'],
            $discovered,
        );

        $this->assertNotEmpty($issuesAfterChange);
        $this->assertStringContainsString('core-module', implode(' ', $issuesAfterChange));
    }
}
