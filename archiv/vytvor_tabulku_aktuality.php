<?php
/**
 * Migrace: Vytvoření tabulky pro denní aktuality Natuzzi
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku wgs_natuzzi_aktuality.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
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
    <title>Migrace: Tabulka Aktuality</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333333; border-bottom: 3px solid #333333;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Vytvoření tabulky pro Natuzzi Aktuality</h1>";

    // Kontrola před migrací
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmtCheck = $pdo->query("SHOW TABLES LIKE 'wgs_natuzzi_aktuality'");
    $tabulkaExistuje = $stmtCheck->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong> Tabulka <code>wgs_natuzzi_aktuality</code> již existuje.<br>";
        echo "Migrace nebude provedena.";
        echo "</div>";

        // Zobrazit strukturu existující tabulky
        $stmtDesc = $pdo->query("DESCRIBE wgs_natuzzi_aktuality");
        $sloupce = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Aktuální struktura tabulky:</h3>";
        echo "<pre>";
        foreach ($sloupce as $sloupec) {
            echo sprintf("%-30s %-20s %s\n",
                $sloupec['Field'],
                $sloupec['Type'],
                $sloupec['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
            );
        }
        echo "</pre>";

        // Zobrazit počet záznamů
        $stmtCount = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_natuzzi_aktuality");
        $pocet = $stmtCount->fetch(PDO::FETCH_ASSOC)['pocet'];
        echo "<div class='info'><strong>Počet záznamů:</strong> {$pocet}</div>";

    } else {
        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $migrationSuccess = false;
            $errorMessage = '';

            $pdo->beginTransaction();

            try {
                // Vytvoření tabulky
                $sqlCreateTable = "
                CREATE TABLE `wgs_natuzzi_aktuality` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `datum` DATE NOT NULL UNIQUE COMMENT 'Datum aktuality',
                    `svatek_cz` VARCHAR(100) NULL COMMENT 'Jméno svátku v ČR',
                    `komentar_dne` TEXT NULL COMMENT 'Komentář k dnešnímu dni',

                    -- Čeština
                    `obsah_cz` LONGTEXT NOT NULL COMMENT 'Kompletní obsah v češtině',

                    -- Angličtina
                    `obsah_en` LONGTEXT NOT NULL COMMENT 'Kompletní obsah v angličtině',

                    -- Italština
                    `obsah_it` LONGTEXT NOT NULL COMMENT 'Kompletní obsah v italštině',

                    -- Metadata
                    `zdroje_json` JSON NULL COMMENT 'JSON pole se zdroji informací',
                    `vygenerovano_ai` BOOLEAN DEFAULT TRUE COMMENT 'Generováno AI',

                    -- Časové razítko
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    INDEX `idx_datum` (`datum`),
                    INDEX `idx_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Denní automaticky generované aktuality o značce Natuzzi'
                ";

                $pdo->exec($sqlCreateTable);

                $pdo->commit();
                $migrationSuccess = true;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $errorMessage = $e->getMessage();
            }

            // Výstup MIMO transakci
            if ($migrationSuccess) {
                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Tabulka <code>wgs_natuzzi_aktuality</code> byla vytvořena.<br><br>";
                echo "<strong>Struktura tabulky:</strong>";
                echo "<ul>";
                echo "<li><code>id</code> - AUTO_INCREMENT primární klíč</li>";
                echo "<li><code>datum</code> - Datum aktuality (UNIQUE)</li>";
                echo "<li><code>svatek_cz</code> - Jméno svátku</li>";
                echo "<li><code>komentar_dne</code> - Komentář k dni</li>";
                echo "<li><code>obsah_cz</code> - Celý obsah v češtině</li>";
                echo "<li><code>obsah_en</code> - Celý obsah v angličtině</li>";
                echo "<li><code>obsah_it</code> - Celý obsah v italštině</li>";
                echo "<li><code>zdroje_json</code> - JSON se zdroji</li>";
                echo "<li><code>vygenerovano_ai</code> - Značka AI generování</li>";
                echo "<li><code>created_at</code> - Časové razítko vytvoření</li>";
                echo "<li><code>updated_at</code> - Časové razítko aktualizace</li>";
                echo "</ul>";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>📋 DALŠÍ KROKY:</strong><br>";
                echo "1. Spustit generátor obsahu: <code>api/generuj_aktuality.php</code><br>";
                echo "2. Cron job je již nastavený: každý den v 06:00<br>";
                echo "3. Zobrazit aktuality: <a href='aktuality.php'>aktuality.php</a>";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI VYTVÁŘENÍ TABULKY:</strong><br>";
                echo htmlspecialchars($errorMessage);
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>📋 CO BUDE PROVEDENO:</strong><br>";
            echo "Vytvoří se tabulka <code>wgs_natuzzi_aktuality</code> s následující strukturou:";
            echo "</div>";

            echo "<pre>";
            echo "CREATE TABLE wgs_natuzzi_aktuality (
    id               INT AUTO_INCREMENT PRIMARY KEY
    datum            DATE UNIQUE
    svatek_cz        VARCHAR(100)
    komentar_dne     TEXT
    obsah_cz         LONGTEXT (čeština)
    obsah_en         LONGTEXT (angličtina)
    obsah_it         LONGTEXT (italština)
    zdroje_json      JSON
    vygenerovano_ai  BOOLEAN
    created_at       TIMESTAMP
    updated_at       TIMESTAMP
)";
            echo "</pre>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<br><a href='admin.php' class='btn'>← Zpět do Admin panelu</a>";
echo "</div></body></html>";
?>
