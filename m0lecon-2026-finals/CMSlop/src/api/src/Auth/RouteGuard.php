<?php

namespace Herbarium\Auth;

use Herbarium\Core\MiddlewareStack;
use Herbarium\Core\AuditLogger;
use Herbarium\Core\Database;

class RouteGuard
{
    public static function wrap(callable $handler, array $middleware): callable
    {
        if (empty($middleware)) {
            return $handler;
        }

        $stack = new MiddlewareStack($middleware, $handler);
        return $stack->resolve();
    }

    public static function auth(): callable
    {
        return function ($next) {
            return function (...$args) use ($next) {
                JwtAuth::requireAuth();
                return $next(...$args);
            };
        };
    }

    public static function admin(): callable
    {
        return function ($next) {
            return function (...$args) use ($next) {
                JwtAuth::requireAdmin();
                return $next(...$args);
            };
        };
    }

    public static function audit(string $action): callable
    {
        return function ($next) use ($action) {
            return function (...$args) use ($next, $action) {
                $claims = JwtAuth::extractFromHeader();
                $userId = $claims ? (int) $claims->sub : null;
                AuditLogger::getInstance()->record($action, $userId, 'guard');
                return $next(...$args);
            };
        };
    }

    public static function jsonBody(): callable
    {
        return function ($next) {
            return function (...$args) use ($next) {
                $raw = file_get_contents('php://input');
                $decoded = json_decode($raw, true);
                $GLOBALS['_body'] = is_array($decoded) ? $decoded : [];
                return $next(...$args);
            };
        };
    }

    public static function rateLimit(int $maxRequests, int $windowSeconds): callable
    {
        return function ($next) use ($maxRequests, $windowSeconds) {
            return function (...$args) use ($next, $maxRequests, $windowSeconds) {
                $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $since = date('Y-m-d H:i:s', time() - $windowSeconds);

                $rows  = Database::prepared(
                    "SELECT COUNT(*) as cnt FROM audit_log WHERE ip_address = ? AND created_at >= ?",
                    [$ip, $since]
                );

                if ((int) ($rows[0]['cnt'] ?? 0) >= $maxRequests) {
                    json_response(['error' => 'Rate limit exceeded'], 429);
                }

                return $next(...$args);
            };
        };
    }

    public static function requireContentType(string $mime): callable
    {
        return function ($next) use ($mime) {
            return function (...$args) use ($next, $mime) {
                $ct = $_SERVER['CONTENT_TYPE'] ?? '';
                if (strpos($ct, $mime) === false && $_SERVER['REQUEST_METHOD'] === 'POST') {
                    json_response(['error' => "Content-Type must be {$mime}"], 415);
                }
                return $next(...$args);
            };
        };
    }
}
