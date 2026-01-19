<?php
/**
 * Migrace: Přidání sloupce provize_procent do tabulky wgs_users
 *
 * Tento skript BEZPEČNĚ přidá sloupec pro individuální procentuální odměny techniků.
 * Výchozí hodnota je 33% pro všechny techniky.
 * Můžete jej spustit vícekrát - pokud sloupec již existuje, nic neudělá.
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
    <title>Migrace: Provize techniků</title>
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
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer;
               border: none; }
        .btn:hover { background: #1a1a1a; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Individuální provize techniků</h1>";

    // Kontrola zda sloupec již existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'provize_procent'");
    $sloupecExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sloupecExistuje) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ SLOUPEC JIŽ EXISTUJE</strong><br>";
        echo "Sloupec <code>provize_procent</code> již existuje v tabulce <code>wgs_users</code>.<br>";
        echo "Migrace není potřeba.";
        echo "</div>";

        // Zobrazit aktuální hodnoty techniků
        $stmt = $pdo->query("
            SELECT id, name, role, provize_procent
            FROM wgs_users
            WHERE role = 'technik'
            ORDER BY name
        ");
        $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($technici) > 0) {
            echo "<div class='info'><strong>Aktuální provize techniků:</strong></div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Jméno</th><th>Provize (%)</th></tr>";
            foreach ($technici as $t) {
                $provize = $t['provize_procent'] ?? 33;
                echo "<tr>";
                echo "<td>{$t['id']}</td>";
                echo "<td>{$t['name']}</td>";
                echo "<td><strong>{$provize}%</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<div class='info'><strong>KONTROLA...</strong></div>";
        echo "<p>Sloupec <code>provize_procent</code> neexistuje v tabulce <code>wgs_users</code>.</p>";

        // Zobrazit techniky kteří budou ovlivněni
        $stmt = $pdo->query("SELECT id, name, email FROM wgs_users WHERE role = 'technik' ORDER BY name");
        $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($technici) > 0) {
            echo "<div class='info'>";
            echo "<strong>Nalezeno " . count($technici) . " techniků:</strong><br>";
            echo "<ul>";
            foreach ($technici as $t) {
                echo "<li><strong>{$t['name']}</strong> ({$t['email']}) - bude mít výchozí provizi 33%</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        // Pokud je execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Přidat sloupec provize_procent
                $pdo->exec("
                    ALTER TABLE wgs_users
                    ADD COLUMN provize_procent DECIMAL(5,2) DEFAULT 33.00
                    COMMENT 'Procentuální odměna technika z ceny zakázky (výchozí 33%)'
                    AFTER role
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Sloupec <code>provize_procent</code> byl přidán do tabulky <code>wgs_users</code>.<br>";
                echo "Výchozí hodnota: <strong>33.00%</strong>";
                echo "</div>";

                // Zobrazit výsledek
                $stmt = $pdo->query("
                    SELECT id, name, email, provize_procent
                    FROM wgs_users
                    WHERE role = 'technik'
                    ORDER BY name
                ");
                $technici = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($technici) > 0) {
                    echo "<div class='info'><strong>Provize techniků po migraci:</strong></div>";
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Jméno</th><th>Email</th><th>Provize (%)</th></tr>";
                    foreach ($technici as $t) {
                        echo "<tr>";
                        echo "<td>{$t['id']}</td>";
                        echo "<td>{$t['name']}</td>";
                        echo "<td>{$t['email']}</td>";
                        echo "<td><strong>{$t['provize_procent']}%</strong></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }

                echo "<p><a href='/admin.php?tab=keys&section=uzivatele' class='btn'>Otevřit správu uživatelů</a></p>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>SQL příkaz který bude proveden:</strong><br>";
            echo "<code style='display: block; padding: 10px; background: #333; color: #0f0; margin: 10px 0;'>";
            echo "ALTER TABLE wgs_users<br>";
            echo "ADD COLUMN provize_procent DECIMAL(5,2) DEFAULT 33.00<br>";
            echo "COMMENT 'Procentuální odměna technika z ceny zakázky (výchozí 33%)'<br>";
            echo "AFTER role;";
            echo "</code>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
