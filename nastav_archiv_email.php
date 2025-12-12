<?php
/**
 * Jednorazovy skript pro nastaveni archivacniho emailu
 */
require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

try {
    $pdo = getDbConnection();

    // Zkontrolovat zda existuje
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_system_config WHERE config_key = 'email_archive_address'");
    $stmt->execute();
    $existuje = $stmt->fetchColumn() > 0;

    if ($existuje) {
        $stmt = $pdo->prepare("UPDATE wgs_system_config SET config_value = :email WHERE config_key = 'email_archive_address'");
    } else {
        $stmt = $pdo->prepare("INSERT INTO wgs_system_config (config_key, config_value) VALUES ('email_archive_address', :email)");
    }

    $stmt->execute(['email' => 'mail@wgs-service.cz']);

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Hotovo</title></head><body style='font-family:sans-serif;padding:50px;'>";
    echo "<h1 style='color:#28a745;'>Archivacni email nastaven!</h1>";
    echo "<p>Vsechny odeslane emaily budou automaticky kopirovany na: <strong>mail@wgs-service.cz</strong></p>";
    echo "<p><a href='/admin.php'>Zpet do admin</a></p>";
    echo "</body></html>";

} catch (Exception $e) {
    echo "CHYBA: " . htmlspecialchars($e->getMessage());
}
?>
