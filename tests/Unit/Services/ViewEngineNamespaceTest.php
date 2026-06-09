<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\ViewEngineTestCase;
use RuntimeException;

final class ViewEngineNamespaceTest extends ViewEngineTestCase
{
    public function test_namespace_resolution(): void
    {
        $moduleViewDir = $this->writeModuleView('blog', 'index', 'Blog Index');

        $this->engine->namespace('blog', $moduleViewDir);
        $output = $this->engine->render('blog::index');

        $this->assertSame('Blog Index', $output);
    }

    public function test_user_views_override_namespace(): void
    {
        $moduleViewDir = $this->writeModuleView('shop', 'cart', 'Module Cart');
        $this->writeFile($this->viewPath('shop/cart.velvet.php'), 'Custom Cart');

        $this->engine->namespace('shop', $moduleViewDir);
        $output = $this->engine->render('shop::cart');

        $this->assertSame('Custom Cart', $output);
    }

    public function test_throws_for_unknown_namespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View 'unknown::view' not found");

        $this->engine->render('unknown::view');
    }
}
