<?php
/**
 * Diagnostika: Online uživatelé
 * Zobrazí stav last_activity v databázi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();

    // 1. Kontrola sloupce last_activity
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'last_activity'");
    $maSloupec = $stmt->rowCount() > 0;

    // 2. Všichni uživatelé s last_activity
    $stmt = $pdo->query("
        SELECT
            user_id,
            name,
            email,
            role,
            last_activity,
            TIMESTAMPDIFF(SECOND, last_activity, NOW()) as pred_sekundami,
            CASE
                WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'ONLINE'
                WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 'NEDÁVNO'
                ELSE 'OFFLINE'
            END as stav
        FROM wgs_users
        ORDER BY last_activity DESC
        LIMIT 20
    ");
    $uzivatele = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Počet online (< 5 min)
    $stmt = $pdo->query("
        SELECT COUNT(*) as pocet
        FROM wgs_users
        WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $onlinePocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    // 4. Aktuální čas serveru
    $stmt = $pdo->query("SELECT NOW() as cas");
    $serverCas = $stmt->fetch(PDO::FETCH_ASSOC)['cas'];

    echo json_encode([
        'status' => 'success',
        'ma_sloupec_last_activity' => $maSloupec,
        'server_cas' => $serverCas,
        'online_pocet' => (int)$onlinePocet,
        'uzivatele' => $uzivatele
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
