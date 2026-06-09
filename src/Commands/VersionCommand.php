<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

class VersionCommand extends Command
{
    public static function category(): string
    {
        return 'General';
    }

    public function signature(): string
    {
        return 'version';
    }

    public function description(): string
    {
        return 'Display Anvyr Loom version';
    }

    public function handle(): int
    {
        $registry = app(\Anvyr\Loom\Core\VersionRegistry::class);
        $coreVersion = $registry->getVersion('core');
        $stability = $registry->getStability('core');

        $this->line("\033[1mAnvyr Loom\033[0m \033[33m{$coreVersion}\033[0m");
        $this->line();

        $releaseDate = $registry->getReleaseDate('core');
        if (!empty($releaseDate)) {
            $this->line("Release Date: {$releaseDate}");
        }

        $this->line('PHP Version: ' . PHP_VERSION);
        $this->line('OS: ' . PHP_OS);
        $this->line("Stability: {$stability}");

        $modules = $registry->getModules();
        if (!empty($modules)) {
            $this->line();
            $this->line('Modules:');
            foreach ($modules as $name => $meta) {
                $moduleVersion = $meta['version'] ?? 'unknown';
                $stability = $meta['stability'] ?? 'unknown';
                $requires = $meta['requires']['core'] ?? null;

                $summary = sprintf('  - %s: %s (%s)', $name, $moduleVersion, $stability);
                if ($requires) {
                    $summary .= sprintf(' requires core %s', $requires);
                }

                $this->line($summary);
            }
        }

        if (str_contains(strtolower($stability), 'dev')) {
            $this->line();
            $this->warning('This is a development version');
        }

        $this->line();

        return 0;
    }
}
