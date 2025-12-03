<?php
/**
 * PSA Kalkulátor - Vytvoření databázových tabulek
 *
 * Tento skript vytvoří tabulky pro PSA kalkulátor a importuje data z JSON.
 * Můžete jej spustit vícekrát - existující tabulky nebudou přepsány.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>PSA Kalkulátor - Vytvoření tabulek</title>
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>PSA Kalkulátor - Vytvoření databázových tabulek</h1>";

try {
    $pdo = getDbConnection();

    // SQL pro vytvoření tabulek
    $sqlTabulky = "
    -- Tabulka zaměstnanců PSA
    CREATE TABLE IF NOT EXISTS psa_zamestnanci (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jmeno VARCHAR(100) NOT NULL,
        ucet VARCHAR(50) DEFAULT '',
        banka VARCHAR(20) DEFAULT '',
        typ ENUM('standard', 'swift', 'special', 'special2', 'pausalni', 'premie_polozka') DEFAULT 'standard',
        aktivni TINYINT(1) DEFAULT 1,
        poznamka TEXT DEFAULT NULL,
        swift_iban VARCHAR(50) DEFAULT NULL,
        swift_bic VARCHAR(20) DEFAULT NULL,
        swift_banka VARCHAR(100) DEFAULT NULL,
        swift_adresa VARCHAR(200) DEFAULT NULL,
        swift_prijemce VARCHAR(100) DEFAULT NULL,
        pausalni_limit DECIMAL(12,2) DEFAULT NULL,
        pausalni_dan DECIMAL(10,2) DEFAULT NULL,
        vytvoreno DATETIME DEFAULT CURRENT_TIMESTAMP,
        upraveno DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

    -- Tabulka období (měsíční docházky)
    CREATE TABLE IF NOT EXISTS psa_obdobi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rok YEAR NOT NULL,
        mesic TINYINT NOT NULL,
        celkem_hodin DECIMAL(10,2) DEFAULT 0,
        celkem_vyplat DECIMAL(12,2) DEFAULT 0,
        celkem_faktur DECIMAL(12,2) DEFAULT 0,
        zisk DECIMAL(12,2) DEFAULT 0,
        marek_bonus DECIMAL(10,2) DEFAULT 0,
        radek_bonus DECIMAL(10,2) DEFAULT 0,
        holky_bonus DECIMAL(10,2) DEFAULT 0,
        radek_celkem DECIMAL(10,2) DEFAULT 0,
        premie_celkem DECIMAL(10,2) DEFAULT 0,
        vytvoreno DATETIME DEFAULT CURRENT_TIMESTAMP,
        upraveno DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_obdobi (rok, mesic)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

    -- Tabulka docházky (hodiny zaměstnanců v období)
    CREATE TABLE IF NOT EXISTS psa_dochazka (
        id INT AUTO_INCREMENT PRIMARY KEY,
        obdobi_id INT NOT NULL,
        zamestnanec_id INT NOT NULL,
        hodiny DECIMAL(10,2) DEFAULT 0,
        bonus DECIMAL(10,2) DEFAULT 0,
        premie DECIMAL(10,2) DEFAULT 0,
        vytvoreno DATETIME DEFAULT CURRENT_TIMESTAMP,
        upraveno DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_dochazka (obdobi_id, zamestnanec_id),
        FOREIGN KEY (obdobi_id) REFERENCES psa_obdobi(id) ON DELETE CASCADE,
        FOREIGN KEY (zamestnanec_id) REFERENCES psa_zamestnanci(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

    -- Tabulka konfigurace PSA
    CREATE TABLE IF NOT EXISTS psa_konfigurace (
        klic VARCHAR(50) PRIMARY KEY,
        hodnota VARCHAR(255) NOT NULL,
        popis VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
    ";

    // Kontrola jestli tabulky existují
    echo "<div class='info'><strong>KONTROLA TABULEK...</strong></div>";

    $existujiciTabulky = [];
    $stmt = $pdo->query("SHOW TABLES LIKE 'psa_%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existujiciTabulky[] = $row[0];
    }

    if (count($existujiciTabulky) > 0) {
        echo "<div class='warning'>";
        echo "<strong>Nalezené PSA tabulky:</strong><br>";
        echo implode(', ', $existujiciTabulky);
        echo "</div>";
    }

    // Pokud je nastaveno ?execute=1, provést vytvoření
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>VYTVÁŘÍM TABULKY...</strong></div>";

        // Rozdělit SQL na jednotlivé příkazy
        $prikazy = array_filter(array_map('trim', explode(';', $sqlTabulky)));

        foreach ($prikazy as $prikaz) {
            if (empty($prikaz) || strpos($prikaz, '--') === 0) continue;

            try {
                $pdo->exec($prikaz);
                // Extrahovat název tabulky z CREATE TABLE
                if (preg_match('/CREATE TABLE.*?(\w+)\s*\(/i', $prikaz, $m)) {
                    echo "<div class='success'>Tabulka <strong>{$m[1]}</strong> vytvořena/ověřena</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }

        // Vložit výchozí konfiguraci
        $konfig = [
            ['sazba_vyplata', '150', 'Sazba výplaty Kč/hodina'],
            ['sazba_faktura', '250', 'Sazba fakturace Kč/hodina'],
            ['firma', 'White Glove Service', 'Název firmy'],
            ['mena', 'CZK', 'Měna']
        ];

        foreach ($konfig as $k) {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO psa_konfigurace (klic, hodnota, popis) VALUES (?, ?, ?)");
                $stmt->execute($k);
            } catch (PDOException $e) {
                // Ignorovat duplicity
            }
        }
        echo "<div class='success'>Výchozí konfigurace vložena</div>";

        echo "<div class='success'>";
        echo "<strong>TABULKY ÚSPĚŠNĚ VYTVOŘENY!</strong><br><br>";
        echo "Nyní můžete <a href='psa_import_json.php' class='btn'>Importovat data z JSON</a>";
        echo "</div>";

    } else {
        // Náhled
        echo "<h3>SQL příkazy k provedení:</h3>";
        echo "<pre>" . htmlspecialchars($sqlTabulky) . "</pre>";

        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>VYTVOŘIT TABULKY</button>";
        echo "</form>";
    }

    // Zobrazit aktuální stav
    if (in_array('psa_zamestnanci', $existujiciTabulky)) {
        $pocet = $pdo->query("SELECT COUNT(*) FROM psa_zamestnanci")->fetchColumn();
        echo "<div class='info'>Aktuálně v databázi: <strong>{$pocet}</strong> zaměstnanců</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='psa-kalkulator.php' class='btn'>Zpět na PSA Kalkulátor</a>";
echo "<a href='admin.php' class='btn'>Zpět do Admin</a>";

echo "</div></body></html>";
?>
