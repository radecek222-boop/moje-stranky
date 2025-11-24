<?php
/**
 * Notification List Direct API
 * Načtení seznamu emailových a SMS notifikací pro admin panel
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

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    $pdo = getDbConnection();

    // Kontrola zda tabulka existuje
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM wgs_notifications LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        // Tabulka neexistuje nebo nemáme oprávnění
        error_log("wgs_notifications table check failed: " . $e->getMessage());
    }

    if (!$tableExists) {
        // Vrátit prázdný seznam pokud tabulka neexistuje
        echo json_encode([
            'status' => 'success',
            'data' => [],
            'count' => 0,
            'message' => 'Notification system not initialized'
        ]);
        exit;
    }

    // Načtení všech notifikačních šablon
    // Použijeme SELECT * aby to fungovalo i když se struktura tabulky mění
    $stmt = $pdo->query("
        SELECT *
        FROM wgs_notifications
        ORDER BY id ASC
    ");

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dekóduj JSON pole a přidej pole 'name' pokud neexistuje
    foreach ($notifications as &$notif) {
        // Pokud neexistuje 'name', použij něco jiného
        if (!isset($notif['name'])) {
            $notif['name'] = $notif['type'] ?? $notif['subject'] ?? 'Notifikace #' . $notif['id'];
        }

        $notif['variables'] = isset($notif['variables']) && $notif['variables'] ? json_decode($notif['variables'], true) : [];
        $notif['cc_emails'] = isset($notif['cc_emails']) && $notif['cc_emails'] ? json_decode($notif['cc_emails'], true) : [];
        $notif['bcc_emails'] = isset($notif['bcc_emails']) && $notif['bcc_emails'] ? json_decode($notif['bcc_emails'], true) : [];
        $notif['active'] = isset($notif['active']) ? (bool)$notif['active'] : false;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $notifications,
        'count' => count($notifications)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
