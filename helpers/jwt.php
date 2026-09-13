<?php
require_once __DIR__ . '/../config.php';

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Signs a JWT HS256. Ex: jwt_encode(['sub' => 7, 'email' => '...']) */
function jwt_encode(array $payload): string
{
    $payload['iat'] = time();
    $payload['exp'] = time() + ACCESS_TOKEN_TTL;
    $payload['iss'] = JWT_ISSUER;

    $header    = b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body      = b64url_encode(json_encode($payload));
    $signature = b64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));

    return "$header.$body.$signature";
}

/** Verifies signature and expiry. Returns the payload or null. */
function jwt_decode(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $body, $signature] = $parts;

    $expected = b64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $hdr = json_decode(b64url_decode($header), true);
    if (($hdr['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $payload = json_decode(b64url_decode($body), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
        return null;
    }

    return $payload;
}