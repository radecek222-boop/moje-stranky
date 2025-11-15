<?php
// BEZPEČNOST: CORS headery odstraněny
// Admin API je pouze pro stejnou doménu, nepotřebuje CORS
// Pokud by bylo nutné CORS povolit, musí být omezeno na konkrétní doménu

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/rate_limiter.php';

header("Content-Type: application/json; charset=utf-8");

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireCSRF();
}

// Ověření admin přístupu
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Přístup odepřen"]);
    exit;
}

// HIGH PRIORITY FIX: Rate limiting na admin API
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'admin';
$identifier = "admin_api_{$ip}_{$userId}";

$rateLimiter = new RateLimiter(getDbConnection());
$rateCheck = $rateLimiter->checkLimit($identifier, 'admin_api', [
    'max_attempts' => 100,
    'window_minutes' => 10,
    'block_minutes' => 30
]);

if (!$rateCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        "status" => "error",
        "message" => $rateCheck['message'],
        "retry_after" => $rateCheck['reset_at']
    ]);
    exit;
}

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Chyba databáze"]);
    exit;
}
