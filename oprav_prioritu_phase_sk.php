<?php
/**
 * Rychlá oprava: Nastavit správnou prioritu pro PHASE SK
 *
 * PHASE SK mělo mít prioritu 90, ale zůstalo na 10.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava: Priorita PHASE SK</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Oprava priority PHASE SK</h1>";

    // Zobrazit současný stav
    $stmt = $pdo->query("
        SELECT zdroj, nazev, priorita
        FROM wgs_pdf_parser_configs
        WHERE zdroj IN ('natuzzi', 'phase', 'phase_cz')
        ORDER BY priorita DESC
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>📊 Současná priorita:</h2>";
    echo "<table>";
    echo "<tr><th>Zdroj</th><th>Název</th><th>Priorita</th></tr>";
    foreach ($configs as $config) {
        $highlight = ($config['zdroj'] === 'phase' && $config['priorita'] != 90) ? ' style="background: #fff3cd;"' : '';
        echo "<tr{$highlight}>";
        echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
        echo "<td>" . $config['priorita'] . ($config['zdroj'] === 'phase' && $config['priorita'] != 90 ? ' ⚠️ ŠPATNĚ' : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Opravit prioritu
    $stmt = $pdo->prepare("
        UPDATE wgs_pdf_parser_configs
        SET priorita = 90
        WHERE zdroj = 'phase'
    ");
    $stmt->execute();

    echo "<div class='success'>";
    echo "✅ Priorita PHASE SK nastavena na <strong>90</strong>";
    echo "</div>";

    // Zobrazit nový stav
    $stmt = $pdo->query("
        SELECT zdroj, nazev, priorita
        FROM wgs_pdf_parser_configs
        WHERE zdroj IN ('natuzzi', 'phase', 'phase_cz')
        ORDER BY priorita DESC
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>📊 Nová priorita:</h2>";
    echo "<table>";
    echo "<tr><th>Zdroj</th><th>Název</th><th>Priorita</th></tr>";
    foreach ($configs as $config) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
        echo "<td>" . $config['priorita'] . " ✅</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div class='info'>";
    echo "<strong>Správné pořadí:</strong><br>";
    echo "1. NATUZZI (100) - nejvyšší priorita<br>";
    echo "2. PHASE CZ (95) - střední priorita<br>";
    echo "3. PHASE SK (90) - nejnižší priorita";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
