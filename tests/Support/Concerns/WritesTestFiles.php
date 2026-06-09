<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

trait WritesTestFiles
{
    protected function writeFile(string $path, string $contents): void
    {
        $this->mkdir(dirname($path));
        file_put_contents($path, $contents);
    }

    protected function writeJsonFile(string $path, array $data): void
    {
        $this->writeFile(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    protected function readJsonFile(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function writePhpConfigFile(string $path, array $config): void
    {
        $this->writeFile($path, "<?php\n\nreturn " . var_export($config, true) . ";\n");
    }
}
