<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Client;

final class HttpRequestException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code,
        private readonly HttpResponse $response,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function response(): HttpResponse
    {
        return $this->response;
    }
}
