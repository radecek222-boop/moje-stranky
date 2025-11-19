<?php
/**
 * Nastavení SMTP: websmtp.cesky-hosting.cz:587 S autentizací
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("
        UPDATE wgs_smtp_settings
        SET smtp_host = 'websmtp.cesky-hosting.cz',
            smtp_port = 587,
            smtp_encryption = 'tls',
            smtp_username = 'reklamace@wgs-service.cz',
            updated_at = NOW()
        WHERE id = :id
    ");
    $updateStmt->execute([':id' => $current['id']]);

    header('Location: /diagnostika_email_queue.php');
    exit;

} catch (Exception $e) {
    die("Chyba: " . $e->getMessage());
}
?>
