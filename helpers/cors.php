<?php
require_once __DIR__ . '/../config.php';

function handle_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!in_array($origin, CORS_ALLOWED_ORIGINS, true)) {
        return; // unknown origin -> we send no CORS headers
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');

    // Preflight response
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}