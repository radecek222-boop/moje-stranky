<?php
/**
 * Heartbeat API - aktualizuje last_activity uzivatele
 *
 * Tento endpoint je volan z JavaScriptu kazdych 30 sekund
 * pro udrzeni presneho online stavu uzivatele.
 *
 * Obchazi Service Worker cache diky POST metode.
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Pouze prihlaseni uzivatele
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Neprihlaseno']);
    exit;
}

try {
    $pdo = getDbConnection();
    $userId = $_SESSION['user_id'];

    // Aktualizovat last_activity
    $stmt = $pdo->prepare("UPDATE wgs_users SET last_activity = NOW() WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $affected = $stmt->rowCount();

    // Pokud uzivatel neexistuje (admin), vytvorit
    if ($affected === 0 && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
        $stmt = $pdo->prepare("
            INSERT INTO wgs_users (user_id, name, email, role, is_active, last_activity, created_at)
            VALUES (:user_id, :name, :email, 'admin', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $_SESSION['user_name'] ?? 'Administrator',
            ':email' => $_SESSION['user_email'] ?? 'admin@wgs-service.cz'
        ]);
    }

    $_SESSION['last_db_activity_update'] = time();

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    error_log("Heartbeat error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba serveru']);
}
?>
