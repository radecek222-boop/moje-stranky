<?php
/**
 * Migrace: Přidání sloupců pro admin aktuality
 *
 * Tento skript BEZPEČNĚ přidá sloupce pro sledování admin aktualit.
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
    <title>Migrace: Sloupce pro admin aktuality</title>
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
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px;
              overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupce pro admin aktuality</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_natuzzi_aktuality LIKE 'created_by_admin'");
    $existujeCreatedByAdmin = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_natuzzi_aktuality LIKE 'upraveno_at'");
    $existujeUpravenoAt = $stmt->rowCount() > 0;

    if ($existujeCreatedByAdmin && $existujeUpravenoAt) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ UPOZORNĚNÍ:</strong> Všechny sloupce již existují. Migrace není potřeba.";
        echo "</div>";
        echo "<a href='aktuality.php' class='btn'>➡️ Přejít na aktuality</a>";
        echo "<a href='nova_aktualita.php' class='btn'>➕ Vytvořit novou aktualitu</a>";
        echo "</div></body></html>";
        exit;
    }

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $migrated = false;

            // Přidat sloupec created_by_admin
            if (!$existujeCreatedByAdmin) {
                $pdo->exec("
                    ALTER TABLE wgs_natuzzi_aktuality
                    ADD COLUMN created_by_admin BOOLEAN DEFAULT FALSE
                    COMMENT 'Byla aktualita vytvořena ručně adminem?'
                ");
                echo "<div class='success'>Přidán sloupec <code>created_by_admin</code></div>";
                $migrated = true;
            }

            // Přidat sloupec upraveno_at
            if (!$existujeUpravenoAt) {
                $pdo->exec("
                    ALTER TABLE wgs_natuzzi_aktuality
                    ADD COLUMN upraveno_at TIMESTAMP NULL DEFAULT NULL
                    COMMENT 'Kdy byl článek naposledy upraven'
                ");
                echo "<div class='success'>Přidán sloupec <code>upraveno_at</code></div>";
                $migrated = true;
            }

            $pdo->commit();

            if ($migrated) {
                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong>";
                echo "</div>";

                echo "<h2>Přidané sloupce:</h2>";
                echo "<ul>";
                if (!$existujeCreatedByAdmin) {
                    echo "<li><code>created_by_admin</code> BOOLEAN - Označuje admin vytvořené aktuality</li>";
                }
                if (!$existujeUpravenoAt) {
                    echo "<li><code>upraveno_at</code> TIMESTAMP - Čas poslední úpravy</li>";
                }
                echo "</ul>";

                echo "<a href='nova_aktualita.php' class='btn'>➕ Vytvořit novou aktualitu</a>";
                echo "<a href='aktuality.php' class='btn'>➡️ Přejít na aktuality</a>";
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<h2>Chybějící sloupce:</h2>";
        echo "<ul>";
        if (!$existujeCreatedByAdmin) {
            echo "<li><code>created_by_admin</code> BOOLEAN - Označuje admin vytvořené aktuality</li>";
        }
        if (!$existujeUpravenoAt) {
            echo "<li><code>upraveno_at</code> TIMESTAMP - Čas poslední úpravy</li>";
        }
        echo "</ul>";

        echo "<div class='info'>";
        echo "<strong>ℹ️ INFO:</strong> Klikněte na tlačítko níže pro spuštění migrace.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
