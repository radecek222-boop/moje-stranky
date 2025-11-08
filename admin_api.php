<?php
// BEZPEČNOST: CORS headery odstraněny
// Admin API je pouze pro stejnou doménu, nepotřebuje CORS
// Pokud by bylo nutné CORS povolit, musí být omezeno na konkrétní doménu

require_once __DIR__ . '/init.php';

header("Content-Type: application/json; charset=utf-8");

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    requireCSRF();
}

// Ověření admin přístupu
if (!isset($_SESSION['wgs_admin']) || $_SESSION['wgs_admin'] !== true) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Přístup odepřen"]);
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
