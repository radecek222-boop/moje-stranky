<?php
/**
 * Migrace: Vytvoření tabulky wgs_transport_events
 *
 * Tento skript BEZPEČNĚ vytvoří tabulku pro transportní eventy.
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Transport Events</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
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
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #1a1a1a; color: #39ff14; padding: 15px;
              border-radius: 5px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Transport Events</h1>";

    // Kontrola zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_events'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='warning'>";
        echo "<strong>TABULKA JIZ EXISTUJE</strong><br>";
        echo "Tabulka <code>wgs_transport_events</code> jiz byla vytvorena.";
        echo "</div>";

        // Zobrazit strukturu
        echo "<h3>Aktualni struktura:</h3>";
        $stmt = $pdo->query("DESCRIBE wgs_transport_events");
        echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíc</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_transport_events");
        $pocet = $stmt->fetchColumn();
        echo "<div class='info'>Pocet zaznamu: <strong>{$pocet}</strong></div>";

    } else {
        echo "<div class='info'><strong>KONTROLA...</strong> Tabulka neexistuje, bude vytvorena.</div>";

        // SQL pro vytvoření tabulky
        $sql = "CREATE TABLE wgs_transport_events (
            event_id INT PRIMARY KEY AUTO_INCREMENT,
            jmeno_prijmeni VARCHAR(255) NOT NULL COMMENT 'Jmeno a prijmeni pasazera',
            cas TIME NOT NULL COMMENT 'Cas transportu',
            cislo_letu VARCHAR(50) DEFAULT NULL COMMENT 'Cislo letu',
            destinace VARCHAR(255) DEFAULT NULL COMMENT 'Destinace (odkud/kam)',
            cas_priletu TIME DEFAULT NULL COMMENT 'Cas priletu',
            telefon VARCHAR(50) DEFAULT NULL COMMENT 'Telefonni cislo',
            email VARCHAR(255) DEFAULT NULL COMMENT 'Email',
            ridic VARCHAR(100) DEFAULT NULL COMMENT 'Jmeno ridice',
            stav ENUM('wait', 'onway', 'drop') DEFAULT 'wait' COMMENT 'Stav: wait=ceka, onway=na ceste, drop=dorucen',
            datum DATE NOT NULL COMMENT 'Datum transportu',
            poznamka TEXT DEFAULT NULL COMMENT 'Poznamka k transportu',
            cas_zmeny_stavu DATETIME DEFAULT NULL COMMENT 'Cas posledni zmeny stavu',
            vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_datum (datum),
            INDEX idx_stav (stav),
            INDEX idx_ridic (ridic),
            INDEX idx_datum_cas (datum, cas)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
        COMMENT='Transportni eventy pro admin panel'";

        echo "<h3>SQL prikaz:</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                $pdo->exec($sql);
                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br>";
                echo "Tabulka <code>wgs_transport_events</code> byla vytvorena.";
                echo "</div>";

                // Zobrazit strukturu
                echo "<h3>Vytvorena struktura:</h3>";
                $stmt = $pdo->query("DESCRIBE wgs_transport_events");
                echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíc</th><th>Default</th></tr>";
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<h3>Struktura tabulky:</h3>";
            echo "<table>";
            echo "<tr><th>Sloupec</th><th>Typ</th><th>Popis</th></tr>";
            echo "<tr><td>event_id</td><td>INT AUTO_INCREMENT</td><td>Primarni klic</td></tr>";
            echo "<tr><td>jmeno_prijmeni</td><td>VARCHAR(255)</td><td>Jmeno a prijmeni pasazera</td></tr>";
            echo "<tr><td>cas</td><td>TIME</td><td>Cas transportu</td></tr>";
            echo "<tr><td>cislo_letu</td><td>VARCHAR(50)</td><td>Cislo letu</td></tr>";
            echo "<tr><td>destinace</td><td>VARCHAR(255)</td><td>Destinace (odkud/kam)</td></tr>";
            echo "<tr><td>cas_priletu</td><td>TIME</td><td>Cas priletu</td></tr>";
            echo "<tr><td>telefon</td><td>VARCHAR(50)</td><td>Telefonni cislo</td></tr>";
            echo "<tr><td>email</td><td>VARCHAR(255)</td><td>Email</td></tr>";
            echo "<tr><td>ridic</td><td>VARCHAR(100)</td><td>Jmeno ridice</td></tr>";
            echo "<tr><td>stav</td><td>ENUM('wait','onway','drop')</td><td>Stav transportu</td></tr>";
            echo "<tr><td>datum</td><td>DATE</td><td>Datum transportu</td></tr>";
            echo "<tr><td>poznamka</td><td>TEXT</td><td>Poznamka k transportu</td></tr>";
            echo "<tr><td>cas_zmeny_stavu</td><td>DATETIME</td><td>Cas posledni zmeny stavu</td></tr>";
            echo "<tr><td>vytvoreno</td><td>TIMESTAMP</td><td>Cas vytvoreni zaznamu</td></tr>";
            echo "<tr><td>aktualizovano</td><td>TIMESTAMP</td><td>Cas posledni aktualizace</td></tr>";
            echo "</table>";

            echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php' class='btn'>Zpet do admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn'>Zpet do admin</a>";
echo "</div></body></html>";
?>
