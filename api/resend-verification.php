<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/mailer.php';
require_once __DIR__ . '/../helpers/ratelimit.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metodă nepermisă. Folosește POST.', 405);
}

 $input = json_input();
 $email = strtolower(trim($input['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Adresă de email invalidă.', 422);
}

rate_limit('resend_email', $email, RESEND_MAX, RATE_WINDOW);
rate_limit('resend_ip', client_ip(), RESEND_IP_MAX, RATE_WINDOW);

 $stmt = db()->prepare('SELECT id, is_verified FROM users WHERE email = ?');
 $stmt->execute([$email]);
 $user = $stmt->fetch();

// Identical response in every case (anti user-enumeration)
if ($user && !$user['is_verified']) {
    // Regenerate the token — the one in the old email becomes useless
    $verifyToken = bin2hex(random_bytes(32));

    db()->prepare('UPDATE users SET verify_token = ?, verify_expires = ? WHERE id = ?')
        ->execute([
            hash('sha256', $verifyToken),
            date('Y-m-d H:i:s', time() + 86400),
            $user['id'],
        ]);

    try {
        send_verification_email($email, $verifyToken);
    } catch (Throwable $e) {
        if (DEBUG) throw $e;
    }
}

json_response([
    'status'  => 'success',
    'message' => 'Dacă există un cont neactivat cu acest email, vei primi un link de verificare.',
]);