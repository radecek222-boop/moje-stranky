<?php
/**
 * Kontrola duplicitních záznamů GREY M
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
    <title>Kontrola duplicit GREY M</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Kontrola duplicit - GREY M</h1>";

    // Hledat všechny záznamy obsahující GREY a M
    $stmt = $pdo->prepare("
        SELECT
            reklamace_id,
            cislo,
            jmeno,
            adresa,
            model,
            assigned_to,
            technik,
            created_by,
            stav,
            DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') as vytvoren
        FROM wgs_reklamace
        WHERE cislo LIKE :cislo OR jmeno LIKE :jmeno
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        'cislo' => '%GREY%M%',
        'jmeno' => '%Pelikán%Martin%'
    ]);
    $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($zaznamy) === 0) {
        echo "<div class='warning'><strong>⚠️ NENALEZEN ŽÁDNÝ ZÁZNAM</strong></div>";
    } elseif (count($zaznamy) === 1) {
        echo "<div class='info'><strong>✓ Nalezen právě 1 záznam (OK)</strong></div>";
    } else {
        echo "<div class='warning'><strong>⚠️ NALEZENO " . count($zaznamy) . " ZÁZNAMŮ!</strong><br>";
        echo "Může existovat duplicita.</div>";
    }

    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Číslo</th>";
    echo "<th>Jméno</th>";
    echo "<th>Adresa</th>";
    echo "<th>Model</th>";
    echo "<th>assigned_to</th>";
    echo "<th>technik</th>";
    echo "<th>created_by</th>";
    echo "<th>Stav</th>";
    echo "<th>Vytvořen</th>";
    echo "</tr>";

    foreach ($zaznamy as $z) {
        echo "<tr>";
        echo "<td><code>{$z['reklamace_id']}</code></td>";
        echo "<td><code>{$z['cislo']}</code></td>";
        echo "<td>{$z['jmeno']}</td>";
        echo "<td>{$z['adresa']}</td>";
        echo "<td>{$z['model']}</td>";
        echo "<td><code>" . ($z['assigned_to'] ?? 'NULL') . "</code></td>";
        echo "<td><strong>" . ($z['technik'] ?? 'NULL') . "</strong></td>";
        echo "<td><code>" . ($z['created_by'] ?? 'NULL') . "</code></td>";
        echo "<td>{$z['stav']}</td>";
        echo "<td>{$z['vytvoren']}</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Simulovat přesně stejný dotaz jako statistiky
    echo "<h2>Simulace dotazu ze statistik</h2>";
    echo "<p>Toto je přesně to, co vrací API pro statistiky:</p>";

    $stmt = $pdo->query("
        SELECT
            r.cislo as cislo_reklamace,
            r.jmeno as jmeno_zakaznika,
            COALESCE(technik.name, r.technik, '-') as technik,
            r.assigned_to,
            technik.id as technik_id,
            technik.name as technik_name_from_join
        FROM wgs_reklamace r
        LEFT JOIN wgs_users technik ON r.assigned_to = technik.id AND technik.role = 'technik'
        WHERE r.cislo LIKE '%GREY%M%'
        LIMIT 5
    ");
    $vysledek = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr>";
    echo "<th>Číslo</th>";
    echo "<th>Jméno zákazníka</th>";
    echo "<th>r.assigned_to</th>";
    echo "<th>technik.id (JOIN)</th>";
    echo "<th>technik.name (JOIN)</th>";
    echo "<th>VÝSLEDEK (COALESCE)</th>";
    echo "</tr>";

    foreach ($vysledek as $v) {
        echo "<tr>";
        echo "<td>{$v['cislo_reklamace']}</td>";
        echo "<td>{$v['jmeno_zakaznika']}</td>";
        echo "<td><code>" . ($v['assigned_to'] ?? 'NULL') . "</code></td>";
        echo "<td><code>" . ($v['technik_id'] ?? 'NULL') . "</code></td>";
        echo "<td>" . ($v['technik_name_from_join'] ?? 'NULL') . "</td>";
        echo "<td><strong style='font-size: 1.2em; color: #0066cc;'>{$v['technik']}</strong></td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
