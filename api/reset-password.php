<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $input    = json_input();
 $token    = trim($input['token'] ?? '');
 $password = $input['password'] ?? '';

if ($token === '' || strlen($password) < PASSWORD_MIN || strlen($password) > PASSWORD_MAX) {
    json_error('Date invalide (parolă între ' . PASSWORD_MIN . ' și ' . PASSWORD_MAX . ' caractere).', 422);
}

 $stmt = db()->prepare(
    'SELECT id FROM users WHERE reset_token = ? AND reset_expires > ?'
);
 $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
 $user = $stmt->fetch();

if (!$user) {
    json_error('Token invalid sau expirat.', 400);
}

// New password + clear the token
db()->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

// Security: log out all active sessions
db()->prepare('DELETE FROM refresh_tokens WHERE user_id = ?')->execute([$user['id']]);

json_response(['status' => 'success', 'message' => 'Parolă resetată. Autentifică-te din nou.']);