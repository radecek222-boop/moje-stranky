<?php
/**
 * Interaktivní výběr stránek pro čistý klon
 *
 * Tento skript zobrazí VŠECHNY PHP stránky v root složce
 * a umožní vybrat, které zkopírovat do čistého klonu.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Výběr stránek pro čistý klon</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white;
                     padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; position: sticky; top: 0; }
        tr:hover { background: #f9f9f9; }
        .checkbox { width: 30px; text-align: center; }
        .filename { font-weight: bold; color: #333; }
        .size { color: #666; }
        .recommended { background: #d4edda; }
        .optional { background: #fff3cd; }
        .legacy { background: #f8d7da; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px;
               cursor: pointer; border: none; font-size: 1em; }
        .btn:hover { background: #555; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .legend { display: flex; gap: 20px; margin: 20px 0; padding: 15px;
                  background: #f9f9f9; border-radius: 5px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-box { width: 20px; height: 20px; border-radius: 3px; }
        .actions { position: sticky; top: 0; background: white; padding: 15px 0;
                   z-index: 10; border-bottom: 2px solid #333; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #333; }
        .stat-label { color: #666; font-size: 0.9em; }
    </style>
    <script>
        function selectAll() {
            document.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => cb.checked = true);
            updateStats();
        }
        function deselectAll() {
            document.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => cb.checked = false);
            updateStats();
        }
        function selectRecommended() {
            document.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => {
                cb.checked = cb.dataset.category === 'recommended';
            });
            updateStats();
        }
        function updateStats() {
            const checked = document.querySelectorAll('input[type=\"checkbox\"]:checked').length;
            const total = document.querySelectorAll('input[type=\"checkbox\"]').length;
            let totalSize = 0;
            document.querySelectorAll('input[type=\"checkbox\"]:checked').forEach(cb => {
                totalSize += parseInt(cb.dataset.size || 0);
            });
            document.getElementById('stat-selected').textContent = checked;
            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-size').textContent = (totalSize / 1024).toFixed(1) + ' MB';
        }
        function generateCode() {
            const selected = [];
            document.querySelectorAll('input[type=\"checkbox\"]:checked').forEach(cb => {
                selected.push(cb.value);
            });
            document.getElementById('selected-files').value = selected.join(',');
            document.getElementById('generate-form').submit();
        }
    </script>
</head>
<body>
<div class='container'>";

echo "<h1>📋 Výběr stránek pro čistý klon</h1>";

$projektRoot = __DIR__;
$rootFiles = glob($projektRoot . '/*.php');
sort($rootFiles);

// Kategorizace souborů
$kategorieStranky = [
    'recommended' => [
        'index.php', 'seznam.php', 'novareklamace.php', 'statistiky.php', 'admin.php',
        'login.php', 'logout.php', 'protokol.php', 'cenik.php', 'cenova-nabidka.php',
        'analytics.php', 'aktuality.php', 'registration.php', 'password_reset.php',
        'init.php', 'gdpr.php', 'hry.php', 'transport.php'
    ],
    'optional' => [
        'nova_aktualita.php', 'photocustomer.php', 'potvrzeni-nabidky.php',
        'qr-kontakt.php', 'gdpr-zadost.php', 'sw.php', 'offline.php', 'health.php',
        'cookies.php', 'podminky.php', 'onas.php', 'nasesluzby.php'
    ]
];

// Počítat statistiky
$stats = [
    'total' => 0,
    'recommended' => 0,
    'optional' => 0,
    'legacy' => 0,
    'totalSize' => 0
];

$soubory = [];

foreach ($rootFiles as $file) {
    $basename = basename($file);
    $size = filesize($file);
    $stats['totalSize'] += $size;
    $stats['total']++;

    // Určit kategorii
    $kategorie = 'legacy';
    if (in_array($basename, $kategorieStranky['recommended'])) {
        $kategorie = 'recommended';
        $stats['recommended']++;
    } elseif (in_array($basename, $kategorieStranky['optional'])) {
        $kategorie = 'optional';
        $stats['optional']++;
    } elseif (strpos($basename, 'pridej_') === 0 || strpos($basename, 'oprav_') === 0 ||
              strpos($basename, 'migrace_') === 0 || strpos($basename, 'test_') === 0 ||
              strpos($basename, 'zkontroluj_') === 0 || strpos($basename, 'diagnostika_') === 0) {
        $kategorie = 'legacy';
        $stats['legacy']++;
    }

    $soubory[] = [
        'name' => $basename,
        'size' => $size,
        'category' => $kategorie
    ];
}

// Zobrazit statistiky
echo "<div class='stats'>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['total']}</div><div class='stat-label'>Celkem souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['recommended']}</div><div class='stat-label'>Doporučené</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['optional']}</div><div class='stat-label'>Volitelné</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['legacy']}</div><div class='stat-label'>Legacy/Migrace</div></div>";
echo "</div>";

// Legenda
echo "<div class='legend'>";
echo "<div class='legend-item'><div class='legend-box' style='background: #d4edda;'></div>Doporučené (nutné pro běh systému)</div>";
echo "<div class='legend-item'><div class='legend-box' style='background: #fff3cd;'></div>Volitelné (můžete vybrat)</div>";
echo "<div class='legend-item'><div class='legend-box' style='background: #f8d7da;'></div>Legacy/Migrace (nedoporučeno)</div>";
echo "</div>";

// Akce
echo "<div class='actions'>";
echo "<button class='btn' onclick='selectAll()'>✓ Vybrat vše</button>";
echo "<button class='btn' onclick='deselectAll()'>✗ Zrušit výběr</button>";
echo "<button class='btn btn-success' onclick='selectRecommended()'>⚡ Vybrat doporučené</button>";
echo "<button class='btn btn-success' onclick='generateCode()'>🚀 Vygenerovat konfiguraci</button>";
echo "<div style='margin-top: 10px;'>";
echo "<strong>Vybráno:</strong> <span id='stat-selected'>0</span> / <span id='stat-total'>{$stats['total']}</span> souborů, ";
echo "<strong>Velikost:</strong> <span id='stat-size'>0 MB</span>";
echo "</div>";
echo "</div>";

// Tabulka souborů
echo "<table>";
echo "<tr>";
echo "<th class='checkbox'>✓</th>";
echo "<th>Soubor</th>";
echo "<th>Velikost</th>";
echo "<th>Kategorie</th>";
echo "</tr>";

foreach ($soubory as $soubor) {
    $checked = $soubor['category'] === 'recommended' ? 'checked' : '';
    $rowClass = $soubor['category'];

    echo "<tr class='{$rowClass}'>";
    echo "<td class='checkbox'><input type='checkbox' name='files[]' value='{$soubor['name']}' data-category='{$soubor['category']}' data-size='{$soubor['size']}' {$checked} onchange='updateStats()'></td>";
    echo "<td class='filename'>{$soubor['name']}</td>";
    echo "<td class='size'>" . round($soubor['size'] / 1024, 1) . " KB</td>";
    echo "<td>";
    if ($soubor['category'] === 'recommended') {
        echo "✅ Doporučené";
    } elseif ($soubor['category'] === 'optional') {
        echo "⚡ Volitelné";
    } else {
        echo "🗑️ Legacy";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Skrytý formulář
echo "<form id='generate-form' method='post' action='generuj_konfiguraci_klonu.php'>";
echo "<input type='hidden' name='selected_files' id='selected-files'>";
echo "</form>";

echo "<script>updateStats();</script>";

echo "</div></body></html>";
?>
