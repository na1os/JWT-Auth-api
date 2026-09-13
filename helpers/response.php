<?php
require_once __DIR__ . '/../config.php';

/** Sends JSON and stops execution. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    // Basic security headers
    header('X-Content-Type-Options: nosniff');  // browser won't guess the MIME type
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');      // URL tokens never leak via Referer

    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['status' => 'error', 'message' => $message], $status);
}

/** Reads the JSON body of the request. */
function json_input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}