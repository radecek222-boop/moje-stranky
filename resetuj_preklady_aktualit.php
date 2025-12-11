<?php
/**
 * Reset prekladu aktualit
 * Vymaze EN a IT preklady, aby se mohly znovu prelozit se spravnymi obrazky
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit reset.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Reset prekladu aktualit</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #fff; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #f59e0b; color: #ffd700; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Reset prekladu aktualit</h1>";

    // Zjistit aktualni stav
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_natuzzi_aktuality WHERE obsah_en IS NOT NULL AND obsah_en != ''");
    $pocetEN = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_natuzzi_aktuality WHERE obsah_it IS NOT NULL AND obsah_it != ''");
    $pocetIT = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_translation_cache");
    $pocetCache = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];

    echo "<div class='info'>";
    echo "<strong>Aktualni stav:</strong><br>";
    echo "Aktuality s EN prekladem: {$pocetEN}<br>";
    echo "Aktuality s IT prekladem: {$pocetIT}<br>";
    echo "Zaznamu v translation cache: {$pocetCache}";
    echo "</div>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='warning'><strong>SPOUSTIM RESET...</strong></div>";

        // Vymazat EN a IT preklady (pouzit prazdny retezec misto NULL)
        $stmt = $pdo->exec("UPDATE wgs_natuzzi_aktuality SET obsah_en = '', obsah_it = ''");
        echo "<div class='success'>Vymazano {$stmt} EN/IT prekladu z aktualit</div>";

        // Vymazat translation cache
        $stmt = $pdo->exec("DELETE FROM wgs_translation_cache WHERE entity_type = 'aktualita'");
        echo "<div class='success'>Vymazano {$stmt} zaznamu z translation cache</div>";

        echo "<div class='success'>";
        echo "<strong>RESET DOKONCEN</strong><br><br>";
        echo "Ted muzes jit na stranku <a href='/aktuality.php' style='color: #39ff14;'>Aktuality</a> a kliknout na vlajku EN nebo IT.<br>";
        echo "Preklady se vytvofi znovu se spravnymi obrazky.";
        echo "</div>";

    } else {
        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Tato akce vymaze vsechny EN a IT preklady aktualit!<br>";
        echo "Preklady se pak musi znovu vytvorit kliknutim na vlajku.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT RESET</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zrusit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn btn-secondary'>Zpet do Admin</a>";
echo "</div></body></html>";
?>
