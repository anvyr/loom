<?php

declare(strict_types=1);

namespace Anvyr\Loom\Exceptions;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        string $message = '',
        /** @var array<string, string> */
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function toResponse(Request $request): Response
    {
        $message = $this->getMessage() ?: $this->defaultMessage();

        $response = $request->expectsJson()
            ? Response::json(['message' => $message, 'status' => $this->status], $this->status)
            : Response::error($message, $this->status);

        if ($this->headers !== []) {
            $response = $response->headers($this->headers);
        }

        return $response;
    }

    private function defaultMessage(): string
    {
        return match ($this->status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Server Error',
            default => 'HTTP Error',
        };
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function serverError(string $message = 'Server Error'): self
    {
        return new self(500, $message);
    }
}
