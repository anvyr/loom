<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Core;

use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Tests\Support\Concerns\WritesTestFiles;
use Anvyr\Loom\Tests\Support\TestCase;

final class ConfigRepositoryTest extends TestCase
{
    use WritesTestFiles;

    private string $testConfigPath;
    private ConfigRepository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigPath = $this->tmpDir . '/config';
        $this->writePhpConfigFile($this->testConfigPath . '/app.php', ['name' => 'TestApp', 'debug' => true]);
        $this->writePhpConfigFile($this->testConfigPath . '/database.php', [
            'driver' => 'sqlite',
            'connections' => ['test' => ['host' => 'localhost']],
        ]);
        $this->writePhpConfigFile($this->testConfigPath . '/nested.php', [
            'level1' => ['level2' => ['level3' => 'deep value']],
        ]);

        $this->config = new ConfigRepository($this->testConfigPath);
    }

    public function test_get_simple_config_value(): void
    {
        $this->assertSame('TestApp', $this->config->get('app.name'));
    }

    public function test_get_nested_config_value_with_dot_notation(): void
    {
        $this->assertSame('deep value', $this->config->get('nested.level1.level2.level3'));
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        $this->assertSame('default-value', $this->config->get('nonexistent.key', 'default-value'));
    }

    public function test_get_entire_config_file(): void
    {
        $app = $this->config->get('app');

        $this->assertIsArray($app);
        $this->assertSame('TestApp', $app['name']);
        $this->assertTrue($app['debug']);
    }

    public function test_set_simple_value(): void
    {
        $this->config->set('app.version', '1.0.0');
        $this->assertSame('1.0.0', $this->config->get('app.version'));
    }

    public function test_set_nested_value_with_dot_notation(): void
    {
        $this->config->set('database.connections.test.port', 3306);
        $this->assertSame(3306, $this->config->get('database.connections.test.port'));
    }
}
