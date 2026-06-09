<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Http\AssetServer;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class AssetRegistryOwnershipTest extends ApplicationTestCase
{
    public function test_application_reuses_preseeded_asset_server_state(): void
    {
        app(AssetServer::class)->registerModule('admin', '/tmp/admin-assets');

        $app = $this->makeApplication();

        /** @var AssetServer $server */
        $server = $app->make(AssetServer::class);

        $this->assertSame('/tmp/admin-assets', $server->modulePath('admin'));
        $this->assertSame($server, app(AssetServer::class));
    }

    public function test_module_registration_persists_on_asset_server(): void
    {
        $app = $this->makeApplication();

        /** @var AssetServer $server */
        $server = $app->make(AssetServer::class);
        $server->registerModule('blog', '/tmp/blog-assets');

        $this->assertSame('/tmp/blog-assets', $app->make(AssetServer::class)->modulePath('blog'));
    }
}
