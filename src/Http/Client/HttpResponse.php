<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Client;

final class HttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(bool $assoc = true): mixed
    {
        return json_decode($this->body, $assoc, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, list<string>> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $lower) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function successful(): bool
    {
        return $this->ok();
    }

    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    public function failed(): bool
    {
        return $this->status >= 400;
    }

    /** @throws HttpRequestException */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpRequestException(
                "HTTP request returned status {$this->status}",
                $this->status,
                $this,
            );
        }

        return $this;
    }
}
