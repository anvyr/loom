<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\RateLimiting;

class Limit
{
    public function __construct(
        public int $maxAttempts = 60,
        public int $decaySeconds = 60,
        public ?string $key = null,
        public ?string $by = 'ip'
    ) {
    }

    public static function perMinute(int $maxAttempts): self
    {
        return new self($maxAttempts, 60);
    }

    public static function perHour(int $maxAttempts): self
    {
        return new self($maxAttempts, 3600);
    }

    public static function perDay(int $maxAttempts): self
    {
        return new self($maxAttempts, 86400);
    }

    public static function perSeconds(int $decaySeconds, int $maxAttempts): self
    {
        return new self($maxAttempts, $decaySeconds);
    }

    public static function none(): self
    {
        return new self(PHP_INT_MAX, 1);
    }

    public function by(string $by): self
    {
        $this->by = $by;
        return $this;
    }

    public function withKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function isUnlimited(): bool
    {
        return $this->maxAttempts >= PHP_INT_MAX;
    }
}
