<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/db.php';

// This endpoint is opened by clicking a link in an email (browser tab),
// not called by JS, so we redirect to the frontend with a status param.

 $token = $_GET['token'] ?? '';
if ($token === '') {
    header('Location: ' . APP_URL . '/index.html?verified=missing');
    exit;
}

 $stmt = db()->prepare(
    'SELECT id FROM users WHERE verify_token = ? AND verify_expires > ?'
);
 $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]);
 $user = $stmt->fetch();

if (!$user) {
    header('Location: ' . APP_URL . '/index.html?verified=invalid');
    exit;
}

db()->prepare('UPDATE users SET is_verified = 1, verify_token = NULL, verify_expires = NULL WHERE id = ?')
    ->execute([$user['id']]);

header('Location: ' . APP_URL . '/index.html?verified=ok');
exit;