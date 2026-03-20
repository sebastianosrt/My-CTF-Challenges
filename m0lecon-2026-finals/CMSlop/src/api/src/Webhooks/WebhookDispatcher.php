<?php

namespace Herbarium\Webhooks;

class WebhookDispatcher
{
    public static function dispatch(string $event, array $payload): int
    {
        $webhooks = WebhookManager::getForEvent($event);
        $dispatched = 0;

        foreach ($webhooks as $webhook) {
            $jsonPayload = json_encode([
                'event'     => $event,
                'timestamp' => date('c'),
                'data'      => $payload,
            ]);

            if (self::isPrivateUrl($webhook['url'])) {
                WebhookManager::logDelivery(
                    (int) $webhook['id'],
                    $event,
                    $jsonPayload,
                    null,
                    'Blocked: URL resolves to private/reserved address',
                    false
                );
                continue;
            }

            $signature = hash_hmac('sha256', $jsonPayload, $webhook['secret']);

            $ch = curl_init($webhook['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Webhook-Signature: sha256=' . $signature,
                    'X-Webhook-Event: ' . $event,
                    'User-Agent: Herbarium-Webhook/1.0',
                ],
            ]);

            $responseBody   = curl_exec($ch);
            $responseStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError      = curl_error($ch);
            curl_close($ch);

            $success = $responseStatus >= 200 && $responseStatus < 300;

            if ($curlError) {
                $responseBody = $curlError;
                $success = false;
            }

            WebhookManager::logDelivery(
                (int) $webhook['id'],
                $event,
                $jsonPayload,
                $responseStatus > 0 ? $responseStatus : null,
                $responseBody !== false ? (string) $responseBody : null,
                $success
            );

            $dispatched++;
        }

        return $dispatched;
    }

    private static function isPrivateUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return true;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return true;
        }

        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        $literalIp = trim($host, '[]');
        if (filter_var($literalIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return !filter_var($literalIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
