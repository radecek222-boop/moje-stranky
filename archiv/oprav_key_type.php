<?php
/**
 * Migrace: Oprava sloupce key_type v tabulce wgs_registration_keys
 *
 * Zmeni sloupec key_type na VARCHAR(20) aby akceptoval vsechny typy klicu.
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
    <title>Migrace: Oprava key_type</title>
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
              overflow-x: auto; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Oprava key_type</h1>";

    // 1. Zkontrolovat aktualni definici
    echo "<div class='info'><strong>KONTROLA AKTUALNI STRUKTURY...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_registration_keys WHERE Field = 'key_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        echo "<div class='error'>Sloupec 'key_type' neexistuje!</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<pre>";
    echo "Aktualni definice sloupce 'key_type':\n";
    echo "  Type: " . htmlspecialchars($column['Type']) . "\n";
    echo "  Null: " . htmlspecialchars($column['Null']) . "\n";
    echo "  Default: " . htmlspecialchars($column['Default'] ?? 'NULL') . "\n";
    echo "</pre>";

    // Zobrazit existujici klice
    echo "<h3>Existujici registracni klice:</h3>";
    $stmt = $pdo->query("SELECT key_code, key_type, usage_count, max_usage, is_active, created_at FROM wgs_registration_keys ORDER BY created_at DESC");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($keys) > 0) {
        echo "<table>";
        echo "<tr><th>Kod</th><th>Typ</th><th>Pouziti</th><th>Aktivni</th><th>Vytvoreno</th></tr>";
        foreach ($keys as $key) {
            $max = $key['max_usage'] ?? '∞';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($key['key_code']) . "</td>";
            echo "<td>" . htmlspecialchars($key['key_type']) . "</td>";
            echo "<td>" . htmlspecialchars($key['usage_count']) . " / " . $max . "</td>";
            echo "<td>" . ($key['is_active'] ? 'Ano' : 'Ne') . "</td>";
            echo "<td>" . htmlspecialchars($key['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>Zadne klice v databazi.</div>";
    }

    // 2. Provest migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

        // Zmenit key_type na VARCHAR(20)
        $sql = "ALTER TABLE wgs_registration_keys MODIFY COLUMN key_type VARCHAR(20) NOT NULL DEFAULT 'technik'";
        echo "<pre>SQL: " . htmlspecialchars($sql) . "</pre>";

        $pdo->exec($sql);

        echo "<div class='success'>";
        echo "<strong>MIGRACE USPESNE DOKONCENA!</strong><br>";
        echo "Sloupec 'key_type' zmenen na VARCHAR(20).<br>";
        echo "Nyni muzete vytvorit klice typu: technik, prodejce";
        echo "</div>";

        // Overit
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_registration_keys WHERE Field = 'key_type'");
        $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>Nova definice: " . htmlspecialchars($newColumn['Type']) . "</pre>";

    } else {
        echo "<h3>Co bude provedeno:</h3>";
        echo "<pre>ALTER TABLE wgs_registration_keys\nMODIFY COLUMN key_type VARCHAR(20) NOT NULL DEFAULT 'technik'</pre>";

        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php?tab=keys' class='btn' style='background:#666;'>Zpet</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
