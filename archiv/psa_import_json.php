<?php
/**
 * PSA Kalkulátor - Import dat z JSON do databáze
 *
 * Tento skript importuje existující data z psa-employees.json do SQL tabulek.
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
    <title>PSA Kalkulátor - Import z JSON</title>
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
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
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

echo "<h1>PSA Kalkulátor - Import z JSON</h1>";

$jsonPath = __DIR__ . '/data/psa-employees.json';

try {
    $pdo = getDbConnection();

    // Kontrola tabulek
    $stmt = $pdo->query("SHOW TABLES LIKE 'psa_zamestnanci'");
    if ($stmt->rowCount() === 0) {
        echo "<div class='error'>Tabulka psa_zamestnanci neexistuje! Nejprve spusťte <a href='psa_vytvorit_tabulky.php'>vytvoření tabulek</a>.</div>";
        echo "</div></body></html>";
        exit;
    }

    // Načíst JSON
    if (!file_exists($jsonPath)) {
        echo "<div class='error'>JSON soubor nenalezen: {$jsonPath}</div>";
        echo "</div></body></html>";
        exit;
    }

    $jsonData = json_decode(file_get_contents($jsonPath), true);
    if (!$jsonData) {
        echo "<div class='error'>Nelze načíst JSON soubor</div>";
        echo "</div></body></html>";
        exit;
    }

    // Zobrazit náhled dat
    echo "<h3>Data k importu:</h3>";

    $zamestnanci = $jsonData['employees'] ?? [];
    $obdobi = $jsonData['periods'] ?? [];

    echo "<div class='info'>";
    echo "Zaměstnanců v JSON: <strong>" . count($zamestnanci) . "</strong><br>";
    echo "Období v JSON: <strong>" . count($obdobi) . "</strong>";
    echo "</div>";

    // Tabulka zaměstnanců
    if (count($zamestnanci) > 0) {
        echo "<h4>Zaměstnanci:</h4>";
        echo "<table><tr><th>ID</th><th>Jméno</th><th>Účet</th><th>Banka</th><th>Typ</th></tr>";
        foreach ($zamestnanci as $z) {
            echo "<tr>";
            echo "<td>" . ($z['id'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($z['name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($z['account'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($z['bank'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($z['type'] ?? 'standard') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Spustit import
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>IMPORTUJI DATA...</strong></div>";

        $pdo->beginTransaction();

        try {
            $importovano = 0;
            $preskoceno = 0;

            // Import zaměstnanců
            $stmtInsert = $pdo->prepare("
                INSERT INTO psa_zamestnanci
                (id, jmeno, ucet, banka, typ, aktivni, poznamka,
                 swift_iban, swift_bic, swift_banka, swift_adresa, swift_prijemce,
                 pausalni_limit, pausalni_dan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                jmeno = VALUES(jmeno),
                ucet = VALUES(ucet),
                banka = VALUES(banka),
                typ = VALUES(typ),
                aktivni = VALUES(aktivni),
                poznamka = VALUES(poznamka),
                swift_iban = VALUES(swift_iban),
                swift_bic = VALUES(swift_bic),
                swift_banka = VALUES(swift_banka),
                swift_adresa = VALUES(swift_adresa),
                swift_prijemce = VALUES(swift_prijemce),
                pausalni_limit = VALUES(pausalni_limit),
                pausalni_dan = VALUES(pausalni_dan)
            ");

            foreach ($zamestnanci as $z) {
                $swift = $z['swiftData'] ?? null;
                $pausalni = $z['pausalni'] ?? null;

                $stmtInsert->execute([
                    $z['id'] ?? null,
                    $z['name'] ?? '',
                    $z['account'] ?? '',
                    $z['bank'] ?? '',
                    $z['type'] ?? 'standard',
                    ($z['active'] ?? true) ? 1 : 0,
                    $z['note'] ?? null,
                    $swift['iban'] ?? null,
                    $swift['swift'] ?? null,
                    $swift['bankName'] ?? null,
                    $swift['bankAddress'] ?? null,
                    $swift['beneficiary'] ?? null,
                    $pausalni['rate'] ?? null,
                    $pausalni['tax'] ?? null
                ]);

                $importovano++;
            }

            echo "<div class='success'>Importováno zaměstnanců: <strong>{$importovano}</strong></div>";

            // Import období
            $importObdobi = 0;
            $importDochazka = 0;

            foreach ($obdobi as $klic => $data) {
                // Parsovat klíč (YYYY-MM)
                list($rok, $mesic) = explode('-', $klic);

                // Vložit období
                $stmtObdobi = $pdo->prepare("
                    INSERT INTO psa_obdobi
                    (rok, mesic, celkem_hodin, celkem_vyplat, celkem_faktur, zisk,
                     marek_bonus, radek_bonus, holky_bonus, radek_celkem, premie_celkem)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    celkem_hodin = VALUES(celkem_hodin),
                    celkem_vyplat = VALUES(celkem_vyplat),
                    celkem_faktur = VALUES(celkem_faktur),
                    zisk = VALUES(zisk),
                    marek_bonus = VALUES(marek_bonus),
                    radek_bonus = VALUES(radek_bonus),
                    holky_bonus = VALUES(holky_bonus),
                    radek_celkem = VALUES(radek_celkem),
                    premie_celkem = VALUES(premie_celkem)
                ");

                $stmtObdobi->execute([
                    (int)$rok,
                    (int)$mesic,
                    $data['totalHours'] ?? 0,
                    $data['totalSalary'] ?? 0,
                    $data['totalInvoice'] ?? 0,
                    $data['profit'] ?? 0,
                    $data['marekBonus'] ?? 0,
                    $data['radekBonus'] ?? 0,
                    $data['girlsBonus'] ?? 0,
                    $data['radekTotal'] ?? 0,
                    $data['premieCelkem'] ?? 0
                ]);

                // Získat ID období
                $stmtGetId = $pdo->prepare("SELECT id FROM psa_obdobi WHERE rok = ? AND mesic = ?");
                $stmtGetId->execute([(int)$rok, (int)$mesic]);
                $obdobiId = $stmtGetId->fetchColumn();

                $importObdobi++;

                // Import docházky pro období
                if (!empty($data['employees'])) {
                    $stmtDochazka = $pdo->prepare("
                        INSERT INTO psa_dochazka
                        (obdobi_id, zamestnanec_id, hodiny, bonus, premie)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        hodiny = VALUES(hodiny),
                        bonus = VALUES(bonus),
                        premie = VALUES(premie)
                    ");

                    foreach ($data['employees'] as $emp) {
                        $stmtDochazka->execute([
                            $obdobiId,
                            $emp['id'],
                            $emp['hours'] ?? 0,
                            $emp['bonusAmount'] ?? 0,
                            $emp['premieCastka'] ?? 0
                        ]);
                        $importDochazka++;
                    }
                }
            }

            echo "<div class='success'>Importováno období: <strong>{$importObdobi}</strong></div>";
            echo "<div class='success'>Importováno docházek: <strong>{$importDochazka}</strong></div>";

            // Import konfigurace
            if (!empty($jsonData['config'])) {
                $cfg = $jsonData['config'];
                $stmtCfg = $pdo->prepare("
                    INSERT INTO psa_konfigurace (klic, hodnota) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE hodnota = VALUES(hodnota)
                ");

                if (isset($cfg['salaryRate'])) {
                    $stmtCfg->execute(['sazba_vyplata', $cfg['salaryRate']]);
                }
                if (isset($cfg['invoiceRate'])) {
                    $stmtCfg->execute(['sazba_faktura', $cfg['invoiceRate']]);
                }
                echo "<div class='success'>Konfigurace importována</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>IMPORT DOKONČEN!</strong><br><br>";
            echo "<a href='psa-kalkulator.php' class='btn'>Otevřít PSA Kalkulátor</a>";
            echo "</div>";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>SPUSTIT IMPORT</button>";
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
