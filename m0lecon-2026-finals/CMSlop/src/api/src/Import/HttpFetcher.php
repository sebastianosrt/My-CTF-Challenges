<?php

namespace Herbarium\Import;

class HttpFetcher
{
    private int $timeout;
    private string $userAgent;

    public function __construct(array $options = [])
    {
        $this->timeout   = $options['timeout'] ?? 10;
        $this->userAgent = $options['user_agent'] ?? 'Herbarium/1.0';
    }

    public function get(string $url, array $headers = []): string
    {
        $result = $this->request('GET', $url, null, $headers);
        if ($result['error'] !== null) {
            throw new \RuntimeException("Fetch failed: {$result['error']}");
        }
        if ($result['status'] >= 400) {
            throw new \RuntimeException("Remote returned HTTP {$result['status']}");
        }
        return $result['body'];
    }

    public function postJson(string $url, array $data, array $headers = []): array
    {
        $headers['Content-Type'] = 'application/json';
        $result = $this->request('POST', $url, json_encode($data), $headers);
        if ($result['error'] !== null) {
            throw new \RuntimeException("POST failed: {$result['error']}");
        }
        return json_decode($result['body'], true) ?? [];
    }

    public function fetch(string $method, string $url, array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $body    = $options['body'] ?? null;
        return $this->request($method, $url, $body, $headers);
    }

    public function head(string $url, array $headers = []): array
    {
        return $this->request('HEAD', $url, null, $headers);
    }
    
    public function isReachable(string $url): bool
    {
        $result = $this->head($url);
        return $result['status'] >= 200 && $result['status'] < 400;
    }

    public function postRaw(string $url, string $body, string $contentType, array $headers = []): string
    {
        $headers['Content-Type'] = $contentType;
        $result = $this->request('POST', $url, $body, $headers);
        if ($result['error'] !== null) {
            throw new \RuntimeException("POST failed: {$result['error']}");
        }
        return $result['body'];
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    private function guardSsrf(string $url): string
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException("Blocked: only http/https schemes are allowed");
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new \RuntimeException("Blocked: no host in URL");
        }

        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException("Blocked: could not resolve host");
        }

        $aaaaRecords = dns_get_record($host, DNS_AAAA);
        if (!empty($aaaaRecords)) {
            foreach ($aaaaRecords as $rec) {
                $ipv6 = $rec['ipv6'] ?? '';
                $packed = @inet_pton($ipv6);
                if ($packed !== false) {
                    if (!filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        throw new \RuntimeException("Blocked: private/reserved IPv6 address");
                    }
                }
            }
        }

        $literalIp = trim($host, '[]');
        if (filter_var($literalIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (!filter_var($literalIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException("Blocked: private/reserved IP address");
            }
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \RuntimeException("Blocked: private/reserved IP address");
        }

        return $ip;
    }

    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        $resolvedIp = $this->guardSsrf($url);
        $headers['User-Agent'] = $this->userAgent;

        $parsed = parse_url($url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);
        $pinnedUrl = str_replace("://{$host}", "://{$resolvedIp}", $url);
        $headers['Host'] = $host;

        $headerStr = '';
        foreach ($headers as $key => $value) {
            $headerStr .= "{$key}: {$value}\r\n";
        }

        $opts = [
            'http' => [
                'method'          => $method,
                'header'          => $headerStr,
                'timeout'         => $this->timeout,
                'ignore_errors'   => true,
                'follow_location' => 0,
                'max_redirects'   => 0,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = $body;
        }

        $context  = stream_context_create($opts);
        $response = @file_get_contents($pinnedUrl, false, $context);

        if ($response === false) {
            return [
                'status'  => 0,
                'headers' => [],
                'body'    => '',
                'error'   => 'Connection failed',
            ];
        }

        $status      = 200;
        $respHeaders = [];
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
                $status = (int) $m[1];
            } elseif (strpos($h, ':') !== false) {
                list($key, $val) = explode(':', $h, 2);
                $respHeaders[trim($key)][] = trim($val);
            }
        }

        return [
            'status'  => $status,
            'headers' => $respHeaders,
            'body'    => $response,
            'error'   => null,
        ];
    }
}
