<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Http;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

final class RequestLifecycleTestController
{
    public function __construct(
        private readonly RequestLifecycleTestService $service
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html('<h1>' . $this->service->getMessage() . '</h1>');
    }
}
