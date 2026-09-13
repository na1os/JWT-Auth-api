<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/response.php';

/** Authentication via the Authorization: Bearer <token> header. */
function require_auth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        json_error('Lipsește header-ul Authorization: Bearer.', 401);
    }

    $payload = jwt_decode($m[1]);
    if ($payload === null || empty($payload['sub'])) {
        json_error('Access token invalid sau expirat.', 401);
    }

    $stmt = db()->prepare(
        'SELECT id, email, first_name, last_name, is_verified, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$payload['sub']]);
    $user = $stmt->fetch();

    if (!$user) {
        json_error('Utilizatorul nu mai există.', 401);
    }

    return $user;
}

/** Generates a new refresh token, stores it (hashed) in the DB and sets it as a cookie. */
function issue_refresh_token(int $userId): void
{
    $token = bin2hex(random_bytes(32)); // the raw value exists ONLY in the cookie

    db()->prepare(
        'INSERT INTO refresh_tokens (user_id, token_hash, expires_at, ip, user_agent)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        hash('sha256', $token),
        date('Y-m-d H:i:s', time() + REFRESH_TOKEN_TTL),
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    setcookie('refresh_token', $token, [
        'expires'  => time() + REFRESH_TOKEN_TTL,
        'path'     => COOKIE_PATH,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}