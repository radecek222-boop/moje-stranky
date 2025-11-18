<?php
/**
 * Migrace: Oprava AUTO_INCREMENT na sloupci user_id
 *
 * Tento skript BEZPEČNĚ opraví sloupec user_id v tabulce wgs_users,
 * aby měl správně nastavený AUTO_INCREMENT.
 * Můžete jej spustit vícekrát - pokud je již opraveno, nic se nestane.
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
    <title>Migrace: Oprava AUTO_INCREMENT na user_id</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #2D5016; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Oprava AUTO_INCREMENT na user_id</h1>";

    // Kontrolní fáze - zjistit aktuální strukturu sloupce user_id
    echo "<div class='info'><strong>KONTROLA AKTUÁLNÍ STRUKTURY...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'user_id'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        echo "<div class='error'><strong>CHYBA:</strong> Sloupec 'user_id' nebyl nalezen v tabulce wgs_users!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'>";
    echo "<strong>Aktuální struktura sloupce user_id:</strong><br>";
    echo "<pre>";
    echo "Typ: " . htmlspecialchars($column['Type']) . "\n";
    echo "Null: " . htmlspecialchars($column['Null']) . "\n";
    echo "Key: " . htmlspecialchars($column['Key']) . "\n";
    echo "Default: " . htmlspecialchars($column['Default'] ?? 'NULL') . "\n";
    echo "Extra: " . htmlspecialchars($column['Extra']) . "\n";
    echo "</pre>";
    echo "</div>";

    // Zkontrolovat, zda má AUTO_INCREMENT
    $hasAutoIncrement = stripos($column['Extra'], 'auto_increment') !== false;

    if ($hasAutoIncrement) {
        echo "<div class='success'>";
        echo "<strong>✅ SLOUPEC JE JIŽ V POŘÁDKU</strong><br>";
        echo "Sloupec 'user_id' již má správně nastavený AUTO_INCREMENT.<br>";
        echo "Není třeba provádět žádnou migraci.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ PROBLÉM ZJIŠTĚN</strong><br>";
        echo "Sloupec 'user_id' NEMÁ nastavený AUTO_INCREMENT.<br>";
        echo "To způsobuje chybu při registraci: 'Field 'user_id' doesn't have a default value'";
        echo "</div>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>🚀 SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Zjistit aktuální maximální user_id pro nastavení AUTO_INCREMENT startovní hodnoty
                $maxIdStmt = $pdo->query("SELECT MAX(user_id) as max_id FROM wgs_users");
                $maxIdRow = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
                $nextId = ($maxIdRow['max_id'] ?? 0) + 1;

                echo "<div class='info'>";
                echo "Maximální existující user_id: " . ($maxIdRow['max_id'] ?? 0) . "<br>";
                echo "Další AUTO_INCREMENT bude: " . $nextId;
                echo "</div>";

                // Opravit sloupec user_id - přidat AUTO_INCREMENT a PRIMARY KEY
                $alterSql = "ALTER TABLE wgs_users
                            MODIFY COLUMN user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY";

                $pdo->exec($alterSql);

                // Nastavit startovní hodnotu AUTO_INCREMENT
                $pdo->exec("ALTER TABLE wgs_users AUTO_INCREMENT = $nextId");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Sloupec 'user_id' byl úspěšně opraven:<br>";
                echo "- Přidán AUTO_INCREMENT<br>";
                echo "- Nastaven jako PRIMARY KEY<br>";
                echo "- Startovní hodnota nastavena na: $nextId<br><br>";
                echo "<strong>Nyní můžete zkusit registraci znovu!</strong>";
                echo "</div>";

                // Zobrazit novou strukturu
                $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'user_id'");
                $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);

                echo "<div class='info'>";
                echo "<strong>Nová struktura sloupce user_id:</strong><br>";
                echo "<pre>";
                echo "Typ: " . htmlspecialchars($newColumn['Type']) . "\n";
                echo "Null: " . htmlspecialchars($newColumn['Null']) . "\n";
                echo "Key: " . htmlspecialchars($newColumn['Key']) . "\n";
                echo "Default: " . htmlspecialchars($newColumn['Default'] ?? 'NULL') . "\n";
                echo "Extra: " . htmlspecialchars($newColumn['Extra']) . "\n";
                echo "</pre>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA PŘI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>📋 CO BUDE PROVEDENO:</strong><br>";
            echo "<pre>";
            echo "1. Zjištění maximálního existujícího user_id\n";
            echo "2. Úprava sloupce user_id:\n";
            echo "   ALTER TABLE wgs_users \n";
            echo "   MODIFY COLUMN user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY\n";
            echo "3. Nastavení startovní hodnoty AUTO_INCREMENT\n";
            echo "</pre>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT MIGRACI</a>";
            echo "<a href='vsechny_tabulky.php' class='btn' style='background: #6c757d;'>🔙 Zpět na SQL kartu</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>KRITICKÁ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
