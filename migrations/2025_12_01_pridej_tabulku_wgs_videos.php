<?php
/**
 * Migrace: Přidání tabulky wgs_videos pro archiv videí zakázek
 *
 * Tento skript bezpečně vytvoří tabulku pro ukládání videí a připraví složku
 * uploads/videos. Je idempotentní a lze jej spustit opakovaně.
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Tabulka wgs_videos</title>
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
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Vytvoření tabulky wgs_videos</h1>";

    // Kontrola zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_videos'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<div class='info'>";
        echo "Tabulka <code>wgs_videos</code> již existuje v databázi.";
        echo "</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_videos");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Aktuální struktura:</h3>";
        echo "<pre>";
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
        }
        echo "</pre>";
    } else {
        echo "<div class='info'>";
        echo "Tabulka <code>wgs_videos</code> neexistuje. Připraveno k vytvoření.";
        echo "</div>";

        // Vytvořit tabulku pokud je execute=1
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // SQL pro vytvoření tabulky
                $sql = "
                CREATE TABLE wgs_videos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    claim_id INT NOT NULL COMMENT 'ID zakázky (wgs_reklamace.id)',
                    video_name VARCHAR(255) NOT NULL COMMENT 'Název videa',
                    video_path VARCHAR(500) NOT NULL COMMENT 'Cesta k video souboru',
                    file_size BIGINT NOT NULL COMMENT 'Velikost souboru v bytech',
                    duration INT DEFAULT NULL COMMENT 'Délka videa v sekundách',
                    thumbnail_path VARCHAR(500) DEFAULT NULL COMMENT 'Cesta k náhledovému obrázku',
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum nahrání',
                    uploaded_by INT DEFAULT NULL COMMENT 'ID uživatele který nahrál (wgs_users.user_id)',

                    INDEX idx_claim_id (claim_id),
                    INDEX idx_uploaded_at (uploaded_at),

                    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='Video archiv zakázek - nahraná videa pro jednotlivé zakázky';
                ";

                $pdo->exec($sql);
                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Tabulka <code>wgs_videos</code> byla úspěšně vytvořena.<br><br>";
                echo "<strong>Struktura tabulky:</strong>";
                echo "<pre>";
                echo "id              INT             Auto increment primary key\n";
                echo "claim_id        INT             ID zakázky (foreign key)\n";
                echo "video_name      VARCHAR(255)    Název videa\n";
                echo "video_path      VARCHAR(500)    Cesta k video souboru\n";
                echo "file_size       BIGINT          Velikost v bytech\n";
                echo "duration        INT             Délka videa (sekundy)\n";
                echo "thumbnail_path  VARCHAR(500)    Náhledový obrázek\n";
                echo "uploaded_at     DATETIME        Datum nahrání\n";
                echo "uploaded_by     INT             ID uživatele\n";
                echo "</pre>";
                echo "</div>";

                // Vytvořit složku pro videa
                $videoDir = __DIR__ . '/../uploads/videos';
                if (!is_dir($videoDir)) {
                    mkdir($videoDir, 0755, true);
                    echo "<div class='success'>";
                    echo "Složka <code>/uploads/videos/</code> byla vytvořena.";
                    echo "</div>";
                } else {
                    echo "<div class='info'>";
                    echo "Složka <code>/uploads/videos/</code> již existuje.";
                    echo "</div>";
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Zobrazit tlačítko pro spuštění
            echo "<h3>SQL příkaz k provedení:</h3>";
            echo "<pre>";
            echo "CREATE TABLE wgs_videos (\n";
            echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
            echo "    claim_id INT NOT NULL,\n";
            echo "    video_name VARCHAR(255) NOT NULL,\n";
            echo "    video_path VARCHAR(500) NOT NULL,\n";
            echo "    file_size BIGINT NOT NULL,\n";
            echo "    duration INT DEFAULT NULL,\n";
            echo "    thumbnail_path VARCHAR(500) DEFAULT NULL,\n";
            echo "    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
            echo "    uploaded_by INT DEFAULT NULL,\n";
            echo "    INDEX idx_claim_id (claim_id),\n";
            echo "    INDEX idx_uploaded_at (uploaded_at),\n";
            echo "    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE\n";
            echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
            echo "</pre>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='../admin.php' class='btn' style='background:#666;'>Zpět do admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
