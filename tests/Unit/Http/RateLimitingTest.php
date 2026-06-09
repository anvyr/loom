<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Contracts\CacheDriver;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Http\Middleware\ThrottleRequests;
use Anvyr\Loom\Http\RateLimiting\Limit;
use Anvyr\Loom\Http\RateLimiting\RateLimiter;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Tests\Support\TestCase;

final class RateLimitingTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private CacheDriver $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new class () implements CacheDriver {
            public array $storage = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->storage[$key] ?? $default;
            }
            public function set(string $key, mixed $value, int $ttl = 3600): bool
            {
                $this->storage[$key] = $value;
                return true;
            }
            public function has(string $key): bool
            {
                return isset($this->storage[$key]);
            }
            public function delete(string $key): bool
            {
                unset($this->storage[$key]);
                return true;
            }
            public function clear(): bool
            {
                $this->storage = [];
                return true;
            }
            public function remember(string $key, int $ttl, callable $callback): mixed
            {
                return $callback();
            }
        };

        $this->rateLimiter = new RateLimiter($this->cache);

        config([
            'http.rate_limit.enabled' => true,
            'http.rate_limit.default' => 'test',
            'http.rate_limit.limiters' => [
                'test' => ['attempts' => 2, 'decay' => 60, 'by' => 'ip'],
            ],
            'http.rate_limit.whitelist' => [],
            'http.rate_limit.max_attempts' => 2,
            'http.rate_limit.decay_minutes' => 1,
        ]);
    }

    // Limit

    public function test_limit_per_minute(): void
    {
        $limit = Limit::perMinute(60);

        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }

    public function test_limit_per_hour(): void
    {
        $limit = Limit::perHour(100);

        $this->assertSame(100, $limit->maxAttempts);
        $this->assertSame(3600, $limit->decaySeconds);
    }

    public function test_limit_per_day(): void
    {
        $limit = Limit::perDay(1000);

        $this->assertSame(1000, $limit->maxAttempts);
        $this->assertSame(86400, $limit->decaySeconds);
    }

    public function test_limit_none_is_unlimited(): void
    {
        $limit = Limit::none();

        $this->assertTrue($limit->isUnlimited());
    }

    public function test_limit_fluent_chaining(): void
    {
        $limit = Limit::perMinute(30)->by('ip_route')->withKey('custom');

        $this->assertSame(30, $limit->maxAttempts);
        $this->assertSame('ip_route', $limit->by);
        $this->assertSame('custom', $limit->key);
    }

    // RateLimiter

    public function test_register_static_limiter(): void
    {
        $this->rateLimiter->for('api', Limit::perMinute(100));

        $this->assertTrue($this->rateLimiter->hasLimiter('api'));

        $limit = $this->rateLimiter->limiter('api');
        $this->assertSame(100, $limit->maxAttempts);
    }

    public function test_register_dynamic_limiter(): void
    {
        $this->rateLimiter->for(
            'dynamic',
            fn (Request $request) =>
            $request->ip() === '10.0.0.1' ? Limit::perMinute(1000) : Limit::perMinute(10)
        );

        $this->assertTrue($this->rateLimiter->hasLimiter('dynamic'));
    }

    public function test_attempt_allows_under_limit(): void
    {
        $result = $this->rateLimiter->attempt('test-key', 5, 60);

        $this->assertTrue($result['allowed']);
        $this->assertSame(4, $result['remaining']);
    }

    public function test_attempt_blocks_over_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->attempt('test-key', 5, 60);
        }

        $result = $this->rateLimiter->attempt('test-key', 5, 60);

        $this->assertFalse($result['allowed']);
        $this->assertSame(0, $result['remaining']);
    }

    public function test_attempts_returns_hit_count(): void
    {
        $this->rateLimiter->attempt('counter-key', 10, 60);
        $this->rateLimiter->attempt('counter-key', 10, 60);
        $this->rateLimiter->attempt('counter-key', 10, 60);

        $this->assertSame(3, $this->rateLimiter->attempts('counter-key'));
    }

    public function test_clear_resets_attempts(): void
    {
        $this->rateLimiter->attempt('clear-key', 5, 60);
        $this->rateLimiter->attempt('clear-key', 5, 60);
        $this->rateLimiter->clear('clear-key');

        $this->assertSame(0, $this->rateLimiter->attempts('clear-key'));
    }

    public function test_whitelist_management(): void
    {
        $this->rateLimiter->whitelist(['127.0.0.1', '::1']);

        $this->assertTrue($this->rateLimiter->isWhitelisted('127.0.0.1'));
        $this->assertFalse($this->rateLimiter->isWhitelisted('192.168.1.1'));
    }

    public function test_returns_null_for_unknown_limiter(): void
    {
        $this->assertNull($this->rateLimiter->limiter('nonexistent'));
    }

    public function test_resolve_key_with_custom_key(): void
    {
        $request = Request::capture();
        $limit = Limit::perMinute(60)->withKey('custom:my-key');

        $this->assertSame('custom:my-key', $this->rateLimiter->resolveKey($request, $limit));
    }

    // ThrottleRequests Middleware

    public function test_middleware_allows_under_limit(): void
    {
        $middleware = $this->makeMiddleware();
        $request = Request::capture();
        $next = fn () => Response::html('ok');

        $response = $middleware->handle($request, $next);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('2', $response->getHeader('X-RateLimit-Limit'));
        $this->assertSame('1', $response->getHeader('X-RateLimit-Remaining'));
    }

    public function test_middleware_blocks_over_limit(): void
    {
        $middleware = $this->makeMiddleware();
        $request = Request::capture();
        $next = fn () => Response::html('ok');

        $middleware->handle($request, $next);
        $middleware->handle($request, $next);
        $response = $middleware->handle($request, $next);

        $this->assertSame(429, $response->getStatus());
        $this->assertStringContainsString('Too Many Requests', $response->getContent());
    }

    public function test_middleware_uses_named_limiter(): void
    {
        $this->rateLimiter->for('generous', Limit::perMinute(100));
        $middleware = $this->makeMiddleware()->setLimiter('generous');

        $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));

        $this->assertSame('100', $response->getHeader('X-RateLimit-Limit'));
    }

    public function test_middleware_uses_inline_limit(): void
    {
        $middleware = $this->makeMiddleware()->setLimiter('50,2');

        $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));

        $this->assertSame('50', $response->getHeader('X-RateLimit-Limit'));
    }

    public function test_middleware_whitelist_bypasses(): void
    {
        $rateLimiter = new RateLimiter($this->cache);
        $rateLimiter->whitelist(['127.0.0.1', 'unknown']);
        $middleware = $this->makeMiddleware($rateLimiter);

        for ($i = 0; $i < 10; $i++) {
            $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));
            $this->assertSame(200, $response->getStatus());
        }
    }

    public function test_middleware_disabled_allows_all(): void
    {
        config(['http.rate_limit.enabled' => false]);
        $middleware = $this->makeMiddleware();

        for ($i = 0; $i < 10; $i++) {
            $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));
            $this->assertSame(200, $response->getStatus());
        }
    }

    public function test_middleware_unlimited_limiter(): void
    {
        $this->rateLimiter->for('unlimited', Limit::none());
        $middleware = $this->makeMiddleware()->setLimiter('unlimited');

        for ($i = 0; $i < 10; $i++) {
            $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));
            $this->assertSame(200, $response->getStatus());
        }
    }

    public function test_middleware_dynamic_limiter(): void
    {
        $this->rateLimiter->for('dynamic', fn () => Limit::perMinute(5));
        $middleware = $this->makeMiddleware()->setLimiter('dynamic');

        $response = $middleware->handle(Request::capture(), fn () => Response::html('ok'));

        $this->assertSame('5', $response->getHeader('X-RateLimit-Limit'));
    }

    private function makeMiddleware(?RateLimiter $rateLimiter = null): ThrottleRequests
    {
        return new ThrottleRequests(
            $rateLimiter ?? $this->rateLimiter,
            Application::getInstance()->make(ConfigRepository::class),
        );
    }
}
