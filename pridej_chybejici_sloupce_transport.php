<?php
/**
 * Migrace: Pridani chybejicich sloupcu do Transport Events tabulek
 *
 * Spustte tento skript pokud tabulky jiz existuji ale chybi nove sloupce
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Pridani chybejicich sloupcu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        pre { background: #1a1a1a; color: #39ff14; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();
    echo "<h1>Pridani chybejicich sloupcu do Transport tabulek</h1>";

    // Zmeny pro wgs_transport_events
    $zmeny = [];

    // Kontrola zda existuje sloupec parent_event_id
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'parent_event_id'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Pridani sloupce parent_event_id',
            'sql' => "ALTER TABLE wgs_transport_events ADD COLUMN parent_event_id INT DEFAULT NULL COMMENT 'ID nadrazeneho eventu' AFTER event_id"
        ];
        $zmeny[] = [
            'popis' => 'Pridani indexu idx_parent',
            'sql' => "ALTER TABLE wgs_transport_events ADD INDEX idx_parent (parent_event_id)"
        ];
    }

    // Kontrola zda existuje sloupec ridic_id
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'ridic_id'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Pridani sloupce ridic_id',
            'sql' => "ALTER TABLE wgs_transport_events ADD COLUMN ridic_id INT DEFAULT NULL COMMENT 'ID ridice' AFTER email"
        ];
        $zmeny[] = [
            'popis' => 'Pridani indexu idx_ridic',
            'sql' => "ALTER TABLE wgs_transport_events ADD INDEX idx_ridic (ridic_id)"
        ];
    }

    // Kontrola zda existuje sloupec poznamka
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'poznamka'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Pridani sloupce poznamka',
            'sql' => "ALTER TABLE wgs_transport_events ADD COLUMN poznamka TEXT DEFAULT NULL COMMENT 'Poznamka' AFTER stav"
        ];
    }

    // Kontrola zda existuje sloupec datum
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'datum'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Pridani sloupce datum',
            'sql' => "ALTER TABLE wgs_transport_events ADD COLUMN datum DATE NOT NULL DEFAULT CURDATE() COMMENT 'Datum transportu' AFTER poznamka"
        ];
        $zmeny[] = [
            'popis' => 'Pridani indexu idx_datum',
            'sql' => "ALTER TABLE wgs_transport_events ADD INDEX idx_datum (datum)"
        ];
    }

    // Kontrola zda existuje sloupec cas_zmeny_stavu
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'cas_zmeny_stavu'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Pridani sloupce cas_zmeny_stavu',
            'sql' => "ALTER TABLE wgs_transport_events ADD COLUMN cas_zmeny_stavu DATETIME DEFAULT NULL COMMENT 'Cas posledni zmeny stavu' AFTER datum"
        ];
    }

    // Kontrola zda existuje tabulka wgs_transport_akce
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_akce'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Vytvoreni tabulky wgs_transport_akce',
            'sql' => "CREATE TABLE wgs_transport_akce (
                event_id INT PRIMARY KEY AUTO_INCREMENT,
                nazev VARCHAR(255) NOT NULL COMMENT 'Nazev eventu',
                datum_od DATE DEFAULT NULL,
                datum_do DATE DEFAULT NULL,
                popis TEXT DEFAULT NULL,
                vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_datum (datum_od, datum_do)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci"
        ];
    }

    // Kontrola zda existuje tabulka wgs_transport_ridici
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_ridici'");
    if ($stmt->rowCount() === 0) {
        $zmeny[] = [
            'popis' => 'Vytvoreni tabulky wgs_transport_ridici',
            'sql' => "CREATE TABLE wgs_transport_ridici (
                ridic_id INT PRIMARY KEY AUTO_INCREMENT,
                event_id INT NOT NULL COMMENT 'ID eventu',
                jmeno VARCHAR(100) NOT NULL COMMENT 'Jmeno ridice',
                telefon VARCHAR(50) DEFAULT NULL,
                auto VARCHAR(100) DEFAULT NULL COMMENT 'Typ auta',
                spz VARCHAR(20) DEFAULT NULL COMMENT 'SPZ vozidla',
                poznamka VARCHAR(255) DEFAULT NULL,
                vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci"
        ];
    }

    if (count($zmeny) === 0) {
        echo "<div class='success'><strong>VSECHNY SLOUPCE A TABULKY JIZ EXISTUJI</strong></div>";
        echo "<a href='admin.php?tab=transport' class='btn'>Zpet na Transport</a>";
    } else {
        echo "<div class='info'>Nalezeno <strong>" . count($zmeny) . "</strong> zmen k provedeni:</div>";

        echo "<ul>";
        foreach ($zmeny as $z) {
            echo "<li>" . htmlspecialchars($z['popis']) . "</li>";
        }
        echo "</ul>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<h2>Provadim zmeny...</h2>";

            foreach ($zmeny as $z) {
                try {
                    $pdo->exec($z['sql']);
                    echo "<div class='success'>" . htmlspecialchars($z['popis']) . " - OK</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'>" . htmlspecialchars($z['popis']) . " - CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }

            echo "<div class='success'><strong>MIGRACE DOKONCENA</strong></div>";
            echo "<a href='admin.php?tab=transport' class='btn'>Zpet na Transport</a>";
        } else {
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php?tab=transport' class='btn' style='background:#666'>Zrusit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
