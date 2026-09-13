<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/cors.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

handle_cors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Metodă nepermisă. Folosește GET.', 405);
}

 $user = require_auth();

json_response([
    'status' => 'success',
    'user'   => [
        'id'         => (int) $user['id'],
        'email'      => $user['email'],
        'first_name' => $user['first_name'],
        'last_name'  => $user['last_name'],
        'created_at' => $user['created_at'],
    ],
]);