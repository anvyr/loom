<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Make;

class MakeConsoleCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:command {name}';
    }

    public function description(): string
    {
        return 'Create a new console command';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        return $this->generateClass(
            $this->argument(0),
            'Anvyr\\Loom\\Commands',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Anvyr\Loom\Commands\Command;

class {{ class }} extends Command
{
    public function signature(): string
    {
        return 'command:name';
    }

    public function description(): string
    {
        return 'Command description';
    }

    public function handle(): int
    {
        $this->info('Command executed');
        return 0;
    }
}
PHP
        );
    }
}
