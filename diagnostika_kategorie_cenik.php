<?php
/**
 * Diagnostika: Kontrola kategorií v databázi ceníku
 * Zobrazí všechny kategorie a jejich překlady
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: Kategorie ceníku</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #333; color: white; position: sticky; top: 0; }
        .filled { background: #d4edda; color: #155724; font-weight: bold; }
        .empty { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika: Kategorie v databázi ceníku</h1>";

    // Načíst všechny položky
    $stmt = $pdo->query("
        SELECT
            id,
            service_name,
            category,
            category_en,
            category_it
        FROM wgs_pricing
        ORDER BY category, service_name
    ");

    $polozky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>CELKOVÝ POČET POLOŽEK:</strong> " . count($polozky);
    echo "</div>";

    // Statistiky kategorií
    $kategorie = [];
    foreach ($polozky as $polozka) {
        $cat = $polozka['category'];
        if (!isset($kategorie[$cat])) {
            $kategorie[$cat] = [
                'cs' => $cat,
                'en' => $polozka['category_en'],
                'it' => $polozka['category_it'],
                'pocet_polozek' => 0
            ];
        }
        $kategorie[$cat]['pocet_polozek']++;
    }

    echo "<h2>Přehled kategorií a jejich překladů</h2>";
    echo "<table>";
    echo "<tr>
            <th>Kategorie (CS)</th>
            <th>Kategorie (EN)</th>
            <th>Kategorie (IT)</th>
            <th>Počet položek</th>
          </tr>";

    foreach ($kategorie as $cat => $data) {
        $enClass = !empty($data['en']) ? 'filled' : 'empty';
        $itClass = !empty($data['it']) ? 'filled' : 'empty';

        $enText = !empty($data['en']) ? htmlspecialchars($data['en']) : '❌ CHYBÍ';
        $itText = !empty($data['it']) ? htmlspecialchars($data['it']) : '❌ CHYBÍ';

        echo "<tr>
                <td><strong>" . htmlspecialchars($data['cs']) . "</strong></td>
                <td class='{$enClass}'>{$enText}</td>
                <td class='{$itClass}'>{$itText}</td>
                <td>{$data['pocet_polozek']}</td>
              </tr>";
    }
    echo "</table>";

    // Kontrola, kolik kategorií má překlady
    $kategorieENPrazdne = 0;
    $kategorieITPrazdne = 0;
    foreach ($kategorie as $data) {
        if (empty($data['en'])) $kategorieENPrazdne++;
        if (empty($data['it'])) $kategorieITPrazdne++;
    }

    if ($kategorieENPrazdne > 0 || $kategorieITPrazdne > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ PROBLÉM NALEZEN:</strong><br>";
        if ($kategorieENPrazdne > 0) {
            echo "• {$kategorieENPrazdne} kategorií nemá anglický překlad<br>";
        }
        if ($kategorieITPrazdne > 0) {
            echo "• {$kategorieITPrazdne} kategorií nemá italský překlad<br>";
        }
        echo "<br><strong>Řešení:</strong> Spusť skript pro doplnění překladů kategorií.";
        echo "</div>";
    } else {
        echo "<div class='info'>";
        echo "<strong>✓ VŠECHNY KATEGORIE MAJÍ PŘEKLADY</strong>";
        echo "</div>";
    }

    echo "<h2>Detailní výpis všech položek</h2>";
    echo "<table>";
    echo "<tr>
            <th>ID</th>
            <th>Název služby</th>
            <th>Kategorie (CS)</th>
            <th>Kategorie (EN)</th>
            <th>Kategorie (IT)</th>
          </tr>";

    foreach ($polozky as $polozka) {
        $enClass = !empty($polozka['category_en']) ? 'filled' : 'empty';
        $itClass = !empty($polozka['category_it']) ? 'filled' : 'empty';

        $enText = !empty($polozka['category_en']) ? htmlspecialchars($polozka['category_en']) : '❌';
        $itText = !empty($polozka['category_it']) ? htmlspecialchars($polozka['category_it']) : '❌';

        echo "<tr>
                <td>{$polozka['id']}</td>
                <td>" . htmlspecialchars($polozka['service_name']) . "</td>
                <td><strong>" . htmlspecialchars($polozka['category']) . "</strong></td>
                <td class='{$enClass}'>{$enText}</td>
                <td class='{$itClass}'>{$itText}</td>
              </tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
