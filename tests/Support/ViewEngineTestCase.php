<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support;

use Anvyr\Loom\Services\ViewEngine;
use Anvyr\Loom\Tests\Support\Concerns\WritesTestFiles;

abstract class ViewEngineTestCase extends TestCase
{
    use WritesTestFiles;

    protected string $viewDir;
    protected string $cacheDir;
    protected ViewEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewDir = $this->tmpDir . '/views';
        $this->cacheDir = $this->tmpDir . '/cache/views';
        $this->mkdir($this->viewDir);
        $this->mkdir($this->cacheDir);

        $this->engine = new ViewEngine($this->viewDir, $this->cacheDir);
    }

    protected function viewPath(string $path = ''): string
    {
        return $path === '' ? $this->viewDir : $this->viewDir . '/' . ltrim($path, '/');
    }

    protected function cachePath(string $path = ''): string
    {
        return $path === '' ? $this->cacheDir : $this->cacheDir . '/' . ltrim($path, '/');
    }

    protected function writeView(string $view, string $contents): void
    {
        $this->writeFile(
            $this->viewPath(str_replace('.', '/', $view) . '.velvet.php'),
            $contents,
        );
    }

    protected function writeModuleView(string $namespace, string $view, string $contents): string
    {
        $moduleViewDir = $this->tmpDir . '/modules/' . $namespace . '/views';
        $this->writeFile(
            $moduleViewDir . '/' . str_replace('.', '/', $view) . '.velvet.php',
            $contents,
        );

        return $moduleViewDir;
    }

    protected function withStringEvaluationDisabled(callable $callback): void
    {
        config(['view.allow_string_evaluation' => false]);

        try {
            $callback();
        } finally {
            config(['view.allow_string_evaluation' => true]);
        }
    }

    protected function clearViewOutputBuffers(): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
    }
}
