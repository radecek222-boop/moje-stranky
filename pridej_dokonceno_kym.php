<?php
/**
 * Migrace: Přidání sloupce dokonceno_kym do wgs_reklamace
 *
 * Tento sloupec ukládá ID uživatele (z wgs_users), který zakázku dokončil.
 * Provize se počítají podle tohoto sloupce - kdo odeslal = tomu se počítá.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: dokonceno_kym</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupec dokonceno_kym</h1>";
    echo "<div class='info'>Tento sloupec ukládá ID technika, který zakázku dokončil (odeslal protokol). Provize se počítají podle tohoto sloupce.</div>";

    // Kontrola zda sloupec existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'dokonceno_kym'");
    $existuje = $stmt->rowCount() > 0;

    if ($existuje) {
        echo "<div class='success'>Sloupec <code>dokonceno_kym</code> již existuje.</div>";
    } else {
        echo "<div class='info'>Sloupec <code>dokonceno_kym</code> neexistuje. Bude přidán.</div>";
    }

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (!$existuje) {
            // Přidat sloupec
            $pdo->exec("ALTER TABLE wgs_reklamace ADD COLUMN dokonceno_kym INT DEFAULT NULL AFTER datum_dokonceni");
            echo "<div class='success'>Sloupec <code>dokonceno_kym</code> byl přidán.</div>";

            // Naplnit existující hotové zakázky podle assigned_to (jako fallback)
            $stmt = $pdo->exec("
                UPDATE wgs_reklamace
                SET dokonceno_kym = assigned_to
                WHERE stav = 'done' AND dokonceno_kym IS NULL AND assigned_to IS NOT NULL
            ");
            echo "<div class='success'>Hotové zakázky aktualizovány (dokonceno_kym = assigned_to jako fallback).</div>";
        }

        // Přidat index
        $stmtIndex = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_dokonceno_kym'");
        if ($stmtIndex->rowCount() === 0) {
            $pdo->exec("ALTER TABLE wgs_reklamace ADD INDEX idx_dokonceno_kym (dokonceno_kym)");
            echo "<div class='success'>Index <code>idx_dokonceno_kym</code> byl přidán.</div>";
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
