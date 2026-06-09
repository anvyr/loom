<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Client;

final class HttpClient
{
    private string $baseUrl;

    /** @var array<string, string> */
    private array $defaultHeaders;

    private int $connectTimeout;
    private int $timeout;

    /** @param array<string, string> $headers */
    public function __construct(
        string $baseUrl = '',
        array $headers = [],
        int $connectTimeout = 5,
        int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = $headers;
        $this->connectTimeout = $connectTimeout;
        $this->timeout = $timeout;
    }

    /** @param array<string, mixed> $options */
    public function get(string $url, array $options = []): HttpResponse
    {
        return $this->send('GET', $url, $options);
    }

    /** @param array<string, mixed> $options */
    public function post(string $url, array $options = []): HttpResponse
    {
        return $this->send('POST', $url, $options);
    }

    /** @param array<string, mixed> $options */
    public function put(string $url, array $options = []): HttpResponse
    {
        return $this->send('PUT', $url, $options);
    }

    /** @param array<string, mixed> $options */
    public function patch(string $url, array $options = []): HttpResponse
    {
        return $this->send('PATCH', $url, $options);
    }

    /** @param array<string, mixed> $options */
    public function delete(string $url, array $options = []): HttpResponse
    {
        return $this->send('DELETE', $url, $options);
    }

    /** @param array<string, mixed> $options */
    public function retry(string $method, string $url, int $times, int $delayMs = 100, array $options = []): HttpResponse
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $times) {
            $attempts++;

            try {
                $response = $this->send($method, $url, $options);

                if (!$response->serverError()) {
                    return $response;
                }

                if ($attempts >= $times) {
                    return $response;
                }
            } catch (HttpClientException $e) {
                $lastException = $e;

                if ($attempts >= $times) {
                    throw $e;
                }
            }

            usleep($delayMs * 1000 * (2 ** ($attempts - 1)));
        }

        // Unreachable in practice, but satisfies static analysis
        throw $lastException ?? new HttpClientException('Retry exhausted with no response');
    }

    /** @param array<string, mixed> $options */
    public function send(string $method, string $url, array $options = []): HttpResponse
    {
        $resolvedUrl = $this->resolveUrl($url, $options['query'] ?? []);
        if ($resolvedUrl === '') {
            throw new HttpClientException('Request URL cannot be empty.');
        }
        /** @var non-empty-string $resolvedUrl */

        $method = strtoupper($method);
        if ($method === '') {
            throw new HttpClientException('HTTP method cannot be empty.');
        }

        $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $resolvedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) ($options['connectTimeout'] ?? $this->connectTimeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($options['timeout'] ?? $this->timeout));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $this->applyBody($ch, $options, $headers);
        $this->applyAuth($ch, $options, $headers);

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            throw new HttpClientException("cURL error ({$errno}): {$error}", $errno);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        /** @var string $raw */
        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return new HttpResponse($statusCode, $body, $this->parseHeaders($rawHeaders));
    }

    /** @param array<string, mixed> $query */
    private function resolveUrl(string $url, array $query): string
    {
        // Absolute URL — use as-is
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $resolved = $url;
        } else {
            $resolved = $this->baseUrl . '/' . ltrim($url, '/');
        }

        if ($query !== []) {
            $separator = str_contains($resolved, '?') ? '&' : '?';
            $resolved .= $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $headers Passed by reference — may add Content-Type.
     */
    private function applyBody(\CurlHandle $ch, array $options, array &$headers): void
    {
        if (isset($options['json'])) {
            $body = json_encode($options['json'], JSON_THROW_ON_ERROR);
            $headers['Content-Type'] ??= 'application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            return;
        }

        if (isset($options['form'])) {
            $body = http_build_query($options['form'], '', '&');
            $headers['Content-Type'] ??= 'application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            return;
        }

        if (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        }
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $headers
     */
    private function applyAuth(\CurlHandle $ch, array $options, array &$headers): void
    {
        if (isset($options['bearer'])) {
            $headers['Authorization'] = 'Bearer ' . $options['bearer'];
            return;
        }

        if (isset($options['auth']) && is_array($options['auth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $options['auth'][0] . ':' . $options['auth'][1]);
        }
    }

    /** @return array<string, list<string>> */
    private function parseHeaders(string $raw): array
    {
        $headers = [];

        // cURL may include multiple header blocks when following redirects
        $blocks = preg_split('/\r?\n\r?\n/', trim($raw)) ?: [];
        $lastBlock = end($blocks);

        if ($lastBlock === false) {
            return $headers;
        }

        foreach (explode("\n", $lastBlock) as $line) {
            $line = trim($line);

            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Skip the HTTP status line if it somehow leaks through
            if (str_starts_with($name, 'HTTP/')) {
                continue;
            }

            $headers[$name][] = $value;
        }

        return $headers;
    }
}
