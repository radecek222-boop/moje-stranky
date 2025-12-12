<?php
/**
 * Test prekladace - rucni spusteni prekladu
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/translator.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit test.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test prekladu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1 { color: #fff; border-bottom: 3px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        pre { background: #111; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .btn { display: inline-block; padding: 12px 24px; background: #39ff14; color: #000; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .btn:hover { background: #2dd310; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Test prekladu aktualit</h1>";

    // Nacist aktuality
    $stmt = $pdo->query("SELECT id, datum, obsah_cz, obsah_en, obsah_it FROM wgs_natuzzi_aktuality ORDER BY datum DESC LIMIT 3");
    $aktuality = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'><strong>Nalezeno aktualit:</strong> " . count($aktuality) . "</div>";

    foreach ($aktuality as $i => $aktualita) {
        echo "<div class='info'>";
        echo "<strong>Aktualita #{$aktualita['id']} ({$aktualita['datum']})</strong><br>";
        echo "CZ delka: " . strlen($aktualita['obsah_cz']) . " znaku<br>";
        echo "EN delka: " . strlen($aktualita['obsah_en']) . " znaku<br>";
        echo "IT delka: " . strlen($aktualita['obsah_it']) . " znaku<br>";
        echo "CZ prvnich 200 znaku: <pre>" . htmlspecialchars(substr($aktualita['obsah_cz'], 0, 200)) . "</pre>";
        echo "EN prvnich 200 znaku: <pre>" . htmlspecialchars(substr($aktualita['obsah_en'], 0, 200)) . "</pre>";
        echo "</div>";
    }

    // Pokud je ?translate=1, spustit preklad prvni aktuality
    if (isset($_GET['translate']) && $_GET['translate'] === '1' && !empty($aktuality)) {
        $prvniAktualita = $aktuality[0];

        if (empty($prvniAktualita['obsah_cz'])) {
            echo "<div class='error'>Prvni aktualita nema cesky obsah!</div>";
        } else {
            echo "<div class='info'><strong>SPOUSTIM PREKLAD...</strong></div>";

            $translator = new WGSTranslator($pdo);

            // Prelozit do EN
            $startTime = microtime(true);
            $prekladEn = $translator->preloz($prvniAktualita['obsah_cz'], 'en', 'aktualita', $prvniAktualita['id']);
            $casEn = round((microtime(true) - $startTime) * 1000);

            echo "<div class='success'>";
            echo "<strong>Preklad do EN dokoncen</strong> (cas: {$casEn}ms)<br>";
            echo "Delka: " . strlen($prekladEn) . " znaku<br>";
            echo "Prvnich 500 znaku: <pre>" . htmlspecialchars(substr($prekladEn, 0, 500)) . "</pre>";
            echo "</div>";

            // Ulozit do DB
            $stmtUpdate = $pdo->prepare("UPDATE wgs_natuzzi_aktuality SET obsah_en = :obsah WHERE id = :id");
            $stmtUpdate->execute(['obsah' => $prekladEn, 'id' => $prvniAktualita['id']]);
            echo "<div class='success'>Ulozeno do databaze!</div>";

            // Prelozit do IT
            $startTime = microtime(true);
            $prekladIt = $translator->preloz($prvniAktualita['obsah_cz'], 'it', 'aktualita', $prvniAktualita['id']);
            $casIt = round((microtime(true) - $startTime) * 1000);

            echo "<div class='success'>";
            echo "<strong>Preklad do IT dokoncen</strong> (cas: {$casIt}ms)<br>";
            echo "Delka: " . strlen($prekladIt) . " znaku<br>";
            echo "Prvnich 500 znaku: <pre>" . htmlspecialchars(substr($prekladIt, 0, 500)) . "</pre>";
            echo "</div>";

            // Ulozit do DB
            $stmtUpdate = $pdo->prepare("UPDATE wgs_natuzzi_aktuality SET obsah_it = :obsah WHERE id = :id");
            $stmtUpdate->execute(['obsah' => $prekladIt, 'id' => $prvniAktualita['id']]);
            echo "<div class='success'>Ulozeno do databaze!</div>";
        }
    } else {
        echo "<a href='?translate=1' class='btn'>SPUSTIT TESTOVACI PREKLAD</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='aktuality.php' style='color: #39ff14;'>Zpet na Aktuality</a>";
echo " | <a href='admin.php' style='color: #39ff14;'>Admin</a>";
echo "</div></body></html>";
?>
