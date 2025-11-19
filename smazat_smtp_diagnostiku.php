<?php
/**
 * Smazání všech diagnostických SMTP souborů ze serveru
 * BEZPEČNOSTNÍ CLEANUP
 */

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (isset($_GET['admin_key']) && hash('sha256', $_GET['admin_key']) === getenv('ADMIN_KEY_HASH')) {
        $_SESSION['is_admin'] = true;
    } else {
        die("PŘÍSTUP ODEPŘEN");
    }
}

$filesToDelete = [
    'smtp_test.php',
    'test_smtp_varianty.php',
    'test_smtp_port25.php',
    'test_smtp_587.php',
    'test_smtp_pripojeni.php',
    'test_smtp_web.php',
    'zobraz_smtp_config.php',
    'oprav_smtp_config.php',
    'oprav_smtp_ihned.php',
    'oprav_smtp_konfiguraci.php',
    'oprav_smtp_na_websmtp.php',
    'nastav_spravne_smtp.php',
    'nastav_presne_podle_uzivatele.php',
    'nastav_smtp_587_auth.php',
    'nastav_smtp_cesky.php',
    'nastav_smtp_cesky_hosting.php',
    'nastav_smtp_heslo.php',
    'nastav_websmtp.php',
    'zmen_from_email.php',
    'aplikuj_smtp_fix.php'
];

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SMTP Cleanup</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".success{color:#4ec9b0;} .error{color:#f48771;}</style></head><body>";
echo "<h1>🧹 SMTP Diagnostické soubory - Cleanup</h1>";

$deleted = [];
$notFound = [];
$errors = [];

foreach ($filesToDelete as $file) {
    $filePath = __DIR__ . '/' . $file;

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            $deleted[] = $file;
            echo "<div class='success'>✅ Smazáno: {$file}</div>";
        } else {
            $errors[] = $file;
            echo "<div class='error'>❌ Chyba při mazání: {$file}</div>";
        }
    } else {
        $notFound[] = $file;
        echo "<div style='color:#999;'>⊘ Neexistuje: {$file}</div>";
    }
}

echo "<br><hr><br>";
echo "<h2>📊 Souhrn:</h2>";
echo "<p><strong class='success'>Smazáno:</strong> " . count($deleted) . " souborů</p>";
echo "<p><strong style='color:#999;'>Nenalezeno:</strong> " . count($notFound) . " souborů</p>";
echo "<p><strong class='error'>Chyby:</strong> " . count($errors) . " souborů</p>";

if (count($deleted) > 0) {
    echo "<br><p class='success'><strong>✅ Cleanup dokončen!</strong> Diagnostické soubory byly odstraněny ze serveru.</p>";
}

echo "<br><p><a href='/admin.php' style='padding:10px 20px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;'>Zpět do Admin panelu</a></p>";

// Smazat i tento soubor
echo "<br><hr><br>";
echo "<p><strong>⚠️ POSLEDNÍ KROK:</strong> Tento cleanup skript je také diagnostický soubor.</p>";
echo "<p>Pro dokončení bezpečnosti smažte i tento soubor: <code>smazat_smtp_diagnostiku.php</code></p>";

echo "</body></html>";
?>
