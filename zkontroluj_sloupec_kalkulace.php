<?php
/**
 * Kontrola jestli sloupec kalkulace_data existuje v tabulce wgs_reklamace
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("<h1>PŘÍSTUP ODEPŘEN</h1>");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola sloupce kalkulace_data</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; padding: 10px; margin: 10px 0; }
        .error { background: #f8d7da; padding: 10px; margin: 10px 0; }
        pre { background: white; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>Kontrola sloupce kalkulace_data v tabulce wgs_reklamace</h1>";

    // Získat strukturu tabulky
    $stmt = $pdo->query("DESCRIBE wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $maKalkulaceData = false;

    echo "<h2>Sloupce v tabulce wgs_reklamace:</h2>";
    echo "<pre>";
    foreach ($columns as $col) {
        $nazev = $col['Field'];
        $typ = $col['Type'];
        $null = $col['Null'];

        if ($nazev === 'kalkulace_data') {
            $maKalkulaceData = true;
            echo "✅ <strong>{$nazev}</strong> - {$typ} - NULL: {$null}\n";
        } else {
            echo "   {$nazev} - {$typ}\n";
        }
    }
    echo "</pre>";

    if ($maKalkulaceData) {
        echo "<div class='success'><strong>✅ Sloupec 'kalkulace_data' EXISTUJE</strong></div>";

        // Zkusit INSERT test
        echo "<h2>Test zápisu:</h2>";

        $testReklamaceId = 'TEST_' . time();

        // Vytvořit testovací reklamaci
        $stmt = $pdo->prepare("
            INSERT INTO wgs_reklamace
            (reklamace_id, jmeno, telefon, email, adresa, stav, typ, created_by, created_at, updated_at)
            VALUES
            (:rek_id, 'Test Kalkulace', '123456789', 'test@test.cz', 'Testovací adresa', 'wait', 'reklamace', 1, NOW(), NOW())
        ");
        $stmt->execute([':rek_id' => $testReklamaceId]);

        echo "<div class='success'>Testovací reklamace vytvořena: {$testReklamaceId}</div>";

        // Uložit kalkulaci
        $testKalkulace = json_encode([
            'celkovaCena' => 500,
            'dopravne' => 50,
            'vzdalenost' => 100,
            'sluzby' => [
                ['nazev' => 'Diagnostika', 'cena' => 110, 'pocet' => 1]
            ],
            'dilyPrace' => [
                ['nazev' => 'Čalounění sedáku', 'cena' => 205, 'pocet' => 1]
            ]
        ]);

        $stmt = $pdo->prepare("
            UPDATE wgs_reklamace
            SET kalkulace_data = :kalkulace_data
            WHERE reklamace_id = :rek_id
        ");
        $stmt->execute([
            ':kalkulace_data' => $testKalkulace,
            ':rek_id' => $testReklamaceId
        ]);

        echo "<div class='success'>Kalkulace uložena</div>";

        // Načíst zpět
        $stmt = $pdo->prepare("SELECT kalkulace_data FROM wgs_reklamace WHERE reklamace_id = :rek_id");
        $stmt->execute([':rek_id' => $testReklamaceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['kalkulace_data']) {
            echo "<div class='success'>✅ Kalkulace úspěšně načtena z databáze!</div>";
            echo "<pre>" . json_encode(json_decode($result['kalkulace_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='error'>❌ Kalkulace se nepodařilo načíst!</div>";
        }

        // Smazat testovací záznam
        $stmt = $pdo->prepare("DELETE FROM wgs_reklamace WHERE reklamace_id = :rek_id");
        $stmt->execute([':rek_id' => $testReklamaceId]);
        echo "<div class='success'>Testovací záznam smazán</div>";

    } else {
        echo "<div class='error'><strong>❌ Sloupec 'kalkulace_data' NEEXISTUJE!</strong></div>";
        echo "<p>Je potřeba vytvořit sloupec pomocí migrace:</p>";
        echo "<pre>ALTER TABLE wgs_reklamace ADD COLUMN kalkulace_data LONGTEXT NULL;</pre>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
