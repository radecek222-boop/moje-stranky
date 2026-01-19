<?php
/**
 * Kontrola údajů Milan Kolín v databázi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola Milan Kolín</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        code { background: #333; padding: 2px 6px; border-radius: 3px; color: #fff; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Kontrola Milan Kolín</h1>";

    // Načíst Milan Kolín z wgs_users
    $stmt = $pdo->prepare("SELECT id, user_id, name, email, role FROM wgs_users WHERE name LIKE :name LIMIT 1");
    $stmt->execute(['name' => '%Milan%Kolín%']);
    $milan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($milan) {
        echo "<div class='info'><strong>Milan Kolín v tabulce wgs_users:</strong></div>";
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
        echo "<tr><td>id (INTEGER)</td><td><code>{$milan['id']}</code></td></tr>";
        echo "<tr><td>user_id (VARCHAR)</td><td><code>{$milan['user_id']}</code></td></tr>";
        echo "<tr><td>name</td><td><code>{$milan['name']}</code></td></tr>";
        echo "<tr><td>email</td><td><code>{$milan['email']}</code></td></tr>";
        echo "<tr><td>role</td><td><code><strong style='color: " . ($milan['role'] === 'technik' ? 'green' : 'red') . "'>{$milan['role']}</strong></code></td></tr>";
        echo "</table>";

        if ($milan['role'] !== 'technik') {
            echo "<div class='warning'>";
            echo "<strong>⚠️ PROBLÉM NALEZEN:</strong><br>";
            echo "Milan Kolín má roli '<strong>{$milan['role']}</strong>', ale statistiky filtrují pouze uživatele s rolí '<strong>technik</strong>'.<br>";
            echo "Proto se v statistikách nezobrazuje jeho jméno správně.";
            echo "</div>";
        }
    } else {
        echo "<div class='warning'>Milan Kolín nebyl nalezen v databázi wgs_users.</div>";
    }

    // Načíst zakázku GREY M
    echo "<h2>Zakázka GREY M v databázi:</h2>";
    $stmt = $pdo->prepare("SELECT cislo, jmeno, assigned_to, technik, created_by FROM wgs_reklamace WHERE cislo LIKE :cislo LIMIT 1");
    $stmt->execute(['cislo' => '%GREY%M%']);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($zakazka) {
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
        echo "<tr><td>cislo</td><td>{$zakazka['cislo']}</td></tr>";
        echo "<tr><td>jmeno</td><td>{$zakazka['jmeno']}</td></tr>";
        echo "<tr><td>assigned_to (INTEGER)</td><td><code>{$zakazka['assigned_to']}</code></td></tr>";
        echo "<tr><td>technik (VARCHAR)</td><td><code>{$zakazka['technik']}</code></td></tr>";
        echo "<tr><td>created_by (VARCHAR)</td><td><code>{$zakazka['created_by']}</code></td></tr>";
        echo "</table>";
    }

    // Simulovat JOIN který používá statistiky
    echo "<h2>Simulace JOIN z statistiky API:</h2>";
    $stmt = $pdo->prepare("
        SELECT
            r.cislo,
            r.assigned_to as assigned_to_raw,
            technik.name as technik_z_joinu,
            r.technik as technik_textovy,
            COALESCE(technik.name, r.technik, '-') as technik_vysledny,
            technik.role as technik_role
        FROM wgs_reklamace r
        LEFT JOIN wgs_users technik ON r.assigned_to = technik.id AND technik.role = 'technik'
        WHERE r.cislo LIKE :cislo
        LIMIT 1
    ");
    $stmt->execute(['cislo' => '%GREY%M%']);
    $join = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($join) {
        echo "<table>";
        echo "<tr><th>Výraz</th><th>Hodnota</th></tr>";
        echo "<tr><td>r.assigned_to</td><td><code>{$join['assigned_to_raw']}</code></td></tr>";
        echo "<tr><td>technik.name (z JOINu)</td><td><code>" . ($join['technik_z_joinu'] ?? 'NULL') . "</code></td></tr>";
        echo "<tr><td>r.technik (textový sloupec)</td><td><code>{$join['technik_textovy']}</code></td></tr>";
        echo "<tr><td>technik.role (z JOINu)</td><td><code>" . ($join['technik_role'] ?? 'NULL') . "</code></td></tr>";
        echo "<tr><td><strong>COALESCE(technik.name, r.technik, '-')</strong></td><td><code><strong style='font-size: 1.2em; color: #0066cc;'>{$join['technik_vysledny']}</strong></code></td></tr>";
        echo "</table>";

        echo "<div class='info'>";
        echo "<strong>Vysvětlení:</strong><br>";
        echo "Statistiky používají COALESCE(), který vybírá první nenulovou hodnotu:<br>";
        echo "1. Pokud JOIN vrátí technik.name → použije se to<br>";
        echo "2. Pokud JOIN vrátí NULL → použije se r.technik (textový sloupec)<br>";
        echo "3. Pokud je i r.technik NULL → použije se '-'";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
