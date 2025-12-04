<?php
/**
 * PSA Kalkulátor - Vložení zaměstnanců přímo do databáze
 * Tento skript vloží všechny zaměstnance přímo bez závislosti na JSON souboru
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit import.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>PSA - Vložení zaměstnanců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>PSA - Vložení zaměstnanců</h1>";

// Kompletní seznam zaměstnanců
$zamestnanci = [
    ['id' => 1, 'jmeno' => 'Nevečný Tomáš', 'ucet' => '1528062183', 'banka' => '0800', 'typ' => 'standard'],
    ['id' => 2, 'jmeno' => 'Stana', 'ucet' => '7122546660', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 3, 'jmeno' => 'Anastasia', 'ucet' => '6647340003', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 4, 'jmeno' => 'Maryna Sosovuik', 'ucet' => '306903309', 'banka' => '0300', 'typ' => 'standard'],
    ['id' => 5, 'jmeno' => 'Naksin Seninec', 'ucet' => '5795877043', 'banka' => '0800', 'typ' => 'standard'],
    ['id' => 6, 'jmeno' => 'Ivana Senynets', 'ucet' => '5795877043', 'banka' => '0800', 'typ' => 'standard'],
    ['id' => 7, 'jmeno' => 'Olha Shkudor', 'ucet' => '', 'banka' => '', 'typ' => 'swift',
     'swift_iban' => 'UA913052990000026207520148665', 'swift_bic' => 'PBANUA2XXXX',
     'swift_banka' => 'JSC CB PRIVATBANK', 'swift_adresa' => '1D HRUSHEVSKOHO STR., KYIV, 01001, UKRAINE',
     'swift_prijemce' => 'Olha Shkudor'],
    ['id' => 8, 'jmeno' => 'Roman Liakh', 'ucet' => '2187259002', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 9, 'jmeno' => 'Maksim Seninec', 'ucet' => '5795877043', 'banka' => '0800', 'typ' => 'standard'],
    ['id' => 10, 'jmeno' => 'Ivan Lichtej', 'ucet' => '325021694', 'banka' => '0300', 'typ' => 'standard'],
    ['id' => 11, 'jmeno' => 'Piven Tetiana', 'ucet' => '2848333004', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 12, 'jmeno' => 'Vitalina', 'ucet' => '2409720010', 'banka' => '3030', 'typ' => 'standard'],
    ['id' => 13, 'jmeno' => 'Tetiana', 'ucet' => '', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 14, 'jmeno' => 'Kataryna', 'ucet' => '6829917002', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 15, 'jmeno' => 'Petr Danek', 'ucet' => '2052322019', 'banka' => '3030', 'typ' => 'standard'],
    ['id' => 16, 'jmeno' => 'Ruslana', 'ucet' => '3531394338', 'banka' => '0300', 'typ' => 'standard'],
    ['id' => 17, 'jmeno' => 'Roman Zhabko', 'ucet' => '2167257018', 'banka' => '3030', 'typ' => 'standard'],
    ['id' => 18, 'jmeno' => 'Václav Stárek', 'ucet' => '7122546660', 'banka' => '5500', 'typ' => 'standard'],
    ['id' => 19, 'jmeno' => 'Marek', 'ucet' => '', 'banka' => '0800', 'typ' => 'special',
     'poznamka' => 'Bonus 20 Kč za každou odpracovanou hodinu všech ostatních zaměstnanců'],
    ['id' => 20, 'jmeno' => 'Lenka', 'ucet' => '270791797', 'banka' => '0300', 'typ' => 'pausalni',
     'pausalni_limit' => 1500000, 'pausalni_dan' => 8716],
    ['id' => 21, 'jmeno' => 'Radek', 'ucet' => '188784838', 'banka' => '0300', 'typ' => 'special2',
     'poznamka' => 'Bonus 20 Kč za každou odpracovanou hodinu všech ostatních zaměstnanců + skryté prémie holek'],
    ['id' => 22, 'jmeno' => 'Prémie', 'ucet' => '', 'banka' => '', 'typ' => 'premie_polozka'],
];

try {
    $pdo = getDbConnection();

    // Kontrola tabulky
    $stmt = $pdo->query("SHOW TABLES LIKE 'psa_zamestnanci'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>Tabulka psa_zamestnanci neexistuje! Nejprve spusťte <a href='psa_vytvorit_tabulky.php'>vytvoření tabulek</a>.</div>";
        echo "</div></body></html>";
        exit;
    }

    // Zobrazit náhled
    echo "<div class='info'>Počet zaměstnanců k vložení: <strong>" . count($zamestnanci) . "</strong></div>";

    echo "<h4>Zaměstnanci:</h4>";
    echo "<table><tr><th>ID</th><th>Jméno</th><th>Účet</th><th>Banka</th><th>Typ</th></tr>";
    foreach ($zamestnanci as $z) {
        echo "<tr>";
        echo "<td>" . $z['id'] . "</td>";
        echo "<td>" . htmlspecialchars($z['jmeno']) . "</td>";
        echo "<td>" . htmlspecialchars($z['ucet']) . "</td>";
        echo "<td>" . htmlspecialchars($z['banka']) . "</td>";
        echo "<td>" . htmlspecialchars($z['typ']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Spustit import
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>VKLÁDÁM ZAMĚSTNANCE...</strong></div>";

        $stmtInsert = $pdo->prepare("
            INSERT INTO psa_zamestnanci
            (id, jmeno, ucet, banka, typ, aktivni, poznamka,
             swift_iban, swift_bic, swift_banka, swift_adresa, swift_prijemce,
             pausalni_limit, pausalni_dan)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            jmeno = VALUES(jmeno),
            ucet = VALUES(ucet),
            banka = VALUES(banka),
            typ = VALUES(typ),
            poznamka = VALUES(poznamka),
            swift_iban = VALUES(swift_iban),
            swift_bic = VALUES(swift_bic),
            swift_banka = VALUES(swift_banka),
            swift_adresa = VALUES(swift_adresa),
            swift_prijemce = VALUES(swift_prijemce),
            pausalni_limit = VALUES(pausalni_limit),
            pausalni_dan = VALUES(pausalni_dan)
        ");

        $vlozeno = 0;
        foreach ($zamestnanci as $z) {
            $stmtInsert->execute([
                $z['id'],
                $z['jmeno'],
                $z['ucet'],
                $z['banka'],
                $z['typ'],
                $z['poznamka'] ?? null,
                $z['swift_iban'] ?? null,
                $z['swift_bic'] ?? null,
                $z['swift_banka'] ?? null,
                $z['swift_adresa'] ?? null,
                $z['swift_prijemce'] ?? null,
                $z['pausalni_limit'] ?? null,
                $z['pausalni_dan'] ?? null
            ]);
            $vlozeno++;
        }

        echo "<div class='success'>Vloženo zaměstnanců: <strong>{$vlozeno}</strong></div>";

        echo "<div class='success'>";
        echo "<strong>IMPORT DOKONČEN!</strong><br><br>";
        echo "<a href='psa-kalkulator.php' class='btn'>Otevřít PSA Kalkulátor</a>";
        echo "</div>";

    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>VLOŽIT ZAMĚSTNANCE</button>";
        echo "</form>";
    }

    // Aktuální stav v DB
    $pocetDb = $pdo->query("SELECT COUNT(*) FROM psa_zamestnanci")->fetchColumn();
    echo "<div class='info'>Aktuálně v databázi: <strong>{$pocetDb}</strong> zaměstnanců</div>";

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='psa_vytvorit_tabulky.php' class='btn'>Zpět na vytvoření tabulek</a>";
echo "<a href='admin.php' class='btn'>Zpět do Admin</a>";

echo "</div></body></html>";
?>
