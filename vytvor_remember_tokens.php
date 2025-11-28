<?php
/**
 * Migrace: Vytvoreni tabulky wgs_remember_tokens
 *
 * Tabulka pro funkci "Zapamatovat si me" pri prihlaseni.
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
    <title>Migrace: Vytvoreni wgs_remember_tokens</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; font-size: 13px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Vytvoreni wgs_remember_tokens</h1>";

    // Zkontrolovat jestli tabulka existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_remember_tokens'");
    $existuje = $stmt->rowCount() > 0;

    if ($existuje) {
        echo "<div class='success'>Tabulka wgs_remember_tokens jiz existuje!</div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_remember_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Struktura tabulky:</h3>";
        echo "<pre>";
        foreach ($columns as $col) {
            echo sprintf("%-20s %-30s %s\n",
                $col['Field'],
                $col['Type'],
                $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
            );
        }
        echo "</pre>";

    } else {
        echo "<div class='info'>Tabulka wgs_remember_tokens neexistuje.</div>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>VYTVÁŘÍM TABULKU...</strong></div>";

            $sql = "
                CREATE TABLE wgs_remember_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    selector VARCHAR(64) NOT NULL UNIQUE,
                    hashed_validator VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_selector (selector),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Tokeny pro funkci Zapamatovat si me'
            ";

            echo "<pre>" . htmlspecialchars($sql) . "</pre>";

            $pdo->exec($sql);

            echo "<div class='success'>";
            echo "<strong>TABULKA USPESNE VYTVORENA!</strong><br>";
            echo "Funkce 'Zapamatovat si me' je nyni funkcni.";
            echo "</div>";

        } else {
            echo "<h3>SQL pro vytvoreni tabulky:</h3>";
            echo "<pre>
CREATE TABLE wgs_remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    selector VARCHAR(64) NOT NULL UNIQUE,
    hashed_validator VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_selector (selector),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
</pre>";

            echo "<a href='?execute=1' class='btn'>VYTVORIT TABULKU</a>";
            echo "<a href='admin.php' class='btn' style='background:#666;'>Zpet</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
