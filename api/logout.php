<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

// Delete the token from the DB (if it exists)
 $token = $_COOKIE['refresh_token'] ?? '';
if ($token !== '') {
    db()->prepare('DELETE FROM refresh_tokens WHERE token_hash = ?')
        ->execute([hash('sha256', $token)]);
}

// Clear the cookie (same attributes as when setting it)
setcookie('refresh_token', '', [
    'expires'  => time() - 3600,
    'path'     => COOKIE_PATH,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

json_response(['status' => 'success', 'message' => 'Te-ai deconectat.']);