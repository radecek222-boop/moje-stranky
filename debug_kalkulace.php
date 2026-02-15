<?php
/**
 * DEBUG SKRIPT: Kalkulace data z databáze
 *
 * Použití: debug_kalkulace.php?id=123
 *
 * Ukáže přesně co je v databázi pro danou reklamaci
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit debug.");
}

$reklamaceId = $_GET['id'] ?? null;

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>DEBUG: Kalkulace Data</title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 1200px;
               margin: 30px auto; padding: 20px; background: #1a1a1a; color: #0f0; }
        .container { background: #000; padding: 20px; border: 2px solid #0f0;
                     border-radius: 5px; }
        h1 { color: #0f0; border-bottom: 2px solid #0f0; padding-bottom: 10px; }
        h2 { color: #0ff; margin-top: 30px; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .info { color: #0ff; }
        pre { background: #222; padding: 15px; border-left: 3px solid #0f0;
              overflow-x: auto; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #0f0; color: #000; padding: 10px; text-align: left; }
        td { background: #222; color: #0f0; padding: 8px; border: 1px solid #0f0; }
        .label { color: #0ff; font-weight: bold; }
        .value { color: #fff; }
        .input { padding: 8px; background: #222; color: #0f0; border: 2px solid #0f0;
                 margin: 10px 0; font-size: 16px; width: 200px; }
        .btn { padding: 10px 20px; background: #0f0; color: #000; border: none;
               cursor: pointer; font-weight: bold; margin: 10px 5px 10px 0; }
        .btn:hover { background: #0ff; }
    </style>
</head>
<body>
<div class='container'>";

if (!$reklamaceId) {
    echo "<h1>🔍 DEBUG: Kalkulace Data</h1>";
    echo "<form method='GET'>";
    echo "<label class='label'>Zadej ID reklamace:</label><br>";
    echo "<input type='number' name='id' class='input' placeholder='např. 123' required>";
    echo "<button type='submit' class='btn'>NAČÍST DATA</button>";
    echo "</form>";
    echo "</div></body></html>";
    exit;
}

echo "<h1>🔍 DEBUG: Kalkulace pro reklamaci #{$reklamaceId}</h1>";

try {
    $pdo = getDbConnection();

    // Načíst kalkulaci z databáze
    $stmt = $pdo->prepare("
        SELECT * FROM wgs_kalkulace
        WHERE reklamace_id = :id
    ");
    $stmt->execute(['id' => $reklamaceId]);
    $kalkulace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kalkulace) {
        echo "<h2 class='error'>❌ KALKULACE NEEXISTUJE</h2>";
        echo "<p class='warning'>Pro tuto reklamaci nebyla vytvořena kalkulace.</p>";
        echo "<a href='?'>← Zkusit jiné ID</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h2 class='success'>✅ KALKULACE NALEZENA</h2>";

    // Zobrazit základní údaje
    echo "<table>";
    echo "<tr><th colspan='2'>ZÁKLADNÍ ÚDAJE</th></tr>";
    echo "<tr><td class='label'>ID kalkulace</td><td class='value'>{$kalkulace['id']}</td></tr>";
    echo "<tr><td class='label'>ID reklamace</td><td class='value'>{$kalkulace['reklamace_id']}</td></tr>";
    echo "<tr><td class='label'>Celková cena</td><td class='value'>{$kalkulace['celkova_cena']} EUR</td></tr>";
    echo "<tr><td class='label'>Dopravné</td><td class='value'>{$kalkulace['dopravne']} EUR</td></tr>";
    echo "<tr><td class='label'>Vzdálenost</td><td class='value'>{$kalkulace['vzdalenost']} km</td></tr>";
    echo "<tr><td class='label'>Adresa</td><td class='value'>" . htmlspecialchars($kalkulace['adresa']) . "</td></tr>";
    echo "<tr><td class='label'>Typ servisu</td><td class='value'>" . htmlspecialchars($kalkulace['typ_servisu'] ?? 'N/A') . "</td></tr>";
    echo "<tr><td class='label'>Těžký nábytek</td><td class='value'>" . ($kalkulace['tezky_nabytek'] ? 'ANO' : 'NE') . "</td></tr>";
    echo "<tr><td class='label'>Druhá osoba</td><td class='value'>" . ($kalkulace['druha_osoba'] ? 'ANO' : 'NE') . "</td></tr>";
    echo "<tr><td class='label'>Vytvořeno</td><td class='value'>{$kalkulace['created_at']}</td></tr>";
    echo "</table>";

    // KRITICKÉ: Dekódovat rozpis JSON
    $rozpis = null;
    if (!empty($kalkulace['rozpis'])) {
        $rozpis = json_decode($kalkulace['rozpis'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<h2 class='error'>❌ CHYBA: JSON rozpis je POŠKOZENÝ</h2>";
            echo "<p class='error'>JSON error: " . json_last_error_msg() . "</p>";
            echo "<pre>" . htmlspecialchars($kalkulace['rozpis']) . "</pre>";
        } else {
            echo "<h2 class='success'>✅ ROZPIS JSON (dekódováno)</h2>";
            echo "<pre>" . htmlspecialchars(json_encode($rozpis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        }
    } else {
        echo "<h2 class='warning'>⚠️ ROZPIS JE PRÁZDNÝ</h2>";
        echo "<p class='warning'>Pole 'rozpis' v databázi je NULL nebo prázdné.</p>";
    }

    // Analýza co MĚLO být v rozpisu
    echo "<h2 class='info'>📊 ANALÝZA ROZPISU</h2>";

    if ($rozpis) {
        // Diagnostika
        if (isset($rozpis['diagnostika']) && $rozpis['diagnostika'] > 0) {
            echo "<p class='success'>✅ Diagnostika: {$rozpis['diagnostika']} EUR</p>";
        } else {
            echo "<p class='warning'>⚠️ Diagnostika: NENÍ</p>";
        }

        // Čalounění
        if (isset($rozpis['calouneni'])) {
            $cal = $rozpis['calouneni'];
            $celkem = ($cal['sedaky'] ?? 0) + ($cal['operky'] ?? 0) +
                      ($cal['podrucky'] ?? 0) + ($cal['panely'] ?? 0);

            if ($celkem > 0) {
                echo "<p class='success'>✅ Čalounické práce: {$celkem} dílů</p>";
                echo "<pre>";
                echo "  Sedáky: " . ($cal['sedaky'] ?? 0) . "\n";
                echo "  Opěrky: " . ($cal['operky'] ?? 0) . "\n";
                echo "  Područky: " . ($cal['podrucky'] ?? 0) . "\n";
                echo "  Panely: " . ($cal['panely'] ?? 0) . "\n";
                echo "  Počet produktů: " . ($cal['pocetProduktu'] ?? 0) . "\n";
                echo "</pre>";
            } else {
                echo "<p class='warning'>⚠️ Čalounické práce: ŽÁDNÉ díly</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Čalounické práce: NENÍ v rozpisu</p>";
        }

        // Mechanika
        if (isset($rozpis['mechanika'])) {
            $mech = $rozpis['mechanika'];
            $celkem = ($mech['relax'] ?? 0) + ($mech['vysuv'] ?? 0);

            if ($celkem > 0) {
                echo "<p class='success'>✅ Mechanické práce: {$celkem} mechanismů</p>";
                echo "<pre>";
                echo "  Relax: " . ($mech['relax'] ?? 0) . "\n";
                echo "  Výsuv: " . ($mech['vysuv'] ?? 0) . "\n";
                echo "</pre>";
            } else {
                echo "<p class='warning'>⚠️ Mechanické práce: ŽÁDNÉ mechanismy</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Mechanické práce: NENÍ v rozpisu</p>";
        }

        // Doplňky
        if (isset($rozpis['doplnky'])) {
            $dopl = $rozpis['doplnky'];

            if ($dopl['material'] ?? false) {
                echo "<p class='success'>✅ Materiál od WGS: ANO</p>";
            } else {
                echo "<p class='warning'>⚠️ Materiál od WGS: NE</p>";
            }

            if ($dopl['vyzvednutiSklad'] ?? false) {
                echo "<p class='success'>✅ Vyzvednutí na skladě: ANO</p>";
            } else {
                echo "<p class='warning'>⚠️ Vyzvednutí na skladě: NE</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Doplňky: NEJSOU v rozpisu</p>";
        }

    } else {
        echo "<p class='error'>❌ Rozpis NEEXISTUJE - nelze analyzovat</p>";
    }

    // Co by mělo být převedeno do služeb/dílů
    echo "<h2 class='info'>🔄 CO BY MĚLO BÝT PŘEVEDENO</h2>";

    if ($rozpis) {
        echo "<h3>SLUŽBY (sluzby[]):</h3>";
        echo "<ul>";

        if (isset($rozpis['diagnostika']) && $rozpis['diagnostika'] > 0) {
            echo "<li class='success'>Inspekce / diagnostika: {$rozpis['diagnostika']} EUR</li>";
        }

        if (isset($rozpis['doplnky']['material']) && $rozpis['doplnky']['material']) {
            echo "<li class='success'>Materiál dodán od WGS: 50 EUR</li>";
        }

        if (isset($rozpis['doplnky']['vyzvednutiSklad']) && $rozpis['doplnky']['vyzvednutiSklad']) {
            echo "<li class='success'>Vyzvednutí dílu na skladě: 10 EUR</li>";
        }

        if (isset($rozpis['mechanika'])) {
            $celkemMech = ($rozpis['mechanika']['relax'] ?? 0) + ($rozpis['mechanika']['vysuv'] ?? 0);
            if ($celkemMech > 0 && ($kalkulace['typ_servisu'] ?? '') === 'mechanika') {
                echo "<li class='success'>Základní servisní sazba: 165 EUR</li>";
            }
        }

        echo "</ul>";

        echo "<h3>DÍLY A PRÁCE (dilyPrace[]):</h3>";
        echo "<ul>";

        if (isset($rozpis['calouneni'])) {
            $cal = $rozpis['calouneni'];
            $celkem = ($cal['sedaky'] ?? 0) + ($cal['operky'] ?? 0) +
                      ($cal['podrucky'] ?? 0) + ($cal['panely'] ?? 0);

            if ($celkem > 0) {
                echo "<li class='success'>Čalounické práce ({$celkem} " .
                     ($celkem === 1 ? 'díl' : ($celkem <= 4 ? 'díly' : 'dílů')) .
                     "): [vypočítat podle prvního dílu 205 EUR + další 70 EUR]</li>";
            }
        }

        if (isset($rozpis['mechanika'])) {
            $celkemMech = ($rozpis['mechanika']['relax'] ?? 0) + ($rozpis['mechanika']['vysuv'] ?? 0);
            if ($celkemMech > 0) {
                $cenaMech = $celkemMech * 45;
                echo "<li class='success'>Mechanické opravy ({$celkemMech} " .
                     ($celkemMech === 1 ? 'mechanismus' : ($celkemMech <= 4 ? 'mechanismy' : 'mechanismů')) .
                     "): {$cenaMech} EUR</li>";
            }
        }

        echo "</ul>";

    } else {
        echo "<p class='error'>❌ Nelze převést - rozpis neexistuje</p>";
    }

    // RAW data
    echo "<h2 class='info'>📄 RAW DATA Z DATABÁZE</h2>";
    echo "<pre>" . htmlspecialchars(json_encode($kalkulace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

    echo "<a href='?' class='btn'>← Načíst jiné ID</a>";

} catch (Exception $e) {
    echo "<h2 class='error'>❌ CHYBA</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div></body></html>";
?>
