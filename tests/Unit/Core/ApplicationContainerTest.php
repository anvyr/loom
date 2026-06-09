<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ServiceProvider;
use Anvyr\Loom\Tests\Support\TestCase;
use RuntimeException;

final class ApplicationContainerTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application($this->tmpDir);
    }

    public function test_bind_registers_factory(): void
    {
        $this->app->bind('counter', function () {
            static $count = 0;
            return ++$count;
        });

        $this->assertSame(1, $this->app->get('counter'));
        $this->assertSame(2, $this->app->get('counter'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->app->singleton('unique', fn () => new \stdClass());

        $first = $this->app->get('unique');
        $second = $this->app->get('unique');

        $this->assertSame($first, $second);
    }

    public function test_instance_stores_existing_object(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';

        $this->app->instance('myobj', $obj);

        $this->assertSame($obj, $this->app->get('myobj'));
    }

    public function test_has_returns_true_for_registered_service(): void
    {
        $this->app->bind('exists', fn () => 'yes');

        $this->assertTrue($this->app->has('exists'));
        $this->assertFalse($this->app->has('nope'));
    }

    public function test_alias_resolves_to_original(): void
    {
        $this->app->singleton('original.service', fn () => 'the value');
        $this->app->alias('original.service', 'short');

        $this->assertSame('the value', $this->app->get('short'));
    }

    public function test_magic_get_returns_service(): void
    {
        $this->app->bind('magic', fn () => 'abracadabra');

        $this->assertSame('abracadabra', $this->app->magic);
    }

    public function test_make_autowires_with_nested_dependencies(): void
    {
        $instance = $this->app->make(ServiceWithDependency::class);

        $this->assertInstanceOf(ServiceWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleDependency::class, $instance->dep);
    }

    public function test_make_uses_default_values_for_non_typed_params(): void
    {
        $instance = $this->app->make(ServiceWithDefault::class);

        $this->assertSame('default-value', $instance->value);
    }

    public function test_make_throws_for_unresolvable_parameter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot autowire');

        $this->app->make(UnresolvableService::class);
    }

    public function test_make_prefers_container_over_autowire(): void
    {
        $custom = new SimpleDependency();
        $custom->marker = 'custom';
        $this->app->instance(SimpleDependency::class, $custom);

        $instance = $this->app->make(ServiceWithDependency::class);

        $this->assertSame('custom', $instance->dep->marker);
    }

    public function test_base_path_returns_correct_path(): void
    {
        $this->assertSame($this->tmpDir, $this->app->basePath());
        $this->assertSame($this->tmpDir . DIRECTORY_SEPARATOR . 'config', $this->app->basePath('config'));
    }

    public function test_environment_returns_config_value(): void
    {
        config(['app.env' => 'testing']);

        $this->assertSame('testing', $this->app->environment());
    }

    public function test_is_debug_returns_config_value(): void
    {
        config(['app.debug' => true]);
        $this->assertTrue($this->app->isDebug());

        config(['app.debug' => false]);
        $this->assertFalse($this->app->isDebug());
    }

    public function test_register_calls_provider_register_method(): void
    {
        $provider = new TestServiceProvider($this->app);
        $this->app->register($provider);

        $this->assertTrue($this->app->has('test.registered'));
    }

    public function test_boot_calls_provider_boot_methods(): void
    {
        $provider = new TestServiceProvider($this->app);
        $this->app->register($provider);
        $this->app->boot();

        $this->assertTrue($this->app->has('test.booted'));
    }

    public function test_boot_only_runs_once(): void
    {
        $counter = 0;
        $this->app->bind('boot.counter', function () use (&$counter) {
            return ++$counter;
        });

        $provider = new BootCounterProvider($this->app);
        $this->app->register($provider);

        $this->app->boot();
        $this->app->boot();
        $this->app->boot();

        $this->assertSame(1, BootCounterProvider::$bootCount);
    }

    public function test_static_instance_management(): void
    {
        Application::clearInstance();

        $this->assertFalse(Application::hasInstance());

        Application::setInstance($this->app);

        $this->assertTrue(Application::hasInstance());
        $this->assertSame($this->app, Application::getInstance());

        Application::clearInstance();
    }

    public function test_get_instance_throws_when_not_set(): void
    {
        Application::clearInstance();

        $this->expectException(RuntimeException::class);
        Application::getInstance();
    }
}

class SimpleDependency
{
    public string $marker = 'original';
}

class ServiceWithDependency
{
    public function __construct(public SimpleDependency $dep)
    {
    }
}

class ServiceWithDefault
{
    public function __construct(public string $value = 'default-value')
    {
    }
}

class UnresolvableService
{
    public function __construct(public string $required)
    {
    }
}

class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('test.registered', true);
    }

    public function boot(): void
    {
        $this->app->instance('test.booted', true);
    }
}

class BootCounterProvider extends ServiceProvider
{
    public static int $bootCount = 0;

    public function register(): void
    {
    }

    public function boot(): void
    {
        self::$bootCount++;
    }
}
