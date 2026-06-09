<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Http\Controllers\WebCronController;
use Anvyr\Loom\Http\RateLimiting\RateLimiter;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Scheduling\Schedule;
use Anvyr\Loom\Tests\Support\TestCase;

final class WebCronControllerTest extends TestCase
{
    public function test_denies_invalid_token(): void
    {
        $app = new Application($this->tmpDir);

        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);
        $_GET = [];

        $controller = $this->makeController($app, new Schedule());
        $response = $controller->handle(Request::capture());

        $this->assertSame(403, $response->getStatus());
        $this->assertStringContainsString('Forbidden', $response->getContent());
    }

    public function test_runs_due_tasks_and_outputs_count(): void
    {
        $app = new Application($this->tmpDir);

        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);
        $_GET = ['token' => 'secret'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        });
        $schedule->call(function () use (&$ran) {
            $ran++;
        });

        $controller = $this->makeController($app, $schedule);
        $response = $controller->handle(Request::capture());

        $this->assertSame(2, $ran);
        $this->assertStringContainsString('Ran 2 scheduled tasks.', $response->getContent());
    }

    public function test_denies_ip_not_on_allowlist(): void
    {
        $app = new Application($this->tmpDir);

        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => ['10.0.0.1'],
        ]);

        $_GET = ['token' => 'secret'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $controller = $this->makeController($app, new Schedule());
        $response = $controller->handle(Request::capture());

        $this->assertSame(403, $response->getStatus());
        $this->assertStringContainsString('IP not allowed', $response->getContent());
    }

    public function test_accepts_valid_signed_url_when_enabled(): void
    {
        $app = new Application($this->tmpDir);

        $token = 'secret';
        $expires = time() + 120;
        $signature = hash_hmac('sha256', (string) $expires, $token);

        config([
            'app.cron_token' => $token,
            'app.cron_signed_urls' => true,
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => false,
        ]);

        $_GET = [
            'expires' => (string) $expires,
            'signature' => $signature,
        ];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ran = 0;
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) {
            $ran++;
        });

        $controller = $this->makeController($app, $schedule);
        $response = $controller->handle(Request::capture());

        $this->assertSame(1, $ran);
        $this->assertStringContainsString('Ran 1 scheduled tasks.', $response->getContent());
    }

    public function test_rate_limit_blocks_when_exceeded(): void
    {
        $app = new Application($this->tmpDir);

        config([
            'app.cron_token' => 'secret',
            'app.cron_allowed_ips' => [],
            'app.cron_rate_limit.enabled' => true,
            'app.cron_rate_limit.attempts' => 1,
            'app.cron_rate_limit.decay' => 60,
        ]);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $schedule = new Schedule();
        $controller = $this->makeController($app, $schedule);

        $_GET = ['token' => 'secret'];
        $controller->handle(Request::capture());

        $_GET = ['token' => 'secret'];
        $response = $controller->handle(Request::capture());

        $this->assertSame(429, $response->getStatus());
        $this->assertStringContainsString('Too Many Requests', $response->getContent());
    }

    private function makeController(Application $app, Schedule $schedule): WebCronController
    {
        return new WebCronController(
            $app,
            $schedule,
            $app->make(ConfigRepository::class),
            $app->make(RateLimiter::class),
        );
    }
}
