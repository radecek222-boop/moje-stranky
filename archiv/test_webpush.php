<?php
/**
 * Diagnostika WebPush
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';

echo "<h1>WebPush Diagnostika</h1>";
echo "<pre>";

// 1. Test autoloaderu
echo "1. AUTOLOADER:\n";
$autoloadPath = __DIR__ . '/vendor/autoload.php';
echo "   Cesta: $autoloadPath\n";
echo "   Existuje: " . (file_exists($autoloadPath) ? 'ANO' : 'NE') . "\n";

if (file_exists($autoloadPath)) {
    try {
        require_once $autoloadPath;
        echo "   Nacten: ANO\n";
    } catch (Exception $e) {
        echo "   CHYBA: " . $e->getMessage() . "\n";
    }
}

// 2. Test tridy
echo "\n2. KNIHOVNA WEBPUSH:\n";
echo "   class_exists Minishlink\\WebPush\\WebPush: " . (class_exists('Minishlink\WebPush\WebPush') ? 'ANO' : 'NE') . "\n";

// 3. Test VAPID klicu
echo "\n3. VAPID KLICE:\n";
$vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
$vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
echo "   VAPID_PUBLIC_KEY: " . (empty($vapidPublic) ? 'CHYBI' : substr($vapidPublic, 0, 20) . '...') . "\n";
echo "   VAPID_PRIVATE_KEY: " . (empty($vapidPrivate) ? 'CHYBI' : substr($vapidPrivate, 0, 10) . '...') . "\n";

// 4. Test WGSWebPush tridy
echo "\n4. WGSWEBPUSH TRIDA:\n";
try {
    require_once __DIR__ . '/includes/WebPush.php';
    echo "   Soubor nacten: ANO\n";

    $pdo = getDbConnection();
    $webPush = new WGSWebPush($pdo);
    echo "   Instance vytvorena: ANO\n";
    echo "   Inicializovano: " . ($webPush->jeInicializovano() ? 'ANO' : 'NE') . "\n";
    echo "   Chyba: " . ($webPush->getChyba() ?: 'zadna') . "\n";

} catch (Exception $e) {
    echo "   CHYBA: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   FATAL ERROR: " . $e->getMessage() . "\n";
    echo "   Soubor: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// 5. Vendor slozka
echo "\n5. VENDOR SLOZKA:\n";
$vendorWebPush = __DIR__ . '/vendor/minishlink/web-push/src/WebPush.php';
echo "   $vendorWebPush\n";
echo "   Existuje: " . (file_exists($vendorWebPush) ? 'ANO' : 'NE') . "\n";

echo "</pre>";
?>
