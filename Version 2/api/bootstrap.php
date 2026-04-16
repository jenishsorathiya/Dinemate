<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function api_allowed_origins(): array
{
    $fromEnv = getenv('DM_ALLOWED_ORIGINS');
    if ($fromEnv !== false && trim($fromEnv) !== '') {
        $origins = array_map('trim', explode(',', $fromEnv));
        return array_values(array_filter($origins, static fn($origin) => $origin !== ''));
    }

    return [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ];
}

function api_apply_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = api_allowed_origins();

    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
}

function api_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function api_error(string $message, int $status = 400): void
{
    api_response(['success' => false, 'error' => $message], $status);
}

function api_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function api_get_path(): string
{
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uriPath = rawurldecode($uriPath);
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/');

    if ($scriptDir !== '' && strpos($uriPath, $scriptDir) === 0) {
        $uriPath = substr($uriPath, strlen($scriptDir));
    }

    if (strpos($uriPath, '/index.php/') === 0) {
        $uriPath = substr($uriPath, strlen('/index.php'));
    } elseif ($uriPath === '/index.php') {
        $uriPath = '/';
    }

    return '/' . ltrim($uriPath, '/');
}

function api_get_current_user(PDO $pdo): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $userId = (int) (getCurrentUserId() ?? 0);
    if ($userId < 1) {
        return null;
    }

    ensureUserAccountSchema($pdo);
    $stmt = $pdo->prepare("SELECT user_id, name, email, role, is_disabled, phone FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !empty($user['is_disabled'])) {
        logout();
        return null;
    }

    return [
        'user_id' => (int) $user['user_id'],
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
    ];
}

function api_require_user(PDO $pdo, ?string $role = null): array
{
    $user = api_get_current_user($pdo);
    if ($user === null) {
        api_error('Unauthorized', 401);
    }

    if ($role !== null && (($user['role'] ?? '') !== $role)) {
        api_error('Forbidden', 403);
    }

    return $user;
}

function api_validate_date(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function api_normalize_time(string $time): ?string
{
    $time = trim($time);
    if ($time === '') {
        return null;
    }

    $timestamp = strtotime($time);
    if ($timestamp === false) {
        return null;
    }

    return date('H:i:s', $timestamp);
}

function api_validate_booking_window(string $startTime, string $endTime, int $minMinutes = 60, ?int $maxMinutes = 180): ?string
{
    if ($startTime >= $endTime) {
        return 'End time must be after start time.';
    }

    if ($startTime < '10:00:00' || $endTime > '22:00:00') {
        return 'Bookings must be within 10:00 to 22:00.';
    }

    $startTs = strtotime('2000-01-01 ' . $startTime);
    $endTs = strtotime('2000-01-01 ' . $endTime);
    $minutes = (int) (($endTs - $startTs) / 60);

    if ($minutes < $minMinutes) {
        return "Minimum booking duration is {$minMinutes} minutes.";
    }

    if ($maxMinutes !== null && $minutes > $maxMinutes) {
        return "Maximum booking duration is {$maxMinutes} minutes.";
    }

    return null;
}

function api_check_capacity(PDO $pdo, int $guests): bool
{
    $capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
    $capacityStmt->execute([$guests]);
    return ((int) $capacityStmt->fetchColumn()) > 0;
}

function api_shape_booking_payload(array $booking): array
{
    return [
        'booking_id' => (int) ($booking['booking_id'] ?? 0),
        'booking_date' => (string) ($booking['booking_date'] ?? ''),
        'start_time' => (string) ($booking['start_time'] ?? ''),
        'end_time' => (string) ($booking['end_time'] ?? ''),
        'number_of_guests' => (int) ($booking['number_of_guests'] ?? 0),
        'status' => (string) ($booking['status'] ?? 'pending'),
        'status_label' => getBookingStatusLabel((string) ($booking['status'] ?? 'pending')),
        'table_id' => isset($booking['table_id']) && $booking['table_id'] !== null ? (int) $booking['table_id'] : null,
        'table_number' => $booking['table_number'] ?? null,
        'special_request' => $booking['special_request'] ?? null,
        'customer_name' => $booking['customer_name'] ?? null,
        'customer_email' => $booking['customer_email'] ?? null,
        'customer_phone' => $booking['customer_phone'] ?? null,
        'booking_source' => $booking['booking_source'] ?? null,
    ];
}
