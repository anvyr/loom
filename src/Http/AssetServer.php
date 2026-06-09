<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http;

/** `/assets/*` before routing; user views vs module public dirs. */
final class AssetServer
{
    private const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'text/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'map' => 'application/json',
        'xml' => 'application/xml',
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
    ];

    private const ASSET_DIRS = ['css', 'js', 'img', 'images', 'fonts', 'media', 'files'];

    /** @var array<string, string> */
    private array $modulePaths = [];
    private ?string $userPath = null;

    public function serve(Request $request): ?Response
    {
        $path = $request->path();

        if (!str_starts_with($path, '/assets/')) {
            return null;
        }

        $assetPath = substr($path, 8);
        if ($assetPath === '') {
            return null;
        }

        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        if (!isset(self::MIME_TYPES[$extension])) {
            return null;
        }

        $segments = explode('/', $assetPath, 2);
        $first = $segments[0];
        $rest = $segments[1] ?? '';

        if ($rest === '') {
            return null;
        }

        if (in_array($first, self::ASSET_DIRS, true)) {
            return $this->serveUserAsset($assetPath, $extension, $request);
        }

        return $this->serveModuleAsset($first, $rest, $extension, $request);
    }

    public function initialize(?string $userPath = null): void
    {
        $this->userPath = $userPath !== null
            ? rtrim($userPath, '/\\')
            : rtrim(view_path(), '/\\');
    }

    public function registerModule(string $name, string $publicPath): void
    {
        $this->modulePaths[strtolower($name)] = rtrim($publicPath, '/\\');
    }

    public function modulePath(string $name): ?string
    {
        return $this->modulePaths[strtolower($name)] ?? null;
    }

    public function reset(): void
    {
        $this->modulePaths = [];
        $this->userPath = null;
    }

    private function serveUserAsset(string $relativePath, string $extension, Request $request): ?Response
    {
        if ($this->userPath === null) {
            $this->initialize();
        }

        if ($this->userPath === null) {
            return null;
        }

        $filePath = $this->userPath . '/assets/' . self::sanitize($relativePath);
        return self::tryServeFile($filePath, $this->userPath, $extension, $request);
    }

    private function serveModuleAsset(string $module, string $relativePath, string $extension, Request $request): ?Response
    {
        $publicDir = $this->modulePaths[strtolower($module)] ?? null;
        if ($publicDir === null) {
            return null;
        }

        $filePath = $publicDir . '/' . self::sanitize($relativePath);
        return self::tryServeFile($filePath, $publicDir, $extension, $request);
    }

    private static function tryServeFile(string $filePath, string $rootDir, string $extension, Request $request): ?Response
    {
        $realRoot = realpath($rootDir);
        $realFile = realpath($filePath);

        // Security: file must exist and be within root directory
        if (!$realFile || !$realRoot || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
            return null;
        }

        return self::respond($realFile, $extension, $request);
    }

    private static function respond(string $path, string $extension, Request $request): Response
    {
        $mtime = filemtime($path) ?: time();
        $size = filesize($path);
        $etag = sprintf('"%x-%x"', $mtime, $size);
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null) {
            $etags = array_map('trim', explode(',', $ifNoneMatch));
            if (in_array($etag, $etags, true) || in_array('*', $etags, true)) {
                return self::notModified($etag, $lastModified);
            }
        }

        $ifModifiedSince = $request->header('If-Modified-Since');
        if ($ifModifiedSince !== null) {
            $since = strtotime($ifModifiedSince);
            if ($since !== false && $since >= $mtime) {
                return self::notModified($etag, $lastModified);
            }
        }

        return Response::file($path, self::MIME_TYPES[$extension] ?? null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]);
    }

    private static function notModified(string $etag, string $lastModified): Response
    {
        return (new Response('', 304))->headers([
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ]);
    }

    private static function sanitize(string $path): string
    {
        // Remove directory traversal attempts and null bytes
        return str_replace(['../', '..\\', "\0", '//'], ['', '', '', '/'], $path);
    }

}
