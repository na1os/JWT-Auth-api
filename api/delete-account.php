<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/ratelimit.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $user = require_auth();

 $input    = json_input();
 $password = $input['password'] ?? '';

if ($password === '') {
    json_error('Parola este obligatorie pentru ștergerea contului.', 422);
}

// Same limits as login — this endpoint verifies a password, so it's just as brute-force-able
rate_limit('delete_email', $user['email'], LOGIN_MAX, RATE_WINDOW);
rate_limit('delete_ip', client_ip(), LOGIN_IP_MAX, RATE_WINDOW);

// Re-verify the password — don't rely on the access token alone
// (if the token is stolen, the attacker still can't delete the account without the password)
 $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
 $stmt->execute([$user['id']]);
 $hash = $stmt->fetchColumn();

if (!$hash || !password_verify($password, $hash)) {
    json_error('Parolă incorectă.', 401);
}

// Delete the account — refresh_tokens goes away automatically (ON DELETE CASCADE in schema)
db()->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);

// Clear the refresh cookie (same attributes as when setting it)
setcookie('refresh_token', '', [
    'expires'  => time() - 3600,
    'path'     => COOKIE_PATH,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

json_response(['status' => 'success', 'message' => 'Contul a fost șters.']);