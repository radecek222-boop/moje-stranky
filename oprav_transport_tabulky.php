<?php
/**
 * Oprava Transport tabulek - vytvori vsechny potrebne tabulky
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Oprava Transport</title>
<style>body{font-family:sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
.box{background:#fff;padding:20px;border-radius:8px;margin:10px 0;}
.ok{color:#155724;background:#d4edda;padding:10px;border-radius:4px;margin:5px 0;}
.err{color:#721c24;background:#f8d7da;padding:10px;border-radius:4px;margin:5px 0;}
.btn{background:#333;color:#fff;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:10px 5px 10px 0;}
</style></head><body><div class='box'>";

try {
    $pdo = getDbConnection();
    echo "<h1>Oprava Transport Tabulek</h1>";

    // 1. Vytvorit wgs_transport_akce pokud neexistuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_akce'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("CREATE TABLE wgs_transport_akce (
            event_id INT PRIMARY KEY AUTO_INCREMENT,
            nazev VARCHAR(255) NOT NULL,
            datum_od DATE DEFAULT NULL,
            datum_do DATE DEFAULT NULL,
            popis TEXT DEFAULT NULL,
            vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_datum (datum_od, datum_do)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        echo "<div class='ok'>Vytvorena tabulka wgs_transport_akce</div>";
    } else {
        echo "<div class='ok'>Tabulka wgs_transport_akce existuje</div>";
    }

    // 2. Vytvorit wgs_transport_ridici pokud neexistuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_ridici'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("CREATE TABLE wgs_transport_ridici (
            ridic_id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            jmeno VARCHAR(100) NOT NULL,
            telefon VARCHAR(50) DEFAULT NULL,
            auto VARCHAR(100) DEFAULT NULL,
            spz VARCHAR(20) DEFAULT NULL,
            poznamka VARCHAR(255) DEFAULT NULL,
            vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event (event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        echo "<div class='ok'>Vytvorena tabulka wgs_transport_ridici</div>";
    } else {
        echo "<div class='ok'>Tabulka wgs_transport_ridici existuje</div>";
    }

    // 3. Zkontrolovat wgs_transport_events
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_transport_events'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("CREATE TABLE wgs_transport_events (
            event_id INT PRIMARY KEY AUTO_INCREMENT,
            parent_event_id INT DEFAULT NULL,
            jmeno_prijmeni VARCHAR(255) NOT NULL,
            cas TIME NOT NULL,
            cislo_letu VARCHAR(50) DEFAULT NULL,
            destinace VARCHAR(255) DEFAULT NULL,
            cas_priletu TIME DEFAULT NULL,
            telefon VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            ridic_id INT DEFAULT NULL,
            stav ENUM('wait', 'onway', 'drop') DEFAULT 'wait',
            poznamka TEXT DEFAULT NULL,
            datum DATE NOT NULL,
            cas_zmeny_stavu DATETIME DEFAULT NULL,
            vytvoreno TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            aktualizovano TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_parent (parent_event_id),
            INDEX idx_datum (datum),
            INDEX idx_stav (stav)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci");
        echo "<div class='ok'>Vytvorena tabulka wgs_transport_events</div>";
    } else {
        echo "<div class='ok'>Tabulka wgs_transport_events existuje</div>";

        // Pridat chybejici sloupce
        $sloupce = ['parent_event_id', 'ridic_id', 'poznamka', 'datum', 'cas_zmeny_stavu'];
        foreach ($sloupce as $sloupec) {
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE '{$sloupec}'");
            if ($stmt->rowCount() === 0) {
                switch ($sloupec) {
                    case 'parent_event_id':
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN parent_event_id INT DEFAULT NULL AFTER event_id");
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD INDEX idx_parent (parent_event_id)");
                        break;
                    case 'ridic_id':
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN ridic_id INT DEFAULT NULL AFTER email");
                        break;
                    case 'poznamka':
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN poznamka TEXT DEFAULT NULL AFTER stav");
                        break;
                    case 'datum':
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN datum DATE NOT NULL DEFAULT (CURDATE()) AFTER poznamka");
                        break;
                    case 'cas_zmeny_stavu':
                        $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN cas_zmeny_stavu DATETIME DEFAULT NULL AFTER datum");
                        break;
                }
                echo "<div class='ok'>Pridan sloupec {$sloupec}</div>";
            }
        }
    }

    echo "<h2>Hotovo!</h2>";
    echo "<a href='admin.php?tab=transport' class='btn'>Prejit na Transport</a>";

} catch (Exception $e) {
    echo "<div class='err'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
