<?php
/**
 * Zobrazení SMTP hesla z databáze
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SMTP Password Check</title>
<style>
body{font-family:monospace;padding:20px;background:#f5f5f5;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;font-family:monospace;}
</style></head><body>";

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SELECT smtp_username, smtp_password, LENGTH(smtp_password) as pass_length FROM wgs_smtp_settings WHERE is_active = 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp) {
        echo "<div class='info'>";
        echo "<strong>SMTP Credentials:</strong><br>";
        echo "Username: <code>{$smtp['smtp_username']}</code><br>";
        echo "Password length: <code>{$smtp['pass_length']} znaků</code><br>";
        echo "Password: <code>" . ($smtp['pass_length'] > 0 ? $smtp['smtp_password'] : 'PRÁZDNÉ!') . "</code>";
        echo "</div>";

        if ($smtp['pass_length'] == 0) {
            echo "<div class='error'>";
            echo "<strong>❌ HESLO JE PRÁZDNÉ!</strong><br>";
            echo "To je problém - SMTP vyžaduje heslo pro autentizaci.<br>";
            echo "Heslo bylo pravděpodobně ztraceno při sjednocování konfigurace.";
            echo "</div>";
        }
    } else {
        echo "<div class='error'>Nenalezena žádná aktivní SMTP konfigurace!</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
