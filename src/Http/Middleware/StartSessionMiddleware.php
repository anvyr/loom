<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Middleware;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Core\Paths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Paths $paths,
        private readonly TenancyState $tenancyState
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            $this->configure();
            session_start();
        }

        return $next($request);
    }

    private function configure(): void
    {
        // Session lifetime
        $lifetime = (int) $this->config->get('session.lifetime', 7200);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);

        // Custom session name
        $sessionName = (string) $this->config->get('session.name', 'loom_session');
        session_name($sessionName);

        // Session storage
        $savePath = $this->paths->storage('sessions');
        if (!is_dir($savePath)) {
            mkdir($savePath, 0700, true);
        }
        ini_set('session.save_path', $savePath);

        // Security settings
        ini_set('session.cookie_httponly', (bool) $this->config->get('session.http_only', true) ? '1' : '0');
        ini_set('session.use_strict_mode', (bool) $this->config->get('session.strict_mode', true) ? '1' : '0');
        ini_set('session.use_only_cookies', (bool) $this->config->get('session.use_only_cookies', true) ? '1' : '0');

        // Secure flag - auto-detect HTTPS or read from config
        $secure = $this->config->get('session.secure', 'auto');
        if ($secure === 'auto') {
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $secure = $isHttps || $this->config->get('app.env') === 'production';
        }
        ini_set('session.cookie_secure', $secure ? '1' : '0');

        // SameSite attribute
        $sameSite = (string) $this->config->get('session.same_site', 'Lax');
        ini_set('session.cookie_samesite', $sameSite);

        // Session path and domain
        $path = $this->config->get('session.path');
        $currentTenant = $this->tenancyState->current();
        $tenantPathPrefix = $currentTenant?->urlPrefix();

        if ((!is_string($path) || $path === '') && $tenantPathPrefix !== null) {
            $path = $tenantPathPrefix;
        }
        if (is_string($path) && $path !== '') {
            ini_set('session.cookie_path', $path);
        }
        $domain = $this->config->get('session.domain');
        if (is_string($domain) && $domain !== '') {
            ini_set('session.cookie_domain', $domain);
        }
    }
}
