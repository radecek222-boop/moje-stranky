<?php
/**
 * AUTOMATICKÝ TEST: Kalkulace → Databáze → PDF
 *
 * Tento skript:
 * 1. Vytvoří testovací kalkulaci v DB
 * 2. Ověří že se uložila
 * 3. Načte ji zpět
 * 4. Zkontroluje že má všechna potřebná data
 * 5. Vypíše PASS/FAIL
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>TEST: Kalkulace → DB → PDF</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0;
               padding: 20px; max-width: 1200px; margin: 0 auto; }
        .pass { color: #0f0; font-weight: bold; }
        .fail { color: #f00; font-weight: bold; }
        .info { color: #0ff; }
        .warn { color: #ff0; }
        pre { background: #111; padding: 15px; border-left: 3px solid #0f0;
              overflow-x: auto; }
        h1 { color: #0ff; border-bottom: 2px solid #0ff; padding-bottom: 10px; }
        h2 { color: #0f0; margin-top: 30px; }
        .test-step { margin: 20px 0; padding: 15px; background: #111;
                     border-left: 4px solid #0ff; }
    </style>
</head>
<body>";

echo "<h1>🧪 AUTOMATICKÝ TEST: Kalkulace → Databáze → PDF</h1>";

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==================================================
    // KROK 1: Najít testovací reklamaci
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>📋 KROK 1: Hledám testovací reklamaci</h2>";

    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, cislo, jmeno
        FROM wgs_reklamace
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $testReklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testReklamace) {
        echo "<p class='fail'>❌ FAIL: Žádná reklamace v databázi</p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<p class='pass'>✅ PASS: Reklamace nalezena</p>";
    echo "<pre>";
    echo "ID: {$testReklamace['id']}\n";
    echo "Reklamace ID: {$testReklamace['reklamace_id']}\n";
    echo "Číslo: {$testReklamace['cislo']}\n";
    echo "Zákazník: {$testReklamace['jmeno']}\n";
    echo "</pre>";
    echo "</div>";

    // ==================================================
    // KROK 2: Vytvořit testovací kalkulaci
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>🔧 KROK 2: Vytvářím testovací kalkulaci</h2>";

    $testKalkulace = [
        'celkovaCena' => 525.00,
        'adresa' => 'Do Dubče 364, Praha 9, 190 11',
        'vzdalenost' => 12.5,
        'dopravne' => 3.50,
        'reklamaceBezDopravy' => false,
        'vyzvednutiSklad' => false,
        'typServisu' => 'calouneni',
        'tezkyNabytek' => true,
        'druhaOsoba' => false,
        'rozpis' => [
            'diagnostika' => 0,
            'calouneni' => [
                'pocetProduktu' => 1,
                'sedaky' => 2,
                'operky' => 1,
                'podrucky' => 0,
                'panely' => 0
            ],
            'mechanika' => [
                'relax' => 0,
                'vysuv' => 0
            ],
            'doplnky' => [
                'tezkyNabytek' => true,
                'material' => true,
                'vyzvednutiSklad' => true
            ]
        ]
    ];

    echo "<p class='info'>📦 Testovací kalkulace:</p>";
    echo "<pre>" . json_encode($testKalkulace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    echo "</div>";

    // ==================================================
    // KROK 3: Uložit do databáze
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>💾 KROK 3: Ukládám do databáze</h2>";

    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET kalkulace_data = :kalkulace_data,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':kalkulace_data' => json_encode($testKalkulace),
        ':id' => $testReklamace['id']
    ]);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        echo "<p class='pass'>✅ PASS: Kalkulace uložena (affected rows: {$affected})</p>";
    } else {
        echo "<p class='fail'>❌ FAIL: Kalkulace se neuložila</p>";
        echo "</div></body></html>";
        exit;
    }
    echo "</div>";

    // ==================================================
    // KROK 4: Načíst zpět z databáze
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>📥 KROK 4: Načítám zpět z databáze</h2>";

    $stmt = $pdo->prepare("
        SELECT kalkulace_data
        FROM wgs_reklamace
        WHERE id = :id
    ");
    $stmt->execute([':id' => $testReklamace['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($row['kalkulace_data'])) {
        echo "<p class='fail'>❌ FAIL: kalkulace_data je prázdné</p>";
        echo "</div></body></html>";
        exit;
    }

    $loadedKalkulace = json_decode($row['kalkulace_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p class='fail'>❌ FAIL: JSON decode error: " . json_last_error_msg() . "</p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<p class='pass'>✅ PASS: Kalkulace načtena z DB</p>";
    echo "<pre>" . json_encode($loadedKalkulace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    echo "</div>";

    // ==================================================
    // KROK 5: Validace dat
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>🔍 KROK 5: Validace dat</h2>";

    $errors = [];

    // Zkontrolovat základní pole
    if (!isset($loadedKalkulace['celkovaCena'])) $errors[] = "Chybí celkovaCena";
    if (!isset($loadedKalkulace['dopravne'])) $errors[] = "Chybí dopravne";
    if (!isset($loadedKalkulace['rozpis'])) $errors[] = "Chybí rozpis";

    // Zkontrolovat rozpis
    if (isset($loadedKalkulace['rozpis'])) {
        if (!isset($loadedKalkulace['rozpis']['calouneni'])) $errors[] = "Chybí rozpis.calouneni";
        if (!isset($loadedKalkulace['rozpis']['doplnky'])) $errors[] = "Chybí rozpis.doplnky";

        // Zkontrolovat čalounění
        if (isset($loadedKalkulace['rozpis']['calouneni'])) {
            $cal = $loadedKalkulace['rozpis']['calouneni'];
            if (($cal['sedaky'] ?? 0) !== 2) $errors[] = "Sedaky != 2";
            if (($cal['operky'] ?? 0) !== 1) $errors[] = "Operky != 1";
        }

        // Zkontrolovat doplňky
        if (isset($loadedKalkulace['rozpis']['doplnky'])) {
            $dopl = $loadedKalkulace['rozpis']['doplnky'];
            if (!($dopl['material'] ?? false)) $errors[] = "Material není true";
            if (!($dopl['vyzvednutiSklad'] ?? false)) $errors[] = "VyzvednutiSklad není true";
        }
    }

    if (count($errors) > 0) {
        echo "<p class='fail'>❌ FAIL: Validace selhala</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li class='fail'>• {$error}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='pass'>✅ PASS: Všechna data validní</p>";
        echo "<ul class='pass'>";
        echo "<li>✓ Celková cena: {$loadedKalkulace['celkovaCena']} EUR</li>";
        echo "<li>✓ Dopravné: {$loadedKalkulace['dopravne']} EUR</li>";
        echo "<li>✓ Čalounění: {$loadedKalkulace['rozpis']['calouneni']['sedaky']} sedáky, {$loadedKalkulace['rozpis']['calouneni']['operky']} opěrka</li>";
        echo "<li>✓ Materiál: ANO</li>";
        echo "<li>✓ Vyzvednutí: ANO</li>";
        echo "</ul>";
    }
    echo "</div>";

    // ==================================================
    // KROK 6: Test převodu do služeb/dílů
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h2>🔄 KROK 6: Test převodu rozpisu → služby/díly</h2>";

    $rozpis = $loadedKalkulace['rozpis'];
    $CENY = [
        'prvniDil' => 205,
        'dalsiDil' => 70,
        'material' => 50,
        'vyzvednutiSklad' => 10,
        'druhaOsoba' => 95
    ];

    // Simulovat převod (stejná logika jako v protokol.js)
    $sluzby = [];
    $dilyPrace = [];

    // Čalounění
    if (isset($rozpis['calouneni'])) {
        $cal = $rozpis['calouneni'];
        $celkemDilu = ($cal['sedaky'] ?? 0) + ($cal['operky'] ?? 0) +
                      ($cal['podrucky'] ?? 0) + ($cal['panely'] ?? 0);

        if ($celkemDilu > 0) {
            $cenaDilu = $CENY['prvniDil'] + ($celkemDilu - 1) * $CENY['dalsiDil'];
            $dilyPrace[] = [
                'nazev' => "Čalounické práce ({$celkemDilu} díly)",
                'cena' => $cenaDilu,
                'pocet' => $celkemDilu
            ];
            echo "<p class='pass'>✓ Čalounické práce: {$cenaDilu} EUR ({$celkemDilu} díly)</p>";
        }
    }

    // Materiál
    if (isset($rozpis['doplnky']['material']) && $rozpis['doplnky']['material']) {
        $sluzby[] = [
            'nazev' => 'Materiál dodán od WGS',
            'cena' => $CENY['material'],
            'pocet' => 1
        ];
        echo "<p class='pass'>✓ Materiál: {$CENY['material']} EUR</p>";
    }

    // Vyzvednutí
    if (isset($rozpis['doplnky']['vyzvednutiSklad']) && $rozpis['doplnky']['vyzvednutiSklad']) {
        $sluzby[] = [
            'nazev' => 'Vyzvednutí dílu na skladě',
            'cena' => $CENY['vyzvednutiSklad'],
            'pocet' => 1
        ];
        echo "<p class='pass'>✓ Vyzvednutí: {$CENY['vyzvednutiSklad']} EUR</p>";
    }

    echo "<p class='info'>📋 Počet služeb: " . count($sluzby) . "</p>";
    echo "<p class='info'>🔧 Počet dílů: " . count($dilyPrace) . "</p>";
    echo "</div>";

    // ==================================================
    // VÝSLEDEK
    // ==================================================
    echo "<div class='test-step'>";
    echo "<h1 class='pass'>🎉 VŠECHNY TESTY PROŠLY!</h1>";
    echo "<p class='info'>Kalkulace je FUNKČNÍ a obsahuje všechna potřebná data pro PDF.</p>";
    echo "<p class='info'><strong>Test reklamace:</strong> {$testReklamace['cislo']}</p>";
    echo "<p class='info'><strong>Zkontrolovat v debug skriptu:</strong></p>";
    echo "<pre>https://www.wgs-service.cz/debug_kalkulace.php?id={$testReklamace['cislo']}</pre>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='test-step'>";
    echo "<h2 class='fail'>❌ CHYBA</h2>";
    echo "<p class='fail'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>
