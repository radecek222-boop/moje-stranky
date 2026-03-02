<?php
/**
 * Migrace: Vytvoření tabulky wgs_hry_logy_aktivity
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro logování aktivity
 * uživatelů v herní zóně. Lze spustit vícekrát – tabulka se nevytvoří
 * duplicitně (IF NOT EXISTS).
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola – pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Logy herní zóny</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb;
                   color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb;
                   color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; font-size: 1rem; }
        .btn:hover { background: #111; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
        th { background: #333; color: #fff; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Logy aktivity herní zóny</h1>";

    // Zkontrolovat jestli tabulka již existuje
    $stmtKontrola = $pdo->query("SHOW TABLES LIKE 'wgs_hry_logy_aktivity'");
    $tabulkaExistuje = $stmtKontrola->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'><strong>Tabulka <code>wgs_hry_logy_aktivity</code> již existuje.</strong></div>";

        // Zobrazit aktuální stav
        $stmtPocet = $pdo->query("SELECT COUNT(*) FROM wgs_hry_logy_aktivity");
        $pocetZaznamu = $stmtPocet->fetchColumn();
        echo "<div class='info'>Aktuálně v tabulce: <strong>{$pocetZaznamu} záznamů</strong></div>";

        $stmtStruktura = $pdo->query("DESCRIBE wgs_hry_logy_aktivity");
        $sloupce = $stmtStruktura->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Aktuální struktura tabulky</h2><table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Výchozí</th></tr>";
        foreach ($sloupce as $sl) {
            echo "<tr><td><code>{$sl['Field']}</code></td><td>{$sl['Type']}</td><td>{$sl['Null']}</td><td>" . ($sl['Default'] ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    }

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>Spouštím migraci...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Vytvořit tabulku
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS wgs_hry_logy_aktivity (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    user_id       VARCHAR(64)  NOT NULL,
                    username      VARCHAR(100) NOT NULL,
                    akce          ENUM('navstivil_zonu', 'spustil_hru') NOT NULL DEFAULT 'navstivil_zonu',
                    hra           VARCHAR(50)  NULL COMMENT 'Název hry (pouze pro akci spustil_hru)',
                    ip_adresa     VARCHAR(45)  NULL,
                    cas           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_cas     (cas),
                    INDEX idx_user    (user_id),
                    INDEX idx_hra     (hra),
                    INDEX idx_akce    (akce)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
                  COMMENT='Logy aktivity uživatelů v herní zóně'
            ");

            $pdo->commit();

            echo "<div class='success'>
                <strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>
                Tabulka <code>wgs_hry_logy_aktivity</code> byla vytvořena.
              </div>";

            // Zobrazit novou strukturu
            $stmtStruktura = $pdo->query("DESCRIBE wgs_hry_logy_aktivity");
            $sloupce = $stmtStruktura->fetchAll(PDO::FETCH_ASSOC);
            echo "<h2>Nová struktura tabulky</h2><table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Výchozí</th></tr>";
            foreach ($sloupce as $sl) {
                echo "<tr><td><code>{$sl['Field']}</code></td><td>{$sl['Type']}</td><td>{$sl['Null']}</td><td>" . ($sl['Default'] ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<div class='info'>
            <strong>Co bude provedeno:</strong><br>
            Vytvoří se tabulka <code>wgs_hry_logy_aktivity</code> s těmito sloupci:<br><br>
            <code>id</code> – primární klíč<br>
            <code>user_id</code> – ID uživatele<br>
            <code>username</code> – jméno uživatele<br>
            <code>akce</code> – navstivil_zonu / spustil_hru<br>
            <code>hra</code> – název hry (Tetris, Had, Prší, …)<br>
            <code>ip_adresa</code> – IP adresa návštěvníka<br>
            <code>cas</code> – čas záznamu (automaticky)
          </div>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='hry.php' class='btn' style='background:#666'>Zpět na herní zónu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
