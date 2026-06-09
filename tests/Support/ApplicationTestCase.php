<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Core\Application;

abstract class ApplicationTestCase extends TestCase
{
    protected string $sandboxRoot;

    protected function prepareFilesystem(): void
    {
        $this->sandboxRoot = $this->tmpDir . '/sandbox';

        $this->copyFile(
            $this->frameworkRoot('bootstrap/app.php'),
            $this->sandboxPath('bootstrap/app.php'),
        );
        $this->copyDirectory(
            $this->frameworkRoot('config'),
            $this->sandboxPath('config'),
        );
        $this->copyDirectory(
            $this->frameworkRoot('database'),
            $this->sandboxPath('database'),
        );

        $this->mkdir($this->sandboxPath('storage/cache'));
        $this->mkdir($this->sandboxPath('storage/data'));
        $this->mkdir($this->sandboxPath('storage/logs'));
        $this->mkdir($this->sandboxPath('storage/sessions'));
        $this->mkdir($this->sandboxPath('user/config'));
        $this->mkdir($this->sandboxPath('user/content/pages'));
        $this->mkdir($this->sandboxPath('user/modules'));
        $this->mkdir($this->sandboxPath('user/views'));
    }

    protected function basePathRoot(): string
    {
        return $this->sandboxRoot;
    }

    protected function configRoot(): string
    {
        return $this->sandboxPath('config');
    }

    protected function userConfigRoot(): string
    {
        return $this->sandboxPath('user/config');
    }

    protected function sandboxPath(string $path = ''): string
    {
        return $path === ''
            ? $this->sandboxRoot
            : $this->sandboxRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    protected function makeApplication(bool $boot = false): Application
    {
        if (Application::hasInstance() && Application::getInstance()->basePath() === $this->sandboxRoot) {
            $app = Application::getInstance();

            if ($boot) {
                $app->boot();
            }

            return $app;
        }

        return $this->freshApplication($boot);
    }

    protected function freshApplication(bool $boot = false): Application
    {
        $app = new Application($this->sandboxRoot, $this->buildConfigRepository(), $this->buildTenancyState());

        if ($boot) {
            $app->boot();
        }

        return $app;
    }

    protected function requireBootstrappedApplication(bool $boot = true): Application
    {
        /** @var Application $app */
        $app = require $this->sandboxPath('bootstrap/app.php');

        if ($boot) {
            $app->boot();
        }

        return $app;
    }
}
