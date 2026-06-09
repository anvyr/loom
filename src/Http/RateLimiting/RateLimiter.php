<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\RateLimiting;

use Anvyr\Loom\Contracts\CacheDriver;
use Anvyr\Loom\Contracts\RateLimiterInterface;
use Anvyr\Loom\Http\Request;
use Closure;

class RateLimiter implements RateLimiterInterface
{
    /** @var array<string, Limit|Closure> */
    protected array $limiters = [];

    /** @var array<string> */
    protected array $whitelist = [];

    public function __construct(
        private readonly CacheDriver $cache
    ) {
    }

    public function for(string $name, Limit|Closure $limiter): self
    {
        $this->limiters[$name] = $limiter;
        return $this;
    }

    public function limiter(string $name, ?Request $request = null): ?Limit
    {
        if (!isset($this->limiters[$name])) {
            return null;
        }

        $limiter = $this->limiters[$name];

        if ($limiter instanceof Closure) {
            return $limiter($request);
        }

        return $limiter;
    }

    public function hasLimiter(string $name): bool
    {
        return isset($this->limiters[$name]);
    }

    /** @return array<string> */
    public function getLimiterNames(): array
    {
        return array_keys($this->limiters);
    }

    /** @param array<string> $ips */
    public function whitelist(array $ips): self
    {
        $this->whitelist = $ips;
        return $this;
    }

    public function addToWhitelist(string $ip): self
    {
        if (!in_array($ip, $this->whitelist, true)) {
            $this->whitelist[] = $ip;
        }
        return $this;
    }

    public function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->whitelist, true);
    }

    /** @return array<string> */
    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /** @return array{allowed: bool, remaining: int, retryAfter: int} */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $cacheKey = $this->getCacheKey($key);
        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            $this->cache->set($cacheKey, [
                'hits' => 1,
                'reset_at' => time() + $decaySeconds,
            ], $decaySeconds);

            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'retryAfter' => $decaySeconds,
            ];
        }

        if (!is_array($data) || !isset($data['hits'], $data['reset_at'])) {
            $this->cache->delete($cacheKey);
            return $this->attempt($key, $maxAttempts, $decaySeconds);
        }

        $hits = (int) $data['hits'];
        $resetAt = (int) $data['reset_at'];

        if ($hits >= $maxAttempts) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retryAfter' => max(0, $resetAt - time()),
            ];
        }

        $hits++;
        $data['hits'] = $hits;
        $ttl = max(1, $resetAt - time());
        $this->cache->set($cacheKey, $data, $ttl);

        return [
            'allowed' => true,
            'remaining' => max(0, $maxAttempts - $hits),
            'retryAfter' => $ttl,
        ];
    }

    public function attempts(string $key): int
    {
        $data = $this->cache->get($this->getCacheKey($key));

        if (!is_array($data) || !isset($data['hits'])) {
            return 0;
        }

        return (int) $data['hits'];
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    public function clear(string $key): bool
    {
        return $this->cache->delete($this->getCacheKey($key));
    }

    public function availableIn(string $key): int
    {
        $data = $this->cache->get($this->getCacheKey($key));

        if (!is_array($data) || !isset($data['reset_at'])) {
            return 0;
        }

        return max(0, (int) $data['reset_at'] - time());
    }

    public function resolveKey(Request $request, Limit $limit, ?string $limiterName = null): string
    {
        if ($limit->key !== null) {
            return $limit->key;
        }

        $parts = ['throttle'];

        if ($limiterName !== null) {
            $parts[] = $limiterName;
        }

        $by = $limit->by ?? 'ip';

        switch ($by) {
            case 'ip':
                $parts[] = $request->ip() ?? 'unknown';
                break;

            case 'ip_route':
                $parts[] = $request->ip() ?? 'unknown';
                $parts[] = $request->method() . ':' . $request->path();
                break;

            default:
                $parts[] = $by;
        }

        return implode(':', $parts);
    }

    protected function getCacheKey(string $key): string
    {
        return 'ratelimit:' . $key;
    }
}
