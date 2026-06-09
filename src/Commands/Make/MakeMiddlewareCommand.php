<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Make;

class MakeMiddlewareCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:middleware {name}';
    }

    public function description(): string
    {
        return 'Create a new middleware class';
    }

    public static function category(): string
    {
        return 'Make';
    }

    public function handle(): int
    {
        return $this->generateClass(
            $this->argument(0),
            'Anvyr\\Loom\\Http\\Middleware',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

class {{ class }}
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
PHP
        );
    }
}
