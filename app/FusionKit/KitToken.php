<?php

namespace App\FusionKit;

/** Token firmati HS256 con il segreto del Kit (widget Assistenza, "entra come"). Compatibili con il Kit Next.js. */
class KitToken
{
    public static function sign(array $payload, int $ttlSeconds, ?string $secret = null): string
    {
        $secret ??= (string) config('fusionkit.kit_secret');
        if ($secret === '') {
            throw new \RuntimeException('FUSION_KIT_SECRET mancante');
        }
        $now = time();
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload + ['iat' => $now, 'exp' => $now + $ttlSeconds], JSON_UNESCAPED_UNICODE));

        return "$header.$body.".self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
    }

    public static function verify(string $token, ?string $secret = null): ?array
    {
        $secret ??= (string) config('fusionkit.kit_secret');
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $secret === '') {
            return null;
        }
        [$h, $b, $s] = $parts;
        if (! hash_equals(self::b64(hash_hmac('sha256', "$h.$b", $secret, true)), $s)) {
            return null;
        }
        $payload = json_decode(self::unb64($b), true);
        if (! is_array($payload) || ! isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
