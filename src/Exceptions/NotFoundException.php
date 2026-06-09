<?php

declare(strict_types=1);

namespace Anvyr\Loom\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct(404, $message);
    }
}
