<?php
/**
 * Diagnostika: Zobrazení obsahu polozky_json v nabídkách
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: polozky_json</title>
    <style>
        body { font-family: 'Segoe UI', monospace; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .nabidka { border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; background: #fafafa; }
        .nabidka h2 { margin-top: 0; color: #333; }
        .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 10px; margin: 10px 0; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 10px; margin: 10px 0; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 10px; margin: 10px 0; }
        .json { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word; }
        .metadata { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 15px 0; }
        .metadata-label { font-weight: bold; color: #666; }
        .metadata-value { color: #333; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Diagnostika: Obsah polozky_json</h1>";

    // Načíst všechny nabídky
    $stmt = $pdo->query("
        SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email,
               celkova_cena, mena, stav, polozky_json,
               vytvoreno_at, odeslano_at
        FROM wgs_nabidky
        ORDER BY vytvoreno_at DESC
        LIMIT 10
    ");
    $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Zobrazeno posledních " . count($nabidky) . " nabídek</p>";

    foreach ($nabidky as $n) {
        echo "<div class='nabidka'>";

        // Záhlaví
        $cislo = $n['cislo_nabidky'] ?? 'CN-' . $n['id'];
        echo "<h2>#{$n['id']} - {$cislo}</h2>";

        // Metadata
        echo "<div class='metadata'>";
        echo "<div class='metadata-label'>Zákazník:</div><div class='metadata-value'>{$n['zakaznik_jmeno']} ({$n['zakaznik_email']})</div>";
        echo "<div class='metadata-label'>Cena:</div><div class='metadata-value'>{$n['celkova_cena']} {$n['mena']}</div>";
        echo "<div class='metadata-label'>Stav:</div><div class='metadata-value'>{$n['stav']}</div>";
        echo "<div class='metadata-label'>Vytvořeno:</div><div class='metadata-value'>{$n['vytvoreno_at']}</div>";
        echo "</div>";

        // Kontrola polozky_json
        $polozkyJson = $n['polozky_json'];

        if (empty($polozkyJson)) {
            echo "<div class='error'><strong>PROBLÉM:</strong> polozky_json je PRÁZDNÝ!</div>";
            continue;
        }

        echo "<div class='info'><strong>Délka JSON:</strong> " . strlen($polozkyJson) . " znaků</div>";

        // Zkusit dekódovat JSON
        $polozky = json_decode($polozkyJson, true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            echo "<div class='error'><strong>CHYBA DEKÓDOVÁNÍ JSON:</strong> " . json_last_error_msg() . "</div>";
            echo "<div class='json'>" . htmlspecialchars($polozkyJson) . "</div>";
            continue;
        }

        // JSON je validní
        if (!is_array($polozky)) {
            echo "<div class='error'><strong>PROBLÉM:</strong> JSON není pole!</div>";
            echo "<div class='json'>" . htmlspecialchars($polozkyJson) . "</div>";
            continue;
        }

        if (empty($polozky)) {
            echo "<div class='error'><strong>PROBLÉM:</strong> Pole položek je PRÁZDNÉ! []</div>";
            echo "<div class='json'>" . htmlspecialchars(json_encode($polozky, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</div>";
            continue;
        }

        // Vše OK - zobrazit položky
        echo "<div class='success'><strong>✓ JSON validní</strong> - Počet položek: " . count($polozky) . "</div>";

        // Formátovaný JSON výpis
        echo "<div class='json'>" . htmlspecialchars(json_encode($polozky, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</div>";

        // Tabulka položek
        echo "<h3>Rozpis položek:</h3>";
        echo "<table style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background: #333; color: white;'>
            <th style='padding: 10px; text-align: left;'>Název</th>
            <th style='padding: 10px; text-align: center;'>Počet</th>
            <th style='padding: 10px; text-align: right;'>Cena/ks</th>
            <th style='padding: 10px; text-align: right;'>Celkem</th>
            <th style='padding: 10px; text-align: left;'>Skupina</th>
        </tr>";

        $celkemKontrola = 0;
        foreach ($polozky as $p) {
            $nazev = $p['nazev'] ?? 'N/A';
            $pocet = $p['pocet'] ?? 1;
            $cena = $p['cena'] ?? 0;
            $skupina = $p['skupina'] ?? 'N/A';
            $celkemRadek = $cena * $pocet;
            $celkemKontrola += $celkemRadek;

            echo "<tr style='border-bottom: 1px solid #ddd;'>
                <td style='padding: 8px;'>" . htmlspecialchars($nazev) . "</td>
                <td style='padding: 8px; text-align: center;'>{$pocet}</td>
                <td style='padding: 8px; text-align: right;'>" . number_format($cena, 2) . " {$n['mena']}</td>
                <td style='padding: 8px; text-align: right; font-weight: bold;'>" . number_format($celkemRadek, 2) . " {$n['mena']}</td>
                <td style='padding: 8px;'>{$skupina}</td>
            </tr>";
        }

        echo "<tr style='background: #f5f5f5; font-weight: bold;'>
            <td colspan='3' style='padding: 10px; text-align: right;'>CELKEM:</td>
            <td style='padding: 10px; text-align: right;'>" . number_format($celkemKontrola, 2) . " {$n['mena']}</td>
            <td></td>
        </tr>";
        echo "</table>";

        // Kontrola shodnosti ceny
        $rozdil = abs($celkemKontrola - floatval($n['celkova_cena']));
        if ($rozdil > 0.01) {
            echo "<div class='error'><strong>VAROVÁNÍ:</strong> Součet položek ({$celkemKontrola}) se NESHODUJE s celkovou cenou ({$n['celkova_cena']})!</div>";
        }

        echo "</div>"; // konec nabidka
    }

} catch (Exception $e) {
    echo "<div class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
