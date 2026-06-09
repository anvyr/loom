<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands;

use Anvyr\Loom\Commands\InstallCommand;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;
use Anvyr\Loom\Tests\Support\Concerns\ReflectionHelpers;

final class InstallCommandTest extends ApplicationTestCase
{
    use ReflectionHelpers;

    public function test_normalize_markdown_parser_accepts_supported_values(): void
    {
        $this->assertSame('commonmark', $this->invokeStatic('normalizeMarkdownParser', ' CommonMark '));
        $this->assertSame('parsedown', $this->invokeStatic('normalizeMarkdownParser', 'PARSEDOWN'));
        $this->assertSame('html', $this->invokeStatic('normalizeMarkdownParser', 'html'));
    }

    public function test_normalize_markdown_parser_rejects_invalid_values(): void
    {
        $this->assertNull($this->invokeStatic('normalizeMarkdownParser', 'markdown'));
        $this->assertNull($this->invokeStatic('normalizeMarkdownParser', null));
    }

    public function test_default_markdown_parser_prefers_first_available_runtime_driver(): void
    {
        $this->assertSame(
            'commonmark',
            $this->invokeStatic('defaultMarkdownParser', [
                'commonmark' => true,
                'parsedown' => true,
                'html' => true,
            ])
        );

        $this->assertSame(
            'parsedown',
            $this->invokeStatic('defaultMarkdownParser', [
                'commonmark' => false,
                'parsedown' => true,
                'html' => true,
            ])
        );

        $this->assertSame(
            'html',
            $this->invokeStatic('defaultMarkdownParser', [
                'commonmark' => false,
                'parsedown' => false,
                'html' => true,
            ])
        );
    }

    public function test_resolve_markdown_parser_selection_falls_back_when_missing_and_not_explicit(): void
    {
        $result = $this->invokeStatic('resolveMarkdownParserSelection', 'commonmark', [
            'commonmark' => false,
            'parsedown' => true,
            'html' => true,
        ], false);

        $this->assertSame('parsedown', $result['driver']);
        $this->assertSame("The 'commonmark' parser is not installed; using 'parsedown' instead.", $result['warning']);
        $this->assertNull($result['error']);
    }

    public function test_resolve_markdown_parser_selection_errors_when_missing_and_explicit(): void
    {
        $result = $this->invokeStatic('resolveMarkdownParserSelection', 'parsedown', [
            'commonmark' => false,
            'parsedown' => false,
            'html' => true,
        ], true);

        $this->assertSame('parsedown', $result['driver']);
        $this->assertNull($result['warning']);
        $this->assertSame(
            "The 'parsedown' parser is not installed. Install it with: composer require erusev/parsedown",
            $result['error']
        );
    }

    public function test_validate_requested_parser_option_rejects_missing_value(): void
    {
        $command = new InstallCommand();
        $command->setOptions(['parser' => true]);

        [$result, $output] = $this->captureOutput(
            fn () => $this->callPrivateMethod($command, 'validateRequestedParserOption')
        );

        $this->assertFalse($result);
        $this->assertStringContainsString('The --parser option requires a value.', $output);
    }

    public function test_update_config_value_replaces_env_boolean_fallback(): void
    {
        $this->copyFile(
            $this->sandboxPath('config/tenancy.php'),
            $this->sandboxPath('user/config/tenancy.php')
        );

        $command = new InstallCommand();

        $this->callPrivateMethod($command, 'updateConfigValue', ['tenancy.php', "'enabled'", true]);

        $contents = (string) file_get_contents($this->sandboxPath('user/config/tenancy.php'));

        $this->assertStringContainsString("'enabled' => true,", $contents);
        $this->assertStringNotContainsString("'enabled' => env('TENANCY_ENABLED', false)", $contents);
    }

    public function test_update_nested_config_value_scopes_to_requested_path(): void
    {
        $this->copyFile(
            $this->sandboxPath('config/db.php'),
            $this->sandboxPath('user/config/db.php')
        );

        $command = new InstallCommand();

        $this->callPrivateMethod($command, 'updateNestedConfigValue', [
            'db.php',
            ['connections', 'pgsql', 'host'],
            'db.example.test',
        ]);

        $contents = (string) file_get_contents($this->sandboxPath('user/config/db.php'));

        $this->assertMatchesRegularExpression(
            "/'mysql'\s*=>\s*\[.*?'host'\s*=>\s*env\('DB_HOST', '127\.0\.0\.1'\)/s",
            $contents
        );
        $this->assertMatchesRegularExpression(
            "/'pgsql'\s*=>\s*\[.*?'host'\s*=>\s*'db\.example\.test'/s",
            $contents
        );
    }

    private function invokeStatic(string $method, mixed ...$arguments): mixed
    {
        return $this->callPrivateMethod(InstallCommand::class, $method, $arguments);
    }
}
