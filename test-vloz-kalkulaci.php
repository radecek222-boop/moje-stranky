<?php
/**
 * Test: Ruční vložení kalkulace do wgs_reklamace.kalkulace_data
 *
 * Tento skript testuje, zda se kalkulace správně ukládá a načítá
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit test.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Vložení Kalkulace</title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 1200px; margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 30px; border-radius: 8px; }
        h1 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        .success { background: #1e4620; border-left: 4px solid #4ec9b0; color: #4ec9b0; padding: 15px; margin: 15px 0; }
        .error { background: #4b1818; border-left: 4px solid #f48771; color: #f48771; padding: 15px; margin: 15px 0; }
        .info { background: #1a3a52; border-left: 4px solid #569cd6; color: #569cd6; padding: 15px; margin: 15px 0; }
        pre { background: #1e1e1e; border: 1px solid #3c3c3c; padding: 15px; overflow-x: auto; border-radius: 4px; color: #ce9178; }
        button { padding: 10px 20px; background: #0e639c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 10px 5px; }
        button:hover { background: #1177bb; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧪 Test Vložení Kalkulace</h1>";

$reklamaceId = 'POZ/2026/15-02/01';

// Testovací kalkulace data (podle tvého screenshotu z wizardu)
$testKalkulace = [
    'celkovaCena' => 572.80,
    'dopravne' => 2.80,
    'vzdalenost' => 5,
    'reklamaceBezDopravy' => false,
    'tezkyNabytek' => true,
    'druhaOsoba' => false,
    'typServisu' => 'calouneni',
    'sluzby' => [
        [
            'nazev' => 'Materiál dodán od WGS',
            'cena' => 50.00,
            'pocet' => 1
        ],
        [
            'nazev' => 'Vyzvednutí dílu na skladě',
            'cena' => 10.00,
            'pocet' => 1
        ]
    ],
    'dilyPrace' => [
        [
            'nazev' => 'Čalounické práce (4 díly)',
            'cena' => 415.00,
            'pocet' => 4,
            'detail' => 'První díl: 205 EUR + 3 dalších dílů × 70 EUR'
        ]
    ],
    'rozpis' => [
        'diagnostika' => 0,
        'calouneni' => [
            'sedaky' => 1,
            'operky' => 1,
            'podrucky' => 1,
            'panely' => 1,
            'pocetProduktu' => 1
        ],
        'mechanika' => [
            'relax' => 0,
            'vysuv' => 0
        ]
    ],
    'poznamka' => 'Testovací kalkulace - ručně vloženo'
];

try {
    $pdo = getDbConnection();

    if (!isset($_GET['vloz'])) {
        echo "<div class='info'>";
        echo "<strong>📋 Reklamace:</strong> {$reklamaceId}<br><br>";
        echo "<strong>🧪 Testovací kalkulace:</strong>";
        echo "<pre>" . json_encode($testKalkulace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "<a href='?vloz=1'><button>VLOŽIT KALKULACI DO DATABÁZE</button></a>";
        echo "<br><br>";
        echo "<a href='test-kalkulace-api.php?reklamace_id={$reklamaceId}'><button>OVĚŘIT API (po vložení)</button></a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // VLOŽIT KALKULACI
    echo "<div class='info'><strong>🔄 Vkládám testovací kalkulaci...</strong></div>";

    // Najít reklamaci
    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, cislo
        FROM wgs_reklamace
        WHERE reklamace_id = :rek_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':rek_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => is_numeric($reklamaceId) ? intval($reklamaceId) : 0
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        echo "<div class='error'>❌ Reklamace nenalezena!</div>";
        exit;
    }

    echo "<div class='success'>✅ Reklamace nalezena (ID: {$reklamace['id']})</div>";

    // UPDATE kalkulace_data
    $kalkulaceJson = json_encode($testKalkulace, JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET kalkulace_data = :kalkulace_data,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':kalkulace_data' => $kalkulaceJson,
        ':id' => $reklamace['id']
    ]);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        echo "<div class='success'>";
        echo "<strong>✅ KALKULACE ÚSPĚŠNĚ VLOŽENA!</strong><br><br>";
        echo "Reklamace ID: {$reklamace['id']}<br>";
        echo "Affected rows: {$affected}<br>";
        echo "JSON délka: " . strlen($kalkulaceJson) . " znaků";
        echo "</div>";

        // Ověřit, že se uložilo
        $stmt = $pdo->prepare("SELECT kalkulace_data FROM wgs_reklamace WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $reklamace['id']]);
        $verify = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($verify && !empty($verify['kalkulace_data'])) {
            echo "<div class='success'>✅ Ověřeno: Kalkulace je v databázi!</div>";
            echo "<pre>" . $verify['kalkulace_data'] . "</pre>";
        } else {
            echo "<div class='error'>❌ CHYBA: Kalkulace se neuložila!</div>";
        }

    } else {
        echo "<div class='error'>❌ UPDATE selhal (0 affected rows)</div>";
    }

    echo "<br><br>";
    echo "<a href='test-kalkulace-api.php?reklamace_id={$reklamaceId}'><button>SPUSTIT DIAGNOSTIKU</button></a> ";
    echo "<a href='?smazat=1'><button class='danger'>SMAZAT KALKULACI</button></a>";

} catch (PDOException $e) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

// SMAZAT KALKULACI
if (isset($_GET['smazat'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET kalkulace_data = NULL,
                updated_at = NOW()
            WHERE reklamace_id = :rek_id OR cislo = :cislo
        ");
        $stmt->execute([
            ':rek_id' => $reklamaceId,
            ':cislo' => $reklamaceId
        ]);

        echo "<div class='info'>🗑️ Kalkulace smazána</div>";
        echo "<a href='test-vloz-kalkulaci.php'><button>ZPĚT</button></a>";
    } catch (PDOException $e) {
        echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "</div></body></html>";
?>
