<?php
/**
 * Admin Stats API
 * API pro načtení statistik pro admin dashboard
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    $pdo = getDbConnection();

    // Počet reklamací
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $claimsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Počet uživatelů
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_users");
    $usersCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Počet online uživatelů (aktivity za posledních 15 minut)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as count
        FROM wgs_sessions
        WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $onlineCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Počet aktivních registračních klíčů
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_registration_keys
        WHERE is_active = 1
    ");
    $keysCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'claims' => (int)$claimsCount,
            'users' => (int)$usersCount,
            'online' => (int)$onlineCount,
            'keys' => (int)$keysCount
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
