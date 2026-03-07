<?php
/**
 * Diagnostický skript pro testování načítání kalkulace z API
 *
 * Tento skript ověří:
 * 1. Co je v databázi (wgs_kalkulace, wgs_nabidky)
 * 2. Co vrací API protokol_api.php
 * 3. Zda se kalkulace správně načítá
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Kalkulace API</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #dcdcaa;
            margin-top: 30px;
            border-left: 4px solid #dcdcaa;
            padding-left: 10px;
        }
        .success {
            background: #1e4620;
            border-left: 4px solid #4ec9b0;
            color: #4ec9b0;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
        }
        .error {
            background: #4b1818;
            border-left: 4px solid #f48771;
            color: #f48771;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
        }
        .warning {
            background: #4d4106;
            border-left: 4px solid #dcdcaa;
            color: #dcdcaa;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
        }
        .info {
            background: #1a3a52;
            border-left: 4px solid #569cd6;
            color: #569cd6;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
        }
        pre {
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            padding: 15px;
            overflow-x: auto;
            border-radius: 4px;
            color: #ce9178;
        }
        .label {
            color: #9cdcfe;
            font-weight: bold;
        }
        input {
            padding: 8px;
            background: #3c3c3c;
            border: 1px solid #555;
            color: #d4d4d4;
            border-radius: 4px;
            width: 300px;
        }
        button {
            padding: 10px 20px;
            background: #0e639c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #1177bb;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Diagnostika načítání kalkulace z API</h1>";

// Formulář pro zadání ID reklamace
if (!isset($_GET['reklamace_id'])) {
    echo "<div class='info'>";
    echo "<strong>Zadejte ID reklamace k testování:</strong><br><br>";
    echo "<form method='GET'>";
    echo "<input type='text' name='reklamace_id' placeholder='např. POZ/2026/15-02/01' required>";
    echo " <button type='submit'>TESTOVAT</button>";
    echo "</form>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

$reklamaceId = $_GET['reklamace_id'];

echo "<div class='info'>";
echo "<strong class='label'>Testovaná reklamace:</strong> " . htmlspecialchars($reklamaceId);
echo "</div>";

try {
    $pdo = getDbConnection();

    // 1. KONTROLA REKLAMACE
    echo "<h2>1️⃣ Kontrola existence reklamace</h2>";

    $stmt = $pdo->prepare("
        SELECT reklamace_id, cislo, id, jmeno, email, stav, typ
        FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        echo "<div class='error'>CHYBA: Reklamace NENALEZENA v databázi!</div>";
        exit;
    }

    echo "<div class='success'>OK: Reklamace nalezena:</div>";
    echo "<pre>" . json_encode($reklamace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

    // 2. KONTROLA KALKULACE V wgs_kalkulace
    echo "<h2>2️⃣ Kontrola tabulky wgs_kalkulace</h2>";

    // Zkontrolovat zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_kalkulace'");
    $tabulkaExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tabulkaExistuje) {
        $stmt = $pdo->prepare("
            SELECT * FROM wgs_kalkulace
            WHERE reklamace_id = :reklamace_id
            LIMIT 1
        ");
        $stmt->execute([':reklamace_id' => $reklamace['reklamace_id']]);
        $kalkulaceRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($kalkulaceRow) {
            echo "<div class='success'>OK: Kalkulace NALEZENA v wgs_kalkulace:</div>";
            echo "<pre>" . json_encode($kalkulaceRow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

            if (!empty($kalkulaceRow['rozpis_json'])) {
                $kalkulaceData = json_decode($kalkulaceRow['rozpis_json'], true);
                echo "<div class='info'><strong>Dekódovaný JSON rozpis:</strong></div>";
                echo "<pre>" . json_encode($kalkulaceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<div class='warning'>POZOR: Sloupec rozpis_json je PRÁZDNÝ!</div>";
            }
        } else {
            echo "<div class='warning'>POZOR: Kalkulace NENÍ v tabulce wgs_kalkulace</div>";
        }
    } else {
        echo "<div class='error'>CHYBA: Tabulka wgs_kalkulace NEEXISTUJE v databázi!</div>";
        echo "<div class='info'>Kalkulace se pravděpodobně ukládá do wgs_nabidky</div>";
    }

    // 3. KONTROLA NABÍDKY V wgs_nabidky
    echo "<h2>3️⃣ Kontrola tabulky wgs_nabidky</h2>";

    // Nejdřív zobrazit strukturu tabulky
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'><strong>Struktura tabulky wgs_nabidky:</strong></div>";
    echo "<pre>";
    foreach ($sloupce as $sloupec) {
        echo $sloupec['Field'] . " (" . $sloupec['Type'] . ")\n";
    }
    echo "</pre>";

    // Zjistit správný sloupec pro časové řazení
    $sloupceNames = array_column($sloupce, 'Field');
    $casovySloupec = null;
    foreach (['created_at', 'created', 'datum_vytvoreni', 'timestamp', 'id'] as $moznySloupec) {
        if (in_array($moznySloupec, $sloupceNames)) {
            $casovySloupec = $moznySloupec;
            break;
        }
    }

    $orderBy = $casovySloupec ? "ORDER BY {$casovySloupec} DESC" : "";

    $stmt = $pdo->prepare("
        SELECT * FROM wgs_nabidky
        WHERE reklamace_id = :reklamace_id
        {$orderBy}
        LIMIT 1
    ");
    $stmt->execute([':reklamace_id' => $reklamace['reklamace_id']]);
    $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($nabidka) {
        echo "<div class='success'>OK: Nabídka NALEZENA v wgs_nabidky:</div>";
        echo "<pre>" . json_encode($nabidka, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<div class='warning'>POZOR: Nabídka NENÍ v tabulce wgs_nabidky</div>";
    }

    // 4. TEST API VOLÁNÍ
    echo "<h2>4️⃣ Test API protokol_api.php (funkce loadReklamace)</h2>";

    // Simulace volání funkce loadReklamace
    require_once __DIR__ . '/api/protokol_api.php';

    $apiResult = loadReklamace(['id' => $reklamaceId]);

    echo "<div class='info'><strong>📡 API Response:</strong></div>";
    echo "<pre>" . json_encode($apiResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

    // 5. ANALÝZA VÝSLEDKU
    echo "<h2>5️⃣ Analýza výsledku</h2>";

    if (isset($apiResult['kalkulace']) && $apiResult['kalkulace'] !== null) {
        echo "<div class='success'>OK: API VRACÍ kalkulaci!</div>";

        if (isset($apiResult['kalkulace']['sluzby']) && is_array($apiResult['kalkulace']['sluzby']) && count($apiResult['kalkulace']['sluzby']) > 0) {
            echo "<div class='success'>OK: Kalkulace obsahuje pole 'sluzby' (" . count($apiResult['kalkulace']['sluzby']) . " položek)</div>";
            echo "<pre>" . json_encode($apiResult['kalkulace']['sluzby'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='error'>CHYBA: Pole 'sluzby' je PRÁZDNÉ nebo NEEXISTUJE!</div>";
        }

        if (isset($apiResult['kalkulace']['dilyPrace']) && is_array($apiResult['kalkulace']['dilyPrace']) && count($apiResult['kalkulace']['dilyPrace']) > 0) {
            echo "<div class='success'>OK: Kalkulace obsahuje pole 'dilyPrace' (" . count($apiResult['kalkulace']['dilyPrace']) . " položek)</div>";
            echo "<pre>" . json_encode($apiResult['kalkulace']['dilyPrace'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='warning'>POZOR: Pole 'dilyPrace' je prázdné</div>";
        }

        echo "<div class='info'><strong>💰 Celková cena:</strong> " . ($apiResult['kalkulace']['celkovaCena'] ?? 'N/A') . " EUR</div>";

    } else {
        echo "<div class='error'>CHYBA: API NEVRACÍ kalkulaci! (klíč 'kalkulace' je null nebo neexistuje)</div>";
    }

    // 6. ZÁVĚR
    echo "<h2>6️⃣ Závěr a doporučení</h2>";

    if (!$kalkulaceRow && !$nabidka) {
        echo "<div class='error'>";
        echo "CHYBA: <strong>PROBLÉM:</strong> Reklamace NEMÁ ani kalkulaci v wgs_kalkulace, ani nabídku v wgs_nabidky!<br><br>";
        echo "<strong>ŘEŠENÍ:</strong><br>";
        echo "1. Otevři kalkulační wizard pro tuto reklamaci<br>";
        echo "2. Vyplň kalkulaci<br>";
        echo "3. Ulož kalkulaci (měla by se uložit do wgs_kalkulace nebo wgs_nabidky)<br>";
        echo "4. Spusť tento test znovu";
        echo "</div>";
    } elseif (isset($apiResult['kalkulace']) && isset($apiResult['kalkulace']['sluzby']) && count($apiResult['kalkulace']['sluzby']) > 0) {
        echo "<div class='success'>";
        echo "OK: <strong>VŠE FUNGUJE SPRÁVNĚ!</strong><br><br>";
        echo "API vrací kalkulaci s vyplněným polem 'sluzby'.<br>";
        echo "PDF PRICELIST by měl zobrazovat kompletní rozpis.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "POZOR: <strong>ČÁSTEČNÝ PROBLÉM:</strong><br><br>";
        echo "Data jsou v databázi, ale API je nevrací správně, nebo pole 'sluzby' je prázdné.<br>";
        echo "Zkontroluj funkci loadReklamace() v api/protokol_api.php";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><br>";
echo "<a href='test-kalkulace-api.php' style='color: #569cd6; text-decoration: none;'>← Testovat jinou reklamaci</a>";
echo "</div></body></html>";
?>
