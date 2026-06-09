<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\Paths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Core\Tenancy\TenantContext;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class PathsTest extends ApplicationTestCase
{
    public function test_paths_service_uses_application_base_path(): void
    {
        $app = $this->makeApplication();

        /** @var Paths $paths */
        $paths = $app->make(Paths::class);

        $this->assertSame($this->sandboxPath(), $paths->base());
        $this->assertSame($this->sandboxPath('public/index.php'), $paths->publicPath('index.php'));
        $this->assertSame($this->sandboxPath('config/app.php'), $paths->config('app.php'));
        $this->assertSame($this->sandboxPath('storage/cache'), $paths->storage('cache'));
        $this->assertSame($this->sandboxPath('user/content/pages'), $paths->content('pages'));
    }

    public function test_helpers_use_paths_service_when_application_exists(): void
    {
        $app = $this->makeApplication();
        $this->setBasePathOverride($this->tmpDir . '/wrong-root');

        /** @var Paths $paths */
        $paths = $app->make(Paths::class);

        $this->assertSame($paths->base('config'), base_path('config'));
        $this->assertSame($paths->publicPath('favicon.ico'), public_path('favicon.ico'));
        $this->assertSame($paths->storage('cache'), storage_path('cache'));
        $this->assertSame($paths->config('app.php'), config_path('app.php'));
    }

    public function test_view_path_preserves_absolute_configured_root(): void
    {
        $this->makeApplication();
        config(['view.path' => $this->tmpDir . '/absolute-views']);

        $this->assertSame($this->tmpDir . '/absolute-views', view_path());
        $this->assertSame($this->tmpDir . '/absolute-views/page.velvet.php', view_path('page.velvet.php'));
    }

    public function test_paths_use_tenant_scoped_roots_when_tenancy_is_enabled(): void
    {
        $app = $this->makeApplication();
        config(['view.path' => 'user/views']);

        /** @var TenancyState $state */
        $state = $app->make(TenancyState::class);
        $state->setConfig([
            'enabled' => true,
            'paths' => [
                'user_root' => 'user/tenants',
                'storage_root' => 'storage/tenants',
            ],
        ]);
        $state->setCurrent(new TenantContext('demo'));

        /** @var Paths $paths */
        $paths = $app->make(Paths::class);

        $this->assertSame($this->sandboxPath('user/tenants/demo/content/pages'), $paths->content('pages'));
        $this->assertSame($this->sandboxPath('storage/tenants/demo/cache'), $paths->storage('cache'));
        $this->assertSame($this->sandboxPath('user/tenants/demo/views/partials'), view_path('partials'));
    }
}
