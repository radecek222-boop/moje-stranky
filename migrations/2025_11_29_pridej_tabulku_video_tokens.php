<?php
/**
 * Migrace: Pridani tabulky wgs_video_tokens pro tokeny ke stazeni videi
 *
 * Tento skript BEZPECNE vytvori tabulku pro tokeny.
 * Muzete jej spustit vicekrat - nevytvori duplicitni tabulku.
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
    <title>Migrace: Tabulka wgs_video_tokens</title>
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

    echo "<h1>Migrace: Vytvoreni tabulky wgs_video_tokens</h1>";

    // Kontrola zda tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_video_tokens'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<div class='info'>";
        echo "Tabulka <code>wgs_video_tokens</code> jiz existuje v databazi.";
        echo "</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_video_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Aktualni struktura:</h3>";
        echo "<pre>";
        foreach ($columns as $col) {
            echo sprintf("%-20s %-20s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
        }
        echo "</pre>";
    } else {
        echo "<div class='info'>";
        echo "Tabulka <code>wgs_video_tokens</code> neexistuje. Pripraveno k vytvoreni.";
        echo "</div>";

        // Vytvorit tabulku pokud je execute=1
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // SQL pro vytvoreni tabulky
                $sql = "
                CREATE TABLE wgs_video_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unikatni token pro stazeni',
                    claim_id INT NOT NULL COMMENT 'ID zakazky (wgs_reklamace.id)',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum vytvoreni',
                    expires_at DATETIME NOT NULL COMMENT 'Datum expirace',
                    download_count INT DEFAULT 0 COMMENT 'Pocet stazeni',
                    max_downloads INT DEFAULT 10 COMMENT 'Max pocet stazeni',
                    is_active TINYINT(1) DEFAULT 1 COMMENT 'Je token aktivni',
                    customer_email VARCHAR(255) DEFAULT NULL COMMENT 'Email zakaznika',

                    INDEX idx_token (token),
                    INDEX idx_claim_id (claim_id),
                    INDEX idx_expires_at (expires_at),

                    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                  COMMENT='Tokeny pro stahovani videi zakazniky';
                ";

                $pdo->exec($sql);

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br><br>";
                echo "Tabulka <code>wgs_video_tokens</code> byla uspesne vytvorena.<br><br>";
                echo "<strong>Struktura tabulky:</strong>";
                echo "<pre>";
                echo "id              INT             Auto increment primary key\n";
                echo "token           VARCHAR(64)     Unikatni token\n";
                echo "claim_id        INT             ID zakazky (foreign key)\n";
                echo "created_at      DATETIME        Datum vytvoreni\n";
                echo "expires_at      DATETIME        Datum expirace\n";
                echo "download_count  INT             Pocet stazeni\n";
                echo "max_downloads   INT             Max pocet stazeni (default 10)\n";
                echo "is_active       TINYINT         Aktivni token\n";
                echo "customer_email  VARCHAR(255)    Email zakaznika\n";
                echo "</pre>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Zobrazit tlacitko pro spusteni
            echo "<h3>SQL prikaz k provedeni:</h3>";
            echo "<pre>";
            echo "CREATE TABLE wgs_video_tokens (\n";
            echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
            echo "    token VARCHAR(64) NOT NULL UNIQUE,\n";
            echo "    claim_id INT NOT NULL,\n";
            echo "    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
            echo "    expires_at DATETIME NOT NULL,\n";
            echo "    download_count INT DEFAULT 0,\n";
            echo "    max_downloads INT DEFAULT 10,\n";
            echo "    is_active TINYINT(1) DEFAULT 1,\n";
            echo "    customer_email VARCHAR(255),\n";
            echo "    INDEX idx_token (token),\n";
            echo "    INDEX idx_claim_id (claim_id),\n";
            echo "    FOREIGN KEY (claim_id) REFERENCES wgs_reklamace(id) ON DELETE CASCADE\n";
            echo ");\n";
            echo "</pre>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php' class='btn' style='background:#666;'>Zpet do admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
