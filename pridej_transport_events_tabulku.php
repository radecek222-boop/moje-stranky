<?php
/**
 * Migrace: Vytvoření tabulek pro Transport Events
 *
 * Tabulky:
 * - wgs_transport_akce - eventy (STVANICE 26, TECHMISSION, atd.)
 * - wgs_transport_ridici - řidiči pro každý event
 * - wgs_transport_events - jednotlivé transporty
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
        h2 { color: #555; margin-top: 2rem; }
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
              border-radius: 5px; overflow-x: auto; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.85rem; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Transport Events System</h1>";

    // SQL pro tabulky
    $tabulky = [
        'wgs_transport_akce' => "
            CREATE TABLE wgs_transport_akce (
                event_id INT PRIMARY KEY AUTO_INCREMENT,
                nazev VARCHAR(255) NOT NULL COMMENT 'Nazev eventu (STVANICE 26, TECHMISSION, atd.)',
                datum_od DATE DEFAULT NULL COMMENT 'Datum zacatku eventu',
                datum_do DATE DEFAULT NULL COMMENT 'Datum konce eventu',
                popis TEXT DEFAULT NULL COMMENT 'Popis eventu',
                vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_datum (datum_od, datum_do)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
            COMMENT='Transportni eventy/akce'
        ",

        'wgs_transport_ridici' => "
            CREATE TABLE wgs_transport_ridici (
                ridic_id INT PRIMARY KEY AUTO_INCREMENT,
                event_id INT NOT NULL COMMENT 'ID eventu',
                jmeno VARCHAR(100) NOT NULL COMMENT 'Jmeno ridice',
                telefon VARCHAR(50) DEFAULT NULL COMMENT 'Telefonni cislo',
                auto VARCHAR(100) DEFAULT NULL COMMENT 'Typ auta (MB V CLASS, atd.)',
                spz VARCHAR(20) DEFAULT NULL COMMENT 'SPZ vozidla',
                poznamka VARCHAR(255) DEFAULT NULL COMMENT 'Poznamka (STAND BY, atd.)',
                vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event (event_id),
                FOREIGN KEY (event_id) REFERENCES wgs_transport_akce(event_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
            COMMENT='Ridici pro transportni eventy'
        ",

        'wgs_transport_events' => "
            CREATE TABLE wgs_transport_events (
                event_id INT PRIMARY KEY AUTO_INCREMENT,
                parent_event_id INT DEFAULT NULL COMMENT 'ID nadrazeneho eventu',
                jmeno_prijmeni VARCHAR(255) NOT NULL COMMENT 'Jmeno a prijmeni pasazera',
                cas TIME NOT NULL COMMENT 'Cas transportu',
                cislo_letu VARCHAR(50) DEFAULT NULL COMMENT 'Cislo letu',
                destinace VARCHAR(255) DEFAULT NULL COMMENT 'Destinace (odkud/kam)',
                cas_priletu TIME DEFAULT NULL COMMENT 'Cas priletu',
                telefon VARCHAR(50) DEFAULT NULL COMMENT 'Telefonni cislo',
                email VARCHAR(255) DEFAULT NULL COMMENT 'Email',
                ridic_id INT DEFAULT NULL COMMENT 'ID ridice',
                stav ENUM('wait', 'onway', 'drop') DEFAULT 'wait' COMMENT 'Stav transportu',
                datum DATE NOT NULL COMMENT 'Datum transportu',
                poznamka TEXT DEFAULT NULL COMMENT 'Poznamka',
                cas_zmeny_stavu DATETIME DEFAULT NULL COMMENT 'Cas posledni zmeny stavu',
                vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_parent (parent_event_id),
                INDEX idx_datum (datum),
                INDEX idx_stav (stav),
                INDEX idx_ridic (ridic_id),
                INDEX idx_datum_cas (datum, cas),
                FOREIGN KEY (parent_event_id) REFERENCES wgs_transport_akce(event_id) ON DELETE CASCADE,
                FOREIGN KEY (ridic_id) REFERENCES wgs_transport_ridici(ridic_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
            COMMENT='Jednotlive transporty v ramci eventu'
        "
    ];

    // Kontrola existujících tabulek
    $existujici = [];
    $chybejici = [];

    foreach ($tabulky as $nazev => $sql) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$nazev}'");
        if ($stmt->rowCount() > 0) {
            $existujici[] = $nazev;
        } else {
            $chybejici[] = $nazev;
        }
    }

    if (count($chybejici) === 0) {
        echo "<div class='success'><strong>VSECHNY TABULKY JIZ EXISTUJI</strong></div>";

        // Zobrazit strukturu
        foreach ($existujici as $nazev) {
            echo "<h2>{$nazev}</h2>";
            $stmt = $pdo->query("DESCRIBE {$nazev}");
            echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klic</th><th>Default</th></tr>";
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
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$nazev}");
            echo "<p>Pocet zaznamu: <strong>" . $stmt->fetchColumn() . "</strong></p>";
        }

    } else {
        echo "<div class='info'><strong>KONTROLA...</strong></div>";
        echo "<p>Existujici tabulky: " . (count($existujici) > 0 ? implode(', ', $existujici) : 'zadne') . "</p>";
        echo "<p>Chybejici tabulky: <strong>" . implode(', ', $chybejici) . "</strong></p>";

        // Zobrazit SQL
        echo "<h2>SQL prikazy:</h2>";
        foreach ($chybejici as $nazev) {
            echo "<h3>{$nazev}</h3>";
            echo "<pre>" . htmlspecialchars($tabulky[$nazev]) . "</pre>";
        }

        // Spustit migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                foreach ($chybejici as $nazev) {
                    echo "<p>Vytvarim tabulku: {$nazev}...</p>";
                    $pdo->exec($tabulky[$nazev]);
                    echo "<div class='success'>Tabulka {$nazev} vytvorena.</div>";
                }

                $pdo->commit();

                echo "<div class='success'><strong>MIGRACE USPESNE DOKONCENA</strong></div>";

                // Přidat vzorový event "STVANICE 26"
                if (in_array('wgs_transport_akce', $chybejici)) {
                    echo "<div class='info'>Pridavam vzorovy event STVANICE 26...</div>";

                    $stmt = $pdo->prepare("
                        INSERT INTO wgs_transport_akce (nazev, popis)
                        VALUES ('STVANICE 26', 'Vzorovy event')
                    ");
                    $stmt->execute();
                    $eventId = $pdo->lastInsertId();

                    echo "<div class='success'>Vzorovy event STVANICE 26 vytvoren (ID: {$eventId})</div>";
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=transport' class='btn'>Prejit na Transport</a>";
echo "<a href='admin.php' class='btn'>Zpet do admin</a>";
echo "</div></body></html>";
?>
