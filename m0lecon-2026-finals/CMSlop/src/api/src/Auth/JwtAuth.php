<?php

namespace Herbarium\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth
{
    private static string $algorithm = 'HS256';

    public static function getSecret(): string
    {
        return getenv('JWT_SECRET') ?: 'fallback_secret_change_me';
    }

    public static function encode(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + 3600;
        $payload['iss'] = 'herbarium-api';

        return JWT::encode($payload, self::getSecret(), self::$algorithm);
    }

    public static function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(self::getSecret(), self::$algorithm));
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function extractFromHeader(): ?object
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return self::decode($matches[1]);
        }

        return null;
    }

    public static function requireAuth(): object
    {
        $claims = self::extractFromHeader();

        if (!$claims) {
            json_response([
                'error' => 'Authentication required',
                'code'  => 'UNAUTHORIZED',
            ], 401);
        }

        return $claims;
    }

    public static function requireAdmin(): object
    {
        $claims = self::requireAuth();

        if (($claims->role ?? '') !== 'admin') {
            json_response([
                'error' => 'Administrator access required',
                'code'  => 'FORBIDDEN',
            ], 403);
        }

        return $claims;
    }

    public static function refresh(string $token): ?string
    {
        $claims = self::decode($token);
        if ($claims === null) {
            return null;
        }
        return self::encode([
            'sub'      => $claims->sub,
            'username' => $claims->username ?? '',
            'role'     => $claims->role ?? 'user',
        ]);
    }

    public static function getClaims(string $token): ?array
    {
        $claims = self::decode($token);
        if ($claims === null) {
            return null;
        }
        return (array) $claims;
    }

    public static function isExpired(string $token): bool
    {
        return self::decode($token) === null;
    }
}
