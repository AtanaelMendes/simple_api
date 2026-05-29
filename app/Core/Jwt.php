<?php

namespace App\Core;

/**
 * Lightweight HS256 JWT for client-side session caching.
 * Not used for server-side authentication — PHP sessions handle that.
 */
class Jwt
{
    public const STORAGE_KEY = 'sofDoc_session';

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function secret(): string
    {
        $key = $_ENV['JWT_SECRET'] ?? $_ENV['APP_SECRET'] ?? null;
        if (!empty($key)) {
            return $key;
        }
        // Derive a stable secret from app-specific env constants
        return hash('sha256', 'sofDoc-jwt-' . ($_ENV['DB_HOST'] ?? 'localhost') . ':' . ($_ENV['DB_NAME'] ?? 'sofDoc'));
    }

    public static function generate(array $payload): string
    {
        $header = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = self::b64url(json_encode($payload));
        $sig    = self::b64url(hash_hmac('sha256', "{$header}.{$body}", self::secret(), true));
        return "{$header}.{$body}.{$sig}";
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "{$header}.{$body}", self::secret(), true));

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $data = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['exp']) && $data['exp'] < time()) {
            return null;
        }

        return $data;
    }
}
