<?php
/**
 * Migrace: Přidání sloupce je_odlozena do tabulky wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá boolean sloupec je_odlozena (1 = odložená reklamace).
 * Lze spustit vícekrát - pokud sloupec existuje, nic se nestane.
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
    <title>Migrace: Přidání sloupce je_odlozena</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 900px; margin: 50px auto; padding: 20px;
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
               border-radius: 5px; margin: 10px 5px 10px 0;
               cursor: pointer; border: none; font-size: 1rem; }
        .btn:hover { background: #111; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px;
              font-size: 0.85rem; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce je_odlozena</h1>";
    echo "<div class='info'><strong>Účel:</strong> Přidá boolean sloupec <code>je_odlozena</code> do tabulky <code>wgs_reklamace</code>.<br>
          Hodnota 1 = reklamace je označena jako odložená (admin ji ručně odloží v detailu).</div>";

    // Kontrola zda sloupec již existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'je_odlozena'");
    $existuje = $stmt->fetch();

    if ($existuje) {
        echo "<div class='success'><strong>Sloupec <code>je_odlozena</code> již existuje.</strong> Migrace není potřeba.</div>";
        echo "<div class='info'>Aktuální definice sloupce:<pre>" . htmlspecialchars(json_encode($existuje, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></div>";
    } else {
        echo "<div class='warning'><strong>Sloupec <code>je_odlozena</code> NEEXISTUJE.</strong> Bude přidán po kliknutí na tlačítko.</div>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->exec("
                ALTER TABLE wgs_reklamace
                ADD COLUMN je_odlozena TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Příznak zda je reklamace odložena (1 = odložena, 0 = aktivní)'
            ");

            // Kontrola výsledku
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'je_odlozena'");
            $overeni = $stmt->fetch();

            if ($overeni) {
                echo "<div class='success'>
                    <strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>
                    Sloupec <code>je_odlozena</code> byl přidán do tabulky <code>wgs_reklamace</code>.<br>
                    Výchozí hodnota: 0 (všechny existující záznamy nejsou odloženy).
                </div>";
            } else {
                echo "<div class='error'><strong>CHYBA:</strong> Sloupec nebyl přidán.</div>";
            }
        } else {
            echo "<pre>ALTER TABLE wgs_reklamace
ADD COLUMN je_odlozena TINYINT(1) NOT NULL DEFAULT 0;</pre>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
