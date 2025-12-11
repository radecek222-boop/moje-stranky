<?php
/**
 * Diagnostika prekladu aktualit
 * Zkontroluje stav DB a otestuje preklad
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/translator.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika prekladu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #2d2d2d; padding: 30px; border-radius: 10px; }
        h1, h2 { color: #39ff14; border-bottom: 2px solid #39ff14; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #28a745; color: #90EE90; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #3d1a1a; border: 1px solid #dc3545; color: #ff8888; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #f59e0b; color: #ffd700; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2d3d; border: 1px solid #17a2b8; color: #87CEEB; padding: 12px; border-radius: 5px; margin: 10px 0; }
        pre { background: #111; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #444; padding: 10px; text-align: left; }
        th { background: #333; }
        .btn { display: inline-block; padding: 12px 24px; background: #39ff14; color: #000; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #2dd310; }
        .empty { color: #ff6666; font-style: italic; }
        .filled { color: #66ff66; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika prekladu aktualit</h1>";

    // 1. KONTROLA STRUKTURY TABULKY
    echo "<h2>1. Struktura tabulky wgs_natuzzi_aktuality</h2>";
    $stmt = $pdo->query("DESCRIBE wgs_natuzzi_aktuality");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $maObsahCz = false;
    $maObsahEn = false;
    $maObsahIt = false;

    echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        $class = '';
        if ($col['Field'] === 'obsah_cz') { $maObsahCz = true; $class = 'filled'; }
        if ($col['Field'] === 'obsah_en') { $maObsahEn = true; $class = 'filled'; }
        if ($col['Field'] === 'obsah_it') { $maObsahIt = true; $class = 'filled'; }
        echo "<tr class='{$class}'><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";

    if ($maObsahCz && $maObsahEn && $maObsahIt) {
        echo "<div class='success'>Vsechny potrebne sloupce existuji (obsah_cz, obsah_en, obsah_it)</div>";
    } else {
        echo "<div class='error'>CHYBI SLOUPCE! CZ: " . ($maObsahCz ? 'OK' : 'CHYBI') . " | EN: " . ($maObsahEn ? 'OK' : 'CHYBI') . " | IT: " . ($maObsahIt ? 'OK' : 'CHYBI') . "</div>";
    }

    // 2. STAV DAT V DATABAZI
    echo "<h2>2. Aktualni stav prekladu v databazi</h2>";
    $stmt = $pdo->query("
        SELECT id, datum,
               LENGTH(obsah_cz) as delka_cz,
               LENGTH(obsah_en) as delka_en,
               LENGTH(obsah_it) as delka_it,
               SUBSTRING(obsah_cz, 1, 100) as ukazka_cz,
               SUBSTRING(obsah_en, 1, 100) as ukazka_en,
               SUBSTRING(obsah_it, 1, 100) as ukazka_it
        FROM wgs_natuzzi_aktuality
        ORDER BY datum DESC
        LIMIT 5
    ");
    $aktuality = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table><tr><th>ID</th><th>Datum</th><th>CZ (znaky)</th><th>EN (znaky)</th><th>IT (znaky)</th></tr>";
    foreach ($aktuality as $a) {
        $czClass = $a['delka_cz'] > 0 ? 'filled' : 'empty';
        $enClass = $a['delka_en'] > 0 ? 'filled' : 'empty';
        $itClass = $a['delka_it'] > 0 ? 'filled' : 'empty';

        echo "<tr>";
        echo "<td>{$a['id']}</td>";
        echo "<td>{$a['datum']}</td>";
        echo "<td class='{$czClass}'>{$a['delka_cz']}</td>";
        echo "<td class='{$enClass}'>{$a['delka_en']}</td>";
        echo "<td class='{$itClass}'>{$a['delka_it']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Zobrazit ukazky obsahu
    if (!empty($aktuality)) {
        $prvni = $aktuality[0];
        echo "<div class='info'>";
        echo "<strong>Ukazka prvni aktuality (ID: {$prvni['id']}):</strong><br><br>";
        echo "<strong>CZ:</strong><pre>" . htmlspecialchars($prvni['ukazka_cz'] ?: '(prazdne)') . "...</pre>";
        echo "<strong>EN:</strong><pre>" . htmlspecialchars($prvni['ukazka_en'] ?: '(prazdne)') . "...</pre>";
        echo "<strong>IT:</strong><pre>" . htmlspecialchars($prvni['ukazka_it'] ?: '(prazdne)') . "...</pre>";
        echo "</div>";
    }

    // 3. TEST PREKLADU
    echo "<h2>3. Test prekladu (MyMemory API)</h2>";

    if (isset($_GET['test_preklad']) && $_GET['test_preklad'] === '1') {
        $testText = "Dobrý den, toto je testovací text pro ověření funkčnosti překladu.";

        echo "<div class='info'>Testovaci text: <strong>{$testText}</strong></div>";

        $translator = new WGSTranslator($pdo);

        // Test EN
        $startEn = microtime(true);
        $prekladEn = $translator->preloz($testText, 'en');
        $casEn = round((microtime(true) - $startEn) * 1000);

        if ($prekladEn !== $testText) {
            echo "<div class='success'>EN preklad OK ({$casEn}ms): <strong>{$prekladEn}</strong></div>";
        } else {
            echo "<div class='error'>EN preklad SELHAL - vratil stejny text!</div>";
        }

        // Test IT
        $startIt = microtime(true);
        $prekladIt = $translator->preloz($testText, 'it');
        $casIt = round((microtime(true) - $startIt) * 1000);

        if ($prekladIt !== $testText) {
            echo "<div class='success'>IT preklad OK ({$casIt}ms): <strong>{$prekladIt}</strong></div>";
        } else {
            echo "<div class='error'>IT preklad SELHAL - vratil stejny text!</div>";
        }
    } else {
        echo "<a href='?test_preklad=1' class='btn'>SPUSTIT TEST PREKLADU</a>";
    }

    // 4. MANUALNI PREKLAD PRVNI AKTUALITY
    echo "<h2>4. Manualni preklad prvni aktuality</h2>";

    if (isset($_GET['preloz_prvni']) && in_array($_GET['preloz_prvni'], ['en', 'it'])) {
        $cilovyJazyk = $_GET['preloz_prvni'];

        $stmt = $pdo->query("SELECT id, obsah_cz FROM wgs_natuzzi_aktuality ORDER BY datum DESC LIMIT 1");
        $prvniAktualita = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prvniAktualita && !empty($prvniAktualita['obsah_cz'])) {
            echo "<div class='info'>Prekladam aktualitu #{$prvniAktualita['id']} do {$cilovyJazyk}...</div>";

            $translator = new WGSTranslator($pdo);
            $start = microtime(true);
            $preklad = $translator->preloz($prvniAktualita['obsah_cz'], $cilovyJazyk, 'aktualita', $prvniAktualita['id']);
            $cas = round((microtime(true) - $start) * 1000);

            if ($preklad !== $prvniAktualita['obsah_cz']) {
                // Ulozit do DB
                $sloupec = 'obsah_' . $cilovyJazyk;
                $stmt = $pdo->prepare("UPDATE wgs_natuzzi_aktuality SET {$sloupec} = :obsah WHERE id = :id");
                $stmt->execute(['obsah' => $preklad, 'id' => $prvniAktualita['id']]);

                echo "<div class='success'>PREKLAD ULOZEN! ({$cas}ms)<br><br>";
                echo "<strong>Prvnich 500 znaku prekladu:</strong><pre>" . htmlspecialchars(substr($preklad, 0, 500)) . "...</pre>";
                echo "</div>";
            } else {
                echo "<div class='error'>PREKLAD SELHAL - API vratilo stejny text!</div>";
            }
        } else {
            echo "<div class='error'>Zadna aktualita k prekladu nebo prazdny CZ obsah!</div>";
        }
    } else {
        echo "<a href='?preloz_prvni=en' class='btn'>PRELOZIT PRVNI DO EN</a>";
        echo "<a href='?preloz_prvni=it' class='btn'>PRELOZIT PRVNI DO IT</a>";
    }

    // 5. KONTROLA TRANSLATION CACHE
    echo "<h2>5. Translation cache</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_translation_cache");
    $pocetCache = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
    echo "<div class='info'>Zaznamu v cache: <strong>{$pocetCache}</strong></div>";

    if ($pocetCache > 0) {
        $stmt = $pdo->query("SELECT id, target_lang, LENGTH(source_text) as src_len, LENGTH(translated_text) as tgt_len, created_at FROM wgs_translation_cache ORDER BY created_at DESC LIMIT 5");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table><tr><th>ID</th><th>Jazyk</th><th>Zdroj (znaky)</th><th>Preklad (znaky)</th><th>Vytvoreno</th></tr>";
        foreach ($cache as $c) {
            echo "<tr><td>{$c['id']}</td><td>{$c['target_lang']}</td><td>{$c['src_len']}</td><td>{$c['tgt_len']}</td><td>{$c['created_at']}</td></tr>";
        }
        echo "</table>";
    }

    // 6. RATE LIMITER
    echo "<h2>6. Rate limiter</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_rate_limits WHERE action_key LIKE '%translate%' ORDER BY last_request DESC LIMIT 5");
    $limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($limits)) {
        echo "<div class='info'>Zadne zaznamy o rate limitingu pro preklady</div>";
    } else {
        echo "<table><tr><th>Akce</th><th>IP</th><th>Pocet</th><th>Posledni</th></tr>";
        foreach ($limits as $l) {
            echo "<tr><td>{$l['action_key']}</td><td>{$l['ip_address']}</td><td>{$l['request_count']}</td><td>{$l['last_request']}</td></tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><br><a href='aktuality.php' class='btn' style='background:#666;'>Zpet na Aktuality</a>";
echo "<a href='admin.php' class='btn' style='background:#666;'>Admin</a>";
echo "</div></body></html>";
?>
