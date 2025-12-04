<?php
/**
 * Migrace: Vytvoření tabulky pro přiřazení supervizorů
 *
 * Tabulka wgs_supervisor_assignments umožňuje přiřadit prodejce pod supervizora.
 * Supervizor pak vidí zakázky všech přiřazených prodejců + své vlastní.
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
    <title>Migrace: Supervisor Assignments</title>
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
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 13px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Supervisor Assignments</h1>";

    // Kontrola zda tabulka již existuje
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_supervisor_assignments'");
    $tabulkaExistuje = $stmt->rowCount() > 0;

    if ($tabulkaExistuje) {
        echo "<div class='info'><strong>Tabulka wgs_supervisor_assignments již existuje.</strong></div>";

        // Zobrazit strukturu
        $stmt = $pdo->query("DESCRIBE wgs_supervisor_assignments");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Aktuální struktura:</h3><pre>";
        foreach ($sloupce as $sloupec) {
            echo "{$sloupec['Field']} - {$sloupec['Type']} ({$sloupec['Key']})\n";
        }
        echo "</pre>";

        // Zobrazit počet záznamů
        $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_supervisor_assignments");
        $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
        echo "<div class='info'>Počet přiřazení: <strong>{$pocet}</strong></div>";

    } else {

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            // Nejprve zjistit název primárního klíče v wgs_users
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users WHERE `Key` = 'PRI'");
            $pkColumn = $stmt->fetch(PDO::FETCH_ASSOC);
            $userPkName = $pkColumn ? $pkColumn['Field'] : 'user_id';

            echo "<div class='info'>Primární klíč wgs_users: <strong>{$userPkName}</strong></div>";

            $sql = "
                CREATE TABLE wgs_supervisor_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    supervisor_user_id INT NOT NULL COMMENT 'ID supervizora (kdo vidí)',
                    salesperson_user_id INT NOT NULL COMMENT 'ID prodejce (koho zakázky vidí)',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_by INT DEFAULT NULL COMMENT 'Admin který přiřazení vytvořil',
                    UNIQUE KEY unique_assignment (supervisor_user_id, salesperson_user_id),
                    KEY idx_supervisor (supervisor_user_id),
                    KEY idx_salesperson (salesperson_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
                COMMENT='Přiřazení prodejců pod supervizory - supervizor vidí zakázky přiřazených prodejců'
            ";

            $pdo->exec($sql);

            echo "<div class='success'>";
            echo "<strong>TABULKA ÚSPĚŠNĚ VYTVOŘENA!</strong><br><br>";
            echo "Tabulka <code>wgs_supervisor_assignments</code> byla vytvořena.<br>";
            echo "Nyní můžete v admin panelu přiřazovat prodejce pod supervizory.";
            echo "</div>";

            // Zobrazit strukturu
            $stmt = $pdo->query("DESCRIBE wgs_supervisor_assignments");
            $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<h3>Struktura tabulky:</h3><pre>";
            foreach ($sloupce as $sloupec) {
                echo "{$sloupec['Field']} - {$sloupec['Type']} ({$sloupec['Key']})\n";
            }
            echo "</pre>";

        } else {
            // Náhled
            echo "<div class='info'>";
            echo "<strong>NÁHLED:</strong> Bude vytvořena nová tabulka pro přiřazení supervizorů.";
            echo "</div>";

            echo "<h3>SQL příkaz:</h3>";
            echo "<pre>
CREATE TABLE wgs_supervisor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supervisor_user_id INT NOT NULL,    -- ID supervizora
    salesperson_user_id INT NOT NULL,   -- ID prodejce
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,        -- Admin který přiřazení vytvořil
    UNIQUE KEY (supervisor_user_id, salesperson_user_id),
    FOREIGN KEY (supervisor_user_id) REFERENCES wgs_users(user_id),
    FOREIGN KEY (salesperson_user_id) REFERENCES wgs_users(user_id)
);</pre>";

            echo "<h3>Účel:</h3>";
            echo "<ul>";
            echo "<li>Supervizor = uživatel který vidí zakázky přiřazených prodejců</li>";
            echo "<li>Jeden prodejce může být pod více supervizory</li>";
            echo "<li>Supervizor vidí své zakázky + zakázky přiřazených prodejců</li>";
            echo "</ul>";

            echo "<a href='?execute=1' class='btn'>VYTVOŘIT TABULKU</a>";
            echo "<a href='/admin.php?tab=users' class='btn' style='background:#666;'>Zpět do Admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='/admin.php?tab=users' class='btn' style='background:#666;'>Zpět do Admin</a>";
echo "</div></body></html>";
?>
