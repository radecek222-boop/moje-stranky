<?php
/**
 * Migrace: Přidání sloupce datum_dokonceni do wgs_reklamace
 *
 * Tento sloupec ukládá datum a čas dokončení zakázky.
 * Používá se pro výpočet provizí techniků podle měsíce dokončení.
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
    <title>Migrace: datum_dokonceni</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupec datum_dokonceni</h1>";

    // Kontrola zda sloupec existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'datum_dokonceni'");
    $existuje = $stmt->rowCount() > 0;

    if ($existuje) {
        echo "<div class='success'>Sloupec <code>datum_dokonceni</code> již existuje.</div>";
    } else {
        echo "<div class='info'>Sloupec <code>datum_dokonceni</code> neexistuje. Bude přidán.</div>";
    }

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (!$existuje) {
            // Přidat sloupec
            $pdo->exec("ALTER TABLE wgs_reklamace ADD COLUMN datum_dokonceni DATETIME DEFAULT NULL AFTER updated_at");
            echo "<div class='success'>Sloupec <code>datum_dokonceni</code> byl přidán.</div>";

            // Naplnit existující hotové zakázky
            $stmt = $pdo->exec("
                UPDATE wgs_reklamace
                SET datum_dokonceni = updated_at
                WHERE stav = 'done' AND datum_dokonceni IS NULL
            ");
            echo "<div class='success'>Hotové zakázky byly aktualizovány (datum_dokonceni = updated_at).</div>";
        }

        // Přidat index pro rychlejší vyhledávání
        $stmtIndex = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_datum_dokonceni'");
        if ($stmtIndex->rowCount() === 0) {
            $pdo->exec("ALTER TABLE wgs_reklamace ADD INDEX idx_datum_dokonceni (datum_dokonceni)");
            echo "<div class='success'>Index <code>idx_datum_dokonceni</code> byl přidán.</div>";
        }

        echo "<div class='success'><strong>MIGRACE DOKONČENA</strong></div>";
        echo "<a href='admin.php' class='btn'>Zpět do Admin</a>";

    } else {
        echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background:#666;'>Zpět</a>";
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:12px;border-radius:5px;color:#721c24;'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
