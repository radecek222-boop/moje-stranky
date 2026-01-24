<?php
/**
 * Konverze reklamace na pozáruční servis
 * NCE25-00000523-49 → Pozáruční servis
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může provést konverzi.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Konverze na pozáruční servis</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #666;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .data-table th, .data-table td {
            padding: 10px; border: 1px solid #ddd; text-align: left;
        }
        .data-table th { background: #f8f9fa; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    $puvodniId = 'NCE25-00000523-49';

    echo "<h1>🔄 Konverze reklamace na pozáruční servis</h1>";

    // Načíst původní reklamaci
    $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE reklamace_id = :id");
    $stmt->execute(['id' => $puvodniId]);
    $puvodni = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$puvodni) {
        echo "<div class='error'><strong>CHYBA:</strong> Reklamace s ID {$puvodniId} nebyla nalezena.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Původní reklamace nalezena:</strong><br>";
    echo "ID: {$puvodni['reklamace_id']}<br>";
    echo "Zákazník: {$puvodni['jmeno']}<br>";
    echo "Typ: {$puvodni['typ']}<br>";
    echo "Model: {$puvodni['model_vyrobku']}<br>";
    echo "</div>";

    // Zobrazit všechna data
    echo "<h2>Původní data</h2>";
    echo "<table class='data-table'>";
    echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
    foreach ($puvodni as $key => $value) {
        if ($value !== null && $value !== '') {
            echo "<tr><td><strong>{$key}</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
    }
    echo "</table>";

    // Pokud je nastaveno ?execute=1, provést konverzi
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM KONVERZI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Vygenerovat nové ID pro pozáruční servis
            // Změnit prefix z NCE na NPS (Natuzzi Pozáruční Servis)
            $noveId = str_replace('NCE', 'NPS', $puvodniId);

            // Kontrola, jestli ID již neexistuje
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_reklamace WHERE reklamace_id = :id");
            $checkStmt->execute(['id' => $noveId]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Reklamace s ID {$noveId} již existuje!");
            }

            // Připravit data pro novou reklamaci
            $novaData = $puvodni;
            unset($novaData['id']); // Auto-increment ID
            $novaData['reklamace_id'] = $noveId;
            $novaData['typ'] = 'POZÁRUČNÍ SERVIS';
            $novaData['stav'] = 'wait'; // Nová reklamace začíná ve stavu ČEKÁ
            $novaData['datum_vytvoreni'] = date('Y-m-d H:i:s');
            $novaData['poznamka_interniho_uzivatele'] =
                ($novaData['poznamka_interniho_uzivatele'] ?? '') .
                "\n\n[Konvertováno z reklamace {$puvodniId} dne " . date('Y-m-d H:i:s') . "]";

            // Vytvořit INSERT dotaz
            $columns = array_keys($novaData);
            $placeholders = array_map(function($col) { return ":$col"; }, $columns);

            $sql = "INSERT INTO wgs_reklamace (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute($novaData);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ KONVERZE ÚSPĚŠNÁ!</strong><br><br>";
            echo "Původní reklamace: <strong>{$puvodniId}</strong> (typ: {$puvodni['typ']})<br>";
            echo "Nová reklamace: <strong><a href='seznam.php?highlight={$noveId}' target='_blank'>{$noveId}</a></strong> (typ: POZÁRUČNÍ SERVIS)<br><br>";
            echo "Stav: ČEKÁ (NOVÁ)<br>";
            echo "Zákazník: {$novaData['jmeno']}<br>";
            echo "Model: {$novaData['model_vyrobku']}<br>";
            echo "</div>";

            echo "<p><a href='seznam.php?highlight={$noveId}' class='btn'>Zobrazit novou reklamaci v seznamu</a></p>";
            echo "<p><a href='admin.php' class='btn'>Zpět do admin</a></p>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA PŘI VYTVÁŘENÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Náhled - tlačítko pro spuštění
        echo "<div class='info'>";
        echo "<strong>NÁHLED KONVERZE:</strong><br><br>";
        echo "Původní ID: <strong>{$puvodniId}</strong><br>";
        echo "Nové ID: <strong>NPS25-00000523-49</strong><br><br>";
        echo "Změny:<br>";
        echo "- <strong>Typ:</strong> {$puvodni['typ']} → <strong>POZÁRUČNÍ SERVIS</strong><br>";
        echo "- <strong>Stav:</strong> → <strong>ČEKÁ (NOVÁ)</strong><br>";
        echo "- <strong>Datum vytvoření:</strong> → <strong>NOVÉ (aktuální čas)</strong><br>";
        echo "- <strong>Poznámka:</strong> Přidá se informace o konverzi<br><br>";
        echo "Všechna ostatní data (zákazník, adresa, model, fotky, atd.) zůstanou identická.";
        echo "</div>";

        echo "<form method='GET'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn' onclick='return confirm(\"Opravdu vytvořit novou pozáruční reklamaci?\")'>
                🚀 SPUSTIT KONVERZI
              </button>";
        echo "</form>";

        echo "<p><a href='admin.php' class='btn' style='background: #666;'>Zrušit</a></p>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
