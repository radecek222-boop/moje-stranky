<?php
/**
 * Migrace: Oprava chyb z error logu (2025-12-10)
 *
 * Tento skript opraví:
 * 1. wgs_pageviews.user_id - změna z INT na VARCHAR(50)
 * 2. wgs_supervisor_assignments - vytvoření chybějící tabulky
 *
 * Bezpečné spustit vícekrát - kontroluje stav před změnami.
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
    <title>Migrace: Oprava chyb z logu</title>
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
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px;
              border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava chyb z logu</h1>";
    echo "<p>Datum: " . date('Y-m-d H:i:s') . "</p>";

    // 1. Kontrola wgs_pageviews.user_id
    echo "<h2>1. Kontrola wgs_pageviews.user_id</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    $pageviewsExists = $stmt->rowCount() > 0;

    if ($pageviewsExists) {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_pageviews LIKE 'user_id'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($column) {
            $currentType = strtoupper($column['Type']);
            echo "<div class='info'><strong>Aktuální typ:</strong> <code>{$currentType}</code></div>";

            if (strpos($currentType, 'INT') !== false) {
                echo "<div class='warning'>Sloupec user_id je INT - potřebuje změnit na VARCHAR(50)</div>";

                if (isset($_GET['execute']) && $_GET['execute'] === '1') {
                    try {
                        $pdo->exec("ALTER TABLE wgs_pageviews MODIFY COLUMN user_id VARCHAR(50) NULL");
                        echo "<div class='success'>Sloupec user_id změněn na VARCHAR(50)</div>";
                    } catch (PDOException $e) {
                        echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            } else {
                echo "<div class='success'>Sloupec user_id je již VARCHAR - OK</div>";
            }
        } else {
            echo "<div class='warning'>Sloupec user_id neexistuje v tabulce</div>";
        }
    } else {
        echo "<div class='info'>Tabulka wgs_pageviews neexistuje - bude vytvořena automaticky při dalším pageview</div>";
    }

    // 2. Kontrola wgs_supervisor_assignments
    echo "<h2>2. Kontrola wgs_supervisor_assignments</h2>";

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_supervisor_assignments'");
    $supervisorExists = $stmt->rowCount() > 0;

    if ($supervisorExists) {
        echo "<div class='success'>Tabulka wgs_supervisor_assignments již existuje - OK</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_supervisor_assignments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table><tr><th>Sloupec</th><th>Typ</th><th>Null</th><th>Klíč</th></tr>";
        foreach ($columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>Tabulka wgs_supervisor_assignments neexistuje</div>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            try {
                $pdo->exec("
                    CREATE TABLE wgs_supervisor_assignments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        supervisor_user_id INT NOT NULL COMMENT 'ID supervizora (wgs_users.id)',
                        salesperson_user_id INT NOT NULL COMMENT 'ID prodejce pod supervizí (wgs_users.id)',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        created_by VARCHAR(50) NULL COMMENT 'Kdo přiřazení vytvořil',
                        UNIQUE KEY uk_supervisor_salesperson (supervisor_user_id, salesperson_user_id),
                        INDEX idx_supervisor (supervisor_user_id),
                        INDEX idx_salesperson (salesperson_user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    COMMENT='Přiřazení prodejců k supervizorům - supervizor vidí reklamace svých prodejců'
                ");
                echo "<div class='success'>Tabulka wgs_supervisor_assignments vytvořena</div>";
            } catch (PDOException $e) {
                echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    // 3. Shrnutí oprav v kódu
    echo "<h2>3. Opravy v kódu (již provedeny)</h2>";
    echo "<table>";
    echo "<tr><th>Soubor</th><th>Problém</th><th>Oprava</th></tr>";
    echo "<tr><td><code>app/controllers/save.php</code></td><td>stav = 'HOTOVO' (Data truncated)</td><td>stav = 'done'</td></tr>";
    echo "<tr><td><code>api/protokol_api.php</code></td><td>r.prodejce neexistuje</td><td>COALESCE(u.name, 'Neznámý')</td></tr>";
    echo "<tr><td><code>api/admin_api.php</code></td><td>r.prodejce neexistuje</td><td>COALESCE(u.name, 'Neznámý')</td></tr>";
    echo "<tr><td><code>api/track_pageview.php</code></td><td>user_id INT (CREATE TABLE)</td><td>user_id VARCHAR(50)</td></tr>";
    echo "</table>";

    // Tlačítko pro spuštění
    if (!isset($_GET['execute']) || $_GET['execute'] !== '1') {
        echo "<h2>Akce</h2>";
        echo "<p>Kliknutím na tlačítko provedete změny v databázi:</p>";
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>Zpět na admin</a>";
    } else {
        echo "<div class='success'><strong>Migrace dokončena!</strong></div>";
        echo "<a href='/admin.php' class='btn'>Zpět na admin</a>";
        echo "<a href='/api/debug_errors.php' class='btn' style='background: #666;'>Zkontrolovat error log</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>Kritická chyba:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
