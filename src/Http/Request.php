<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http;

class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $request;

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, mixed> */
    private array $cookies;
    private ?string $pathPrefix = null;

    public function __construct()
    {
        $this->query = $_GET;
        $this->request = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;

        if ($this->isJson()) {
            $input = file_get_contents('php://input');
            $data = is_string($input) ? json_decode($input, true) : null;
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $this->request = array_merge($this->request, $data);
            }
        }
    }

    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = strtok($this->server['REQUEST_URI'] ?? '/', '?') ?: '/';

        if ($this->pathPrefix !== null && $this->pathPrefix !== '/') {
            $prefix = rtrim($this->pathPrefix, '/');
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                if ($path === '') {
                    $path = '/';
                }
            }
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        return $path;
    }

    public function rawPath(): string
    {
        $path = strtok($this->server['REQUEST_URI'] ?? '/', '?') ?: '/';

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') {
            $path = '/';
        }

        return $path;
    }

    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->host();
        return $scheme . '://' . $host . $this->rawPath();
    }

    public function host(): string
    {
        $host = $this->forwardedHost() ?? ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');
        $host = strtolower((string) $host);

        if (str_contains($host, ':')) {
            [$host] = explode(':', $host, 2);
        }

        return $host;
    }

    public function setPathPrefix(?string $prefix): void
    {
        if ($prefix === null || $prefix === '') {
            $this->pathPrefix = null;
            return;
        }

        $prefix = '/' . ltrim($prefix, '/');
        $this->pathPrefix = rtrim($prefix, '/');
    }

    public function isSecure(): bool
    {
        $forwardedProto = $this->forwardedHeader('proto', 'X-Forwarded-Proto');
        if ($forwardedProto !== null) {
            $protos = array_map('trim', explode(',', strtolower($forwardedProto)));
            if (in_array('https', $protos, true)) {
                return true;
            }
        }

        return isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->request) || array_key_exists($key, $this->query);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $default;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? $default;
    }

    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function ip(): ?string
    {
        $forwardedFor = $this->forwardedHeader('for', 'X-Forwarded-For');
        if ($forwardedFor !== null) {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? null;
    }

    public function ajax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        return str_contains($this->header('Accept', ''), 'application/json');
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        return \Anvyr\Loom\Validation\Validator::make($this->all(), $rules)->validate();
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? '';
        return stripos($contentType, 'application/json') !== false;
    }

    private function forwardedHost(): ?string
    {
        $forwardedHost = $this->forwardedHeader('host', 'X-Forwarded-Host');
        if ($forwardedHost === null) {
            return null;
        }

        $candidates = array_map('trim', explode(',', $forwardedHost));
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function forwardedHeader(string $key, string $defaultHeader): ?string
    {
        if (!$this->isTrustedProxyRequest()) {
            return null;
        }

        $headers = config('http.trusted_proxies.headers', []);
        $headerName = is_array($headers) && isset($headers[$key]) ? (string) $headers[$key] : $defaultHeader;
        $value = $this->header($headerName);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function isTrustedProxyRequest(): bool
    {
        if (!(bool) config('http.trusted_proxies.enabled', false)) {
            return false;
        }

        $remoteAddr = (string) ($this->server['REMOTE_ADDR'] ?? '');
        if ($remoteAddr === '') {
            return false;
        }

        $proxies = config('http.trusted_proxies.proxies', []);
        if (!is_array($proxies) || $proxies === []) {
            return false;
        }

        foreach ($proxies as $proxy) {
            if (!is_string($proxy) || $proxy === '') {
                continue;
            }

            if ($proxy === '*') {
                return true;
            }

            if (str_contains($proxy, '/')) {
                if ($this->ipInCidr($remoteAddr, $proxy)) {
                    return true;
                }
                continue;
            }

            if ($proxy === $remoteAddr) {
                return true;
            }
        }

        return false;
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
