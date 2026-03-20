<?php

namespace Herbarium;

class ApiClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(getenv('API_BASE_URL') ?: 'http://api:80', '/');
    }

    public function get(string $endpoint, ?string $jwt = null): array
    {
        return $this->request('GET', $endpoint, null, $jwt);
    }

    public function post(string $endpoint, ?array $data = null, ?string $jwt = null): array
    {
        return $this->request('POST', $endpoint, $data, $jwt);
    }

    public function put(string $endpoint, ?array $data = null, ?string $jwt = null): array
    {
        return $this->request('PUT', $endpoint, $data, $jwt);
    }

    public function delete(string $endpoint, ?string $jwt = null): array
    {
        return $this->request('DELETE', $endpoint, null, $jwt);
    }

    public function uploadFile(string $endpoint, string $fieldName, array $fileInfo, ?string $jwt = null): array
    {
        $boundary = '----HerbariumBoundary' . bin2hex(random_bytes(8));

        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$fieldName}\"; filename=\"{$fileInfo['name']}\"\r\n";
        $body .= "Content-Type: {$fileInfo['type']}\r\n\r\n";
        $body .= file_get_contents($fileInfo['tmp_name']) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $headers = "Content-Type: multipart/form-data; boundary={$boundary}\r\n";
        if ($jwt) {
            $headers .= "Authorization: Bearer {$jwt}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $url = $this->baseUrl . $endpoint;
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['error' => 'Failed to connect to API', '_status' => 502];
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        $decoded = json_decode($response, true) ?? ['raw' => $response];
        $decoded['_status'] = $statusCode;

        return $decoded;
    }

    public function postRaw(string $endpoint, string $body, string $contentType, ?string $jwt = null): array
    {
        $headers = "Content-Type: {$contentType}\r\n";
        if ($jwt) {
            $headers .= "Authorization: Bearer {$jwt}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => $headers,
                'content'       => $body,
                'timeout'       => 30,
                'ignore_errors' => true,
            ],
        ]);

        $url = $this->baseUrl . $this->normalize_path($endpoint);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['error' => 'Failed to connect to API', '_status' => 502];
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        $decoded    = json_decode($response, true) ?? ['raw' => $response];
        $decoded['_status'] = $statusCode;

        return $decoded;
    }

    public function fetchRaw(string $endpoint, ?string $jwt = null): ?string
    {
        $headers = "Accept: */*\r\n";
        if ($jwt) {
            $headers .= "Authorization: Bearer {$jwt}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $url = $this->baseUrl . $this->normalize_path($endpoint);
        return @file_get_contents($url, false, $context) ?: null;
    }

    private function request(string $method, string $endpoint, ?array $data, ?string $jwt): array
    {
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";

        if ($jwt) {
            $headers .= "Authorization: Bearer {$jwt}\r\n";
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => $headers,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $url = $this->baseUrl . $this->normalize_path($endpoint);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['error' => 'Failed to connect to API', '_status' => 502];
        }

        $statusCode = $this->extractStatusCode($http_response_header ?? []);
        $decoded = json_decode($response, true) ?? ['raw' => $response];
        $decoded['_status'] = $statusCode;

        return $decoded;
    }

    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                return (int)$matches[1];
            }
        }
        return 200;
    }

    function normalize_path($p) {
        return '/' . implode('/', array_reduce(explode('/', $p), function($a,$b){
            return $b==''||$b=='.' ? $a : ($b=='..' ? array_slice($a,0,-1) : [...$a,$b]);
        }, []));
    }
}
