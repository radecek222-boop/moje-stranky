<?php
/**
 * Migrace: Oprava sloupce created_by na VARCHAR
 *
 * Sloupec created_by musi byt VARCHAR, protoze user_id muze byt:
 * - 'ADMIN001' pro admina
 * - 'TCH20250001' pro technika
 * - 'PRO20250001' pro prodejce
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Oprava created_by</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #ddd; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava sloupce created_by</h1>";

    // Zkontrolovat aktualni typ
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field = 'created_by'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        echo "<div class='error'>Sloupec 'created_by' neexistuje!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<pre>Aktualni typ: " . htmlspecialchars($column['Type']) . "</pre>";

    $isInt = (stripos($column['Type'], 'int') !== false);

    if (!$isInt) {
        echo "<div class='success'>Sloupec 'created_by' je jiz VARCHAR - neni potreba menit.</div>";
    } else {
        echo "<div class='info'>Sloupec 'created_by' je INT - potreba zmenit na VARCHAR.</div>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            // Nejdriv zmenit existujici INT hodnoty na string
            $sql1 = "ALTER TABLE wgs_reklamace MODIFY COLUMN created_by VARCHAR(50) NULL";
            echo "<pre>SQL: " . htmlspecialchars($sql1) . "</pre>";
            $pdo->exec($sql1);

            echo "<div class='success'><strong>HOTOVO!</strong> Sloupec zmenen na VARCHAR(50).</div>";

            // Overit
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field = 'created_by'");
            $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<pre>Novy typ: " . htmlspecialchars($newColumn['Type']) . "</pre>";

        } else {
            echo "<h3>SQL pro zmenu:</h3>";
            echo "<pre>ALTER TABLE wgs_reklamace MODIFY COLUMN created_by VARCHAR(50) NULL</pre>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    // Zkontrolovat take zpracoval_id
    echo "<h3>Kontrola sloupce zpracoval_id:</h3>";
    $stmt2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field = 'zpracoval_id'");
    $column2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($column2) {
        echo "<pre>Typ zpracoval_id: " . htmlspecialchars($column2['Type']) . "</pre>";

        $isInt2 = (stripos($column2['Type'], 'int') !== false);

        if ($isInt2 && isset($_GET['execute']) && $_GET['execute'] === '1') {
            $sql2 = "ALTER TABLE wgs_reklamace MODIFY COLUMN zpracoval_id VARCHAR(50) NULL";
            echo "<pre>SQL: " . htmlspecialchars($sql2) . "</pre>";
            $pdo->exec($sql2);
            echo "<div class='success'>Sloupec zpracoval_id zmenen na VARCHAR(50).</div>";
        } elseif ($isInt2) {
            echo "<div class='info'>zpracoval_id je take INT - bude zmenen spolu s created_by.</div>";
        }
    }

    echo "<br><a href='admin.php' class='btn' style='background:#666;'>Zpet do Admin</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
