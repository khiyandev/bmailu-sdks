<?php

declare(strict_types=1);

namespace Bmailu;

/**
 * Client for the Bmailu API. No dependencies: curl when it is loaded, PHP streams otherwise.
 *
 *     $bmailu = new \Bmailu\Bmailu('bmk_...');
 *     $bmailu->emails->send(['from' => 'Shop <hello@shop.com>', 'to' => 'ann@example.com', 'subject' => 'Hi', 'html' => '<p>Hi</p>']);
 */
final class Bmailu
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://app.bmailu.com/api/v1';

    public readonly string $baseUrl;
    private string $apiKey;

    public readonly Resources\Me $me;
    public readonly Resources\Domains $domains;
    public readonly Resources\Mailboxes $mailboxes;
    public readonly Resources\Aliases $aliases;
    public readonly Resources\ApiKeys $apiKeys;
    public readonly Resources\SendingDomains $sendingDomains;
    public readonly Resources\Emails $emails;
    public readonly Resources\Webhooks $webhooks;
    public readonly Resources\Suppressions $suppressions;
    public readonly Resources\Metrics $metrics;
    public readonly Resources\Logs $logs;
    public readonly Resources\Inbox $inbox;
    public readonly Resources\Templates $templates;
    public readonly Resources\Contacts $contacts;
    public readonly Resources\Segments $segments;
    public readonly Resources\Topics $topics;
    public readonly Resources\Broadcasts $broadcasts;
    public readonly Resources\Events $events;
    public readonly Resources\Automations $automations;

    /**
     * @param array{timeout?: int, transport?: string, handler?: callable} $options
     *   transport: "curl" or "stream" (default: curl when loaded).
     *   handler: fn(string $method, string $url, ?string $body, array $headers): array{int, string, string}
     *   (status, content type, body) replaces the network, for tests.
     */
    public function __construct(?string $apiKey = null, ?string $baseUrl = null, private array $options = [])
    {
        $key = $apiKey ?? (getenv('BMAILU_API_KEY') ?: null);
        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('Missing API key: pass it to new Bmailu() or set BMAILU_API_KEY');
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($baseUrl ?? (getenv('BMAILU_BASE_URL') ?: self::DEFAULT_BASE_URL), '/');
        $this->me = new Resources\Me($this);
        $this->domains = new Resources\Domains($this);
        $this->mailboxes = new Resources\Mailboxes($this);
        $this->aliases = new Resources\Aliases($this);
        $this->apiKeys = new Resources\ApiKeys($this);
        $this->sendingDomains = new Resources\SendingDomains($this);
        $this->emails = new Resources\Emails($this);
        $this->webhooks = new Resources\Webhooks($this);
        $this->suppressions = new Resources\Suppressions($this);
        $this->metrics = new Resources\Metrics($this);
        $this->logs = new Resources\Logs($this);
        $this->inbox = new Resources\Inbox($this);
        $this->templates = new Resources\Templates($this);
        $this->contacts = new Resources\Contacts($this);
        $this->segments = new Resources\Segments($this);
        $this->topics = new Resources\Topics($this);
        $this->broadcasts = new Resources\Broadcasts($this);
        $this->events = new Resources\Events($this);
        $this->automations = new Resources\Automations($this);
    }

    /**
     * Call any endpoint. Returns decoded JSON (associative arrays), a string for text/CSV/HTML and for files.
     *
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @throws BmailuError
     */
    public function request(string $method, string $path, mixed $body = null, array $query = [], array $headers = []): mixed
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path) . $this->query($query);
        $data = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $h = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'bmailu-php/' . self::VERSION,
        ];
        $h = array_merge($h, $headers);
        $timeout = (int) ($this->options['timeout'] ?? 30);
        $useCurl = ($this->options['transport'] ?? (function_exists('curl_init') ? 'curl' : 'stream')) === 'curl';
        [$status, $contentType, $raw] = isset($this->options['handler'])
            ? ($this->options['handler'])(strtoupper($method), $url, $data, $h)
            : ($useCurl ? $this->curl(strtoupper($method), $url, $data, $h, $timeout)
            : $this->stream(strtoupper($method), $url, $data, $h, $timeout));
        $parsed = $this->parse($raw, $contentType);
        if ($status >= 200 && $status < 300) {
            return $parsed;
        }
        $err = is_array($parsed) && isset($parsed['error']) && is_array($parsed['error']) ? $parsed['error'] : null;
        if ($err !== null) {
            throw new BmailuError($status, (string) ($err['code'] ?? 'http_error'), (string) ($err['message'] ?? ''), $parsed);
        }
        throw new BmailuError($status, 'http_error', substr(is_string($parsed) ? $parsed : $raw, 0, 500) ?: "HTTP $status", $parsed);
    }

    /** @param array<string, mixed> $query */
    private function query(array $query): string
    {
        $q = [];
        foreach ($query as $k => $v) {
            if ($v === null) {
                continue;
            }
            $q[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        return $q === [] ? '' : '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    private function parse(string $raw, string $contentType): mixed
    {
        if ($raw === '') {
            return null;
        }
        if (str_contains(strtolower($contentType), 'json')) {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }
        return $raw;
    }

    /** @return array{int, string, string} */
    private function curl(string $method, string $url, ?string $data, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        $contentType = '';
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$contentType): int {
                if (stripos($line, 'content-type:') === 0) {
                    $contentType = trim(substr($line, 13));
                }
                return strlen($line);
            },
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $msg = curl_error($ch);
            throw new BmailuError(0, 'connection_error', $msg);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$status, $contentType, (string) $raw];
    }

    /** @return array{int, string, string} */
    private function stream(string $method, string $url, ?string $data, array $headers, int $timeout): array
    {
        $lines = ['Connection: close'];
        foreach ($headers as $k => $v) {
            $lines[] = "$k: $v";
        }
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $lines),
            'content' => $data ?? '',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'protocol_version' => 1.1,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        if ($raw === false || $responseHeaders === []) {
            $e = error_get_last();
            throw new BmailuError(0, 'connection_error', $e['message'] ?? 'Request failed');
        }
        $status = 0;
        $contentType = '';
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
                $contentType = '';
            } elseif (stripos($line, 'content-type:') === 0) {
                $contentType = trim(substr($line, 13));
            }
        }
        return [$status, $contentType, (string) $raw];
    }
}
