<?php
date_default_timezone_set('Europe/Bucharest');

// ---- data base ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'auth_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- URL-uri ----
define('BASE_URL', 'https://apps.m1hai.xyz/login/');  // API
define('APP_URL',  'https://m1hai.xyz');  // frontend

// ---- JWT / Access token ----
// Generate a secret:  php -r "echo bin2hex(random_bytes(32));"
define('JWT_SECRET', '');
define('JWT_ISSUER', 'apps.m1hai.xyz');
define('ACCESS_TOKEN_TTL',  15 * 60);            // 15 minute
define('REFRESH_TOKEN_TTL', 30 * 24 * 60 * 60);   // 30 zile

// ---- origins ----
define('CORS_ALLOWED_ORIGINS', [
    'https://m1hai.xyz',
    // 'https://another-project.ro',
]);

// ---- SMTP (PHPMailer) ----
define('SMTP_HOST', '');
define('SMTP_PORT', 465);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('MAIL_FROM', '');
define('MAIL_FROM_NAME', 'test');

// ---- Rate limiting ----
define('RATE_WINDOW', 15 * 60);
define('LOGIN_MAX',        5);   // per email
define('LOGIN_IP_MAX',    20);   // per IP
define('FORGOT_MAX',      3);   // per email
define('FORGOT_IP_MAX',  10);
define('REGISTER_IP_MAX', 5);   // per IP
define('RESEND_MAX',      3);   // per email
define('RESEND_IP_MAX',   6);

// ---- accounts ----
define('MIN_AGE', 13);         // 13 or 18 recommanded
define('PASSWORD_MIN', 8);
define('PASSWORD_MAX', 72);    // bcrypt internal limit - do not increase

// ---- Debug ----
define('DEBUG', true);
ini_set('display_errors', '1');
error_reporting(DEBUG ? E_ALL : 1);

// ---- Refresh token cookie ----
// '/' works no matter what subfolder the API lives in
define('COOKIE_PATH', '/');

set_exception_handler(function (Throwable $e) {
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = (defined('CORS_ALLOWED_ORIGINS') && is_array(CORS_ALLOWED_ORIGINS))
        ? CORS_ALLOWED_ORIGINS : [];
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => DEBUG
            ? $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']'
            : 'Internal Server Error.',
    ]);
    exit;
});