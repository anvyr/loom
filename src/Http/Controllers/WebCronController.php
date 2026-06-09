<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Controllers;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Http\RateLimiting\RateLimiter;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Scheduling\Schedule;

class WebCronController
{
    public function __construct(
        private readonly Application $app,
        private readonly Schedule $schedule,
        private readonly ConfigRepository $config,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->isIpAllowed()) {
            return Response::html('Forbidden: IP not allowed.', 403);
        }

        if (!$this->isAuthorized($request)) {
            return Response::html('Forbidden: Invalid or missing cron token.', 403);
        }

        $rateLimitResponse = $this->checkRateLimit();
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        $tasks = $this->schedule->getDueTasks();
        $count = 0;

        foreach ($tasks as $task) {
            if ($cmd = $task->getCommand()) {
                $binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
                $loom = $this->app->basePath() . '/loom';

                $command = build_cli_command($binary, $loom, $cmd) . ' > /dev/null 2>&1 &';
                exec($command);
                $count++;
            } else {
                $task->run($this->app);
                $count++;
            }
        }

        return Response::html("Ran {$count} scheduled tasks.");
    }

    private function isAuthorized(Request $request): bool
    {
        $configuredToken = (string) $this->config->get('app.cron_token', '');
        if ($configuredToken === '') {
            return false;
        }

        $token = (string) $request->query('token', '');
        if ($token !== '' && hash_equals($configuredToken, $token)) {
            return true;
        }

        if (!(bool) $this->config->get('app.cron_signed_urls', false)) {
            return false;
        }

        $expires = (int) $request->query('expires', 0);
        $signature = (string) $request->query('signature', '');

        if ($expires <= 0 || $signature === '' || $expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', (string) $expires, $configuredToken);

        return hash_equals($expected, $signature);
    }

    private function isIpAllowed(): bool
    {
        $allowlist = $this->config->get('app.cron_allowed_ips', []);
        if (!is_array($allowlist) || $allowlist === []) {
            return true;
        }

        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remoteIp === '') {
            return false;
        }

        foreach ($allowlist as $allowed) {
            if (!is_string($allowed) || $allowed === '') {
                continue;
            }

            if ($allowed === '*') {
                return true;
            }

            if (str_contains($allowed, '/')) {
                if ($this->ipInCidr($remoteIp, $allowed)) {
                    return true;
                }
                continue;
            }

            if ($allowed === $remoteIp) {
                return true;
            }
        }

        return false;
    }

    private function checkRateLimit(): ?Response
    {
        if (!(bool) $this->config->get('app.cron_rate_limit.enabled', false)) {
            return null;
        }

        $attempts = max(1, (int) $this->config->get('app.cron_rate_limit.attempts', 60));
        $decay = max(1, (int) $this->config->get('app.cron_rate_limit.decay', 60));
        $remoteIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $result = $this->rateLimiter->attempt('webcron:' . $remoteIp, $attempts, $decay);
        if ($result['allowed']) {
            return null;
        }

        return Response::html('Too Many Requests', 429);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if (!is_string($subnet) || !is_string($bits) || !is_numeric($bits)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bitsInt = (int) $bits;
        $maxBits = strlen($ipBin) * 8;
        if ($bitsInt < 0 || $bitsInt > $maxBits) {
            return false;
        }

        $bytes = intdiv($bitsInt, 8);
        $remainingBits = $bitsInt % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~(0xff >> $remainingBits)) & 0xff;
        $ipByte = ord($ipBin[$bytes]);
        $subnetByte = ord($subnetBin[$bytes]);

        return ($ipByte & $mask) === ($subnetByte & $mask);
    }
}
