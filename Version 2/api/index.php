<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/public.php';
require_once __DIR__ . '/routes/customer.php';
require_once __DIR__ . '/routes/admin.php';

api_apply_cors_headers();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = api_get_path();

$handled = false;
$handled = $handled || api_route_public($pdo, $method, $path);
$handled = $handled || api_route_auth($pdo, $method, $path);
$handled = $handled || api_route_customer($pdo, $method, $path);
$handled = $handled || api_route_admin($pdo, $method, $path);

if ($handled) {
    exit();
}

if (strpos($path, '/v1/') === 0) {
    api_error('Endpoint not found.', 404);
}

api_error('Invalid API path.', 404);
