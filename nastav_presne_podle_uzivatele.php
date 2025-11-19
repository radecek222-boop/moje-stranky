<?php
/**
 * NASTAVENÍ SMTP PŘESNĚ JAK ŘEKL UŽIVATEL
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

try {
    $pdo = getDbConnection();

    // PŘESNĚ JAK ŘEKL UŽIVATEL
    $stmt = $pdo->prepare("
        UPDATE wgs_smtp_settings
        SET smtp_host = 'websmtp.cesky-hosting.cz',
            smtp_port = 25,
            smtp_username = 'wgs-service.cz',
            smtp_password = 'p7u.s13mR2018',
            smtp_encryption = 'tls',
            smtp_from_email = 'reklamace@wgs-service.cz',
            smtp_from_name = 'White Glove Service'
        WHERE is_active = 1
    ");

    $stmt->execute();

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SMTP nastaveno</title></head><body>";
    echo "<h1>✅ SMTP NASTAVENO PŘESNĚ JAK JSTE ŘEKL</h1>";
    echo "<pre>";
    echo "Host: websmtp.cesky-hosting.cz\n";
    echo "Port: 25\n";
    echo "SMTPSecure: tls (STARTTLS)\n";
    echo "Auth: true\n";
    echo "Username: wgs-service.cz\n";
    echo "Password: p7u.s13mR2018\n";
    echo "From: reklamace@wgs-service.cz\n";
    echo "</pre>";
    echo "<p><strong>Nyní čekáme na DNS propagaci (10-30 minut).</strong></p>";
    echo "<p><a href='/smtp_test.php' style='padding: 10px 20px; background: green; color: white; text-decoration: none;'>Test SMTP</a></p>";
    echo "</body></html>";

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage();
}
?>
