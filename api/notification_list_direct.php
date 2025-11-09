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

    $pdo = getDbConnection();

    // Načtení všech notifikačních šablon
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            description,
            trigger_event,
            recipient_type,
            type,
            subject,
            template,
            variables,
            cc_emails,
            bcc_emails,
            active,
            created_at,
            updated_at
        FROM wgs_notifications
        ORDER BY name ASC
    ");

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dekóduj JSON pole
    foreach ($notifications as &$notif) {
        $notif['variables'] = $notif['variables'] ? json_decode($notif['variables'], true) : [];
        $notif['cc_emails'] = $notif['cc_emails'] ? json_decode($notif['cc_emails'], true) : [];
        $notif['bcc_emails'] = $notif['bcc_emails'] ? json_decode($notif['bcc_emails'], true) : [];
        $notif['active'] = (bool)$notif['active'];
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
