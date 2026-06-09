<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Make;

class MakeModelCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:model {name}';
    }

    public function description(): string
    {
        return 'Create a new model class';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        return $this->generateClass(
            $this->argument(0),
            'Anvyr\\Loom\\Models',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

class {{ class }}
{
}
PHP
        );
    }
}
