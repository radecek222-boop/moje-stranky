<?php
/**
 * Find SMTP Configuration
 * Rychlý script pro nalezení SMTP údajů v databázi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola (session už běží z init.php)
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - admin login required');
}

$pdo = getDbConnection();

echo "<h1>🔍 Hledání SMTP konfigurace</h1>";
echo "<style>body { font-family: monospace; padding: 20px; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f0f0f0; } .found { background: #d4edda; } .empty { background: #fff3cd; }</style>";

// 1. Hledat ve wgs_system_config
echo "<h2>1. wgs_system_config tabulka</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM wgs_system_config WHERE config_key LIKE '%smtp%' OR config_key LIKE '%mail%' OR config_group = 'email' ORDER BY config_key");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($configs)) {
        echo "<table>";
        echo "<tr><th>Config Key</th><th>Value</th><th>Group</th><th>Sensitive</th></tr>";
        foreach ($configs as $config) {
            $value = $config['config_value'];
            $class = !empty($value) ? 'found' : 'empty';

            // Zamaskovat hesla
            if (stripos($config['config_key'], 'password') !== false || stripos($config['config_key'], 'pass') !== false) {
                $value = !empty($value) ? '***' . substr($value, -4) : '(empty)';
            } else {
                $value = !empty($value) ? htmlspecialchars($value) : '(empty)';
            }

            echo "<tr class='$class'>";
            echo "<td><strong>" . htmlspecialchars($config['config_key']) . "</strong></td>";
            echo "<td>" . $value . "</td>";
            echo "<td>" . htmlspecialchars($config['config_group']) . "</td>";
            echo "<td>" . ($config['is_sensitive'] ? '🔒 Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Žádné SMTP konfigurace nenalezeny v wgs_system_config</p>";
    }
} catch (PDOException $e) {
    echo "<p>⚠️ Tabulka wgs_system_config neexistuje nebo je nedostupná: " . $e->getMessage() . "</p>";
}

// 2. Hledat v jiných config tabulkách
echo "<h2>2. Ostatní config tabulky</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE '%config%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        if ($table === 'wgs_system_config') continue; // Už jsme kontrolovali

        echo "<h3>Tabulka: $table</h3>";
        try {
            // Získat strukturu tabulky
            $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);

            // Najít sloupce které mohou obsahovat SMTP údaje
            $searchCols = array_filter($cols, function($col) {
                return stripos($col, 'smtp') !== false ||
                       stripos($col, 'mail') !== false ||
                       stripos($col, 'email') !== false ||
                       stripos($col, 'host') !== false ||
                       stripos($col, 'port') !== false;
            });

            if (!empty($searchCols)) {
                $colList = implode(', ', $searchCols);
                $rows = $pdo->query("SELECT * FROM $table LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    echo "<pre>" . print_r($rows, true) . "</pre>";
                } else {
                    echo "<p>Tabulka je prázdná</p>";
                }
            } else {
                echo "<p>Žádné relevantní sloupce</p>";
            }
        } catch (PDOException $e) {
            echo "<p>⚠️ Chyba při čtení: " . $e->getMessage() . "</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p>⚠️ Chyba při hledání tabulek: " . $e->getMessage() . "</p>";
}

// 3. Zkontrolovat PHP konstanty z config.php
echo "<h2>3. PHP Konstanty (z .env nebo config.php)</h2>";
echo "<table>";
echo "<tr><th>Konstanta</th><th>Hodnota</th></tr>";

$constants = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM'];
foreach ($constants as $const) {
    if (defined($const)) {
        $value = constant($const);

        // Zamaskovat hesla
        if (stripos($const, 'PASS') !== false) {
            $display = !empty($value) && !stripos($value, 'CHYBA') ? '***' . substr($value, -4) : htmlspecialchars($value);
        } else {
            $display = htmlspecialchars($value);
        }

        $class = !empty($value) && stripos($value, 'CHYBA') === false ? 'found' : 'empty';
        echo "<tr class='$class'><td><strong>$const</strong></td><td>$display</td></tr>";
    } else {
        echo "<tr class='empty'><td><strong>$const</strong></td><td>(not defined)</td></tr>";
    }
}
echo "</table>";

echo "<hr>";
echo "<p><a href='/admin.php?tab=control_center'>← Zpět do Admin Control Center</a></p>";
