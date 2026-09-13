<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/ratelimit.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $input    = json_input();
 $email    = strtolower(trim($input['email'] ?? ''));
 $password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    json_error('Email și parolă obligatorii.', 422);
}

// Rate limiting: per email (targeted attacker) and per IP (distributed brute-force)
rate_limit('login_email', $email, LOGIN_MAX, RATE_WINDOW);
rate_limit('login_ip', client_ip(), LOGIN_IP_MAX, RATE_WINDOW);

 $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
 $stmt->execute([$email]);
 $user = $stmt->fetch();

// Dummy hash — the password gets verified even when the user doesn't exist (anti timing attack)
 $dummyHash = '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZabcde';
 $hash = $user['password_hash'] ?? $dummyHash;

// Generic message, so we don't leak whether the email exists
if (!$user || !password_verify($password, $hash)) {
    json_error('Email sau parolă incorectă.', 401);
}
if (!$user['is_verified']) {
    json_error('Contul nu este activat. Verifică-ți email-ul.', 403);
}

// Successful login: clear the per-email counter (keep the per-IP one, otherwise
// an attack with correct passwords on different accounts could reset its own limit)
rate_limit_clear('login_email', $email);

// Access token (15 min) + refresh token (HttpOnly cookie, 30 days)
 $accessToken = jwt_encode(['sub' => (int) $user['id'], 'email' => $user['email']]);
issue_refresh_token((int) $user['id']);

json_response([
    'status'       => 'success',
    'access_token' => $accessToken,
    'token_type'   => 'Bearer',
    'expires_in'   => ACCESS_TOKEN_TTL,
]);