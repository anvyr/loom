<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services;

class SessionManager
{
    private bool $started = false;

    public function __construct()
    {
        $this->started = session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $value = $_SESSION;

            foreach ($segments as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }

            return $value;
        }

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $current = &$_SESSION;

            foreach ($segments as $i => $segment) {
                if ($i === count($segments) - 1) {
                    $current[$segment] = $value;
                } else {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }
        } else {
            $_SESSION[$key] = $value;
        }
    }

    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }

    public function delete(string $key): void
    {
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $current = &$_SESSION;

            foreach ($segments as $i => $segment) {
                if ($i === count($segments) - 1) {
                    unset($current[$segment]);
                } else {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        return;
                    }
                    $current = &$current[$segment];
                }
            }
        } else {
            unset($_SESSION[$key]);
        }
    }

    public function flash(string $key, mixed $value): void
    {
        $this->set("_flash.new.{$key}", $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get("_flash.old.{$key}", $default);
    }

    public function ageFlashData(): void
    {
        $this->delete('_flash.old');

        if ($this->has('_flash.new')) {
            $this->set('_flash.old', $this->get('_flash.new', []));
            $this->delete('_flash.new');
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $_SESSION;
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function regenerate(bool $deleteOld = true): bool
    {
        if (!$this->started) {
            return false;
        }

        return session_regenerate_id($deleteOld);
    }

    public function token(): string
    {
        if (!$this->has('_token')) {
            $this->set('_token', bin2hex(random_bytes(32)));
        }

        return $this->get('_token');
    }

    public function regenerateToken(): void
    {
        $this->set('_token', bin2hex(random_bytes(32)));
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
}
