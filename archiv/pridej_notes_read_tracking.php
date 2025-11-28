<?php
/**
 * Migrace: Přidání tracking systému pro přečtení poznámek
 *
 * Tento skript BEZPEČNĚ přidá tabulku pro sledování, kdo už poznámku přečetl.
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
    <title>Migrace: Notes Read Tracking</title>
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
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #111; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Notes Read Tracking</h1>";
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Kontrola existence tabulky wgs_notes_read
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_notes_read'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<div class='warning'>";
        echo "<strong>Tabulka 'wgs_notes_read' již existuje.</strong><br>";
        echo "Migrace byla pravděpodobně již provedena.";
        echo "</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_notes_read");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h2>Aktuální struktura tabulky:</h2>";
        echo "<pre>";
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
        }
        echo "</pre>";

    } else {
        echo "<div class='info'>";
        echo "<strong>Tabulka 'wgs_notes_read' neexistuje.</strong><br>";
        echo "Bude vytvořena nová tabulka pro tracking přečtení poznámek.";
        echo "</div>";

        echo "<h2>Co bude provedeno:</h2>";
        echo "<pre>";
        echo "CREATE TABLE wgs_notes_read (\n";
        echo "  id INT AUTO_INCREMENT PRIMARY KEY,\n";
        echo "  note_id INT NOT NULL,\n";
        echo "  user_email VARCHAR(255) NOT NULL,\n";
        echo "  read_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
        echo "  UNIQUE KEY unique_read (note_id, user_email),\n";
        echo "  FOREIGN KEY (note_id) REFERENCES wgs_notes(id) ON DELETE CASCADE\n";
        echo ");\n";
        echo "</pre>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Vytvoření tabulky wgs_notes_read
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS wgs_notes_read (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        note_id INT NOT NULL,
                        user_email VARCHAR(255) NOT NULL,
                        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_read (note_id, user_email),
                        FOREIGN KEY (note_id) REFERENCES wgs_notes(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Tabulka 'wgs_notes_read' byla vytvořena.<br>";
                echo "Nyní lze trackovat, kdo už poznámku přečetl.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>STRUKTURA TABULKY:</strong><br>";
                echo "- <code>id</code>: Auto-increment primární klíč<br>";
                echo "- <code>note_id</code>: ID poznámky (FK na wgs_notes)<br>";
                echo "- <code>user_email</code>: Email uživatele, který poznámku přečetl<br>";
                echo "- <code>read_at</code>: Čas přečtení<br>";
                echo "- <code>UNIQUE KEY</code>: Jeden uživatel může poznámku přečíst pouze jednou<br>";
                echo "- <code>ON DELETE CASCADE</code>: Při smazání poznámky se smažou i read záznamy";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    echo "<hr style='margin: 30px 0;'>";
    echo "<a href='admin.php' class='btn' style='background: #666;'>← Zpět na Admin</a>";
    echo "<a href='vsechny_tabulky.php' class='btn' style='background: #666;'>Zobrazit všechny tabulky</a>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
