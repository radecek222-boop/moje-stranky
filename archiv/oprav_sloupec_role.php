<?php
/**
 * Migrace: Oprava sloupce role v tabulce wgs_users
 *
 * Tento skript opraví sloupec 'role' tak, aby akceptoval všechny role:
 * technik, prodejce, partner, admin, user
 *
 * Bezpečné spuštění - můžete spustit vícekrát.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava sloupce role</title>
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
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; }
        code { font-family: 'Consolas', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava sloupce role</h1>";

    // 1. Zkontrolovat aktuální definici sloupce role
    echo "<div class='info'><strong>KONTROLA AKTUALNI STRUKTURY...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users WHERE Field = 'role'");
    $roleColumn = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roleColumn) {
        echo "<div class='error'>Sloupec 'role' neexistuje v tabulce wgs_users!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<pre>";
    echo "Aktualni definice sloupce 'role':\n";
    echo "  Type: " . htmlspecialchars($roleColumn['Type']) . "\n";
    echo "  Null: " . htmlspecialchars($roleColumn['Null']) . "\n";
    echo "  Default: " . htmlspecialchars($roleColumn['Default'] ?? 'NULL') . "\n";
    echo "</pre>";

    // Zkontrolovat jestli je ENUM
    $currentType = $roleColumn['Type'];
    $isEnum = (strpos($currentType, 'enum') === 0);

    if ($isEnum) {
        echo "<div class='warning'>";
        echo "<strong>Sloupec 'role' je ENUM!</strong><br>";
        echo "Aktualni hodnoty: " . htmlspecialchars($currentType) . "<br>";
        echo "Je potreba zmenit na VARCHAR(20) nebo rozsirit ENUM.";
        echo "</div>";
    } else {
        echo "<div class='info'>Sloupec 'role' neni ENUM, je: " . htmlspecialchars($currentType) . "</div>";
    }

    // 2. Provest migraci pokud je pozadovano
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Zmenit sloupec role na VARCHAR(20)
            $sql = "ALTER TABLE wgs_users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'Role uzivatele (admin, user, prodejce, technik, partner)'";

            echo "<pre>SQL: " . htmlspecialchars($sql) . "</pre>";

            $pdo->exec($sql);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>";
            echo "Sloupec 'role' byl zmenen na VARCHAR(20).<br>";
            echo "Nyni akceptuje vsechny role: admin, user, prodejce, technik, partner";
            echo "</div>";

            // Overit zmenu
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users WHERE Field = 'role'");
            $newRoleColumn = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "<pre>";
            echo "Nova definice sloupce 'role':\n";
            echo "  Type: " . htmlspecialchars($newRoleColumn['Type']) . "\n";
            echo "  Null: " . htmlspecialchars($newRoleColumn['Null']) . "\n";
            echo "  Default: " . htmlspecialchars($newRoleColumn['Default'] ?? 'NULL') . "\n";
            echo "</pre>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // Nahled - co bude provedeno
        echo "<h3>Co bude provedeno:</h3>";
        echo "<pre>";
        echo "ALTER TABLE wgs_users \n";
        echo "MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' \n";
        echo "COMMENT 'Role uzivatele (admin, user, prodejce, technik, partner)'";
        echo "</pre>";

        echo "<div class='warning'>";
        echo "<strong>Pozor:</strong> Tato zmena je nevratna. Uistete se, ze mate zalohu databaze!";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #666;'>Zpet do Admin</a>";
    }

    // 3. Zobrazit aktualni uzivatele a jejich role
    echo "<h3>Aktualni uzivatele a jejich role:</h3>";

    $stmt = $pdo->query("SELECT user_id, email, name, role, is_admin FROM wgs_users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<table style='width:100%; border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>User ID</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Email</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Jmeno</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Role</th>";
        echo "<th style='padding: 8px; border: 1px solid #ddd;'>Is Admin</th>";
        echo "</tr>";

        foreach ($users as $user) {
            echo "<tr>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($user['user_id'] ?? '-') . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($user['email'] ?? '-') . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($user['name'] ?? '-') . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($user['role'] ?? '-') . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($user['is_admin'] ? 'Ano' : 'Ne') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<div class='info'>Zadni uzivatele v databazi.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
