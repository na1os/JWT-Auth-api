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

// This endpoint sends emails -> it's a spam vector, hence the strict limit
rate_limit('forgot_email', $email, FORGOT_MAX, RATE_WINDOW);
rate_limit('forgot_ip', client_ip(), FORGOT_IP_MAX, RATE_WINDOW);

 $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
 $stmt->execute([$email]);
 $user = $stmt->fetch();

// Identical response regardless of whether the email exists (anti user-enumeration)
if ($user) {
    $resetToken = bin2hex(random_bytes(32));

    db()->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
        ->execute([
            hash('sha256', $resetToken),
            date('Y-m-d H:i:s', time() + 3600), // 1 hour
            $user['id'],
        ]);

    try {
        send_reset_email($email, $resetToken);
    } catch (Throwable $e) {
        if (DEBUG) throw $e;
    }
}

json_response([
    'status'  => 'success',
    'message' => 'Dacă există un cont cu acest email, vei primi un link de resetare.',
]);