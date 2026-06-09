<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Http;

final class RequestLifecycleTestService
{
    public function getMessage(): string
    {
        return 'Service injected!';
    }
}
