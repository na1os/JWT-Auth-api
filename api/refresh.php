<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/auth.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $token = $_COOKIE['refresh_token'] ?? '';
if ($token === '') {
    json_error('Refresh token lipsă (cookie).', 401);
}

// Housekeeping: delete expired tokens
db()->prepare('DELETE FROM refresh_tokens WHERE expires_at < ?')
    ->execute([date('Y-m-d H:i:s')]);

 $stmt = db()->prepare(
    'SELECT rt.id AS rt_id, rt.user_id, rt.expires_at, u.email, u.is_verified
     FROM refresh_tokens rt
     JOIN users u ON u.id = rt.user_id
     WHERE rt.token_hash = ?'
);
 $stmt->execute([hash('sha256', $token)]);
 $row = $stmt->fetch();

if (!$row) {
    json_error('Refresh token invalid.', 401);
}
if (strtotime($row['expires_at']) < time()) {
    db()->prepare('DELETE FROM refresh_tokens WHERE id = ?')->execute([$row['rt_id']]);
    json_error('Refresh token expirat. Autentifică-te din nou.', 401);
}
if (!$row['is_verified']) {
    json_error('Cont neverificat.', 403);
}

// ROTATION: delete the old token and issue a new one
db()->prepare('DELETE FROM refresh_tokens WHERE id = ?')->execute([$row['rt_id']]);
issue_refresh_token((int) $row['user_id']);

 $accessToken = jwt_encode(['sub' => (int) $row['user_id'], 'email' => $row['email']]);

json_response([
    'status'       => 'success',
    'access_token' => $accessToken,
    'token_type'   => 'Bearer',
    'expires_in'   => ACCESS_TOKEN_TTL,
]);