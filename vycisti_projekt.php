<?php
/**
 * Vyčištění projektu - Smazání nepotřebných souborů
 *
 * Tento skript zobrazí všechny soubory v root složce a umožní vybrat,
 * které SMAZAT. Před smazáním vytvoří zálohu.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

// Zpracování smazání
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_files'])) {
    $filesToDelete = $_POST['files'] ?? [];

    if (!empty($filesToDelete)) {
        // Vytvoření zálohy
        $backupDir = __DIR__ . '/backups/cleanup_' . date('Y-m-d_H-i-s');
        mkdir($backupDir, 0755, true);

        echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Mazání souborů</title>";
        echo "<style>body{font-family:monospace;background:#000;color:#0f0;padding:20px;}.success{color:#0f0;}.error{color:#f00;}</style></head><body>";
        echo "<h1>Mazání souborů...</h1>";
        echo "<div style='background:#111;padding:20px;border-radius:5px;'>";

        $deleted = 0;
        $errors = 0;

        foreach ($filesToDelete as $file) {
            $filePath = __DIR__ . '/' . basename($file);

            // Bezpečnostní kontrola - pouze PHP soubory v root
            if (!file_exists($filePath) || !is_file($filePath) || dirname($filePath) !== __DIR__) {
                echo "<div class='error'>⊗ Přeskakuji: " . htmlspecialchars($file) . " (neplatná cesta)</div>";
                continue;
            }

            // Záloha před smazáním
            $backupPath = $backupDir . '/' . basename($file);
            if (copy($filePath, $backupPath)) {
                // Smazat soubor
                if (unlink($filePath)) {
                    echo "<div class='success'>✓ Smazáno: " . htmlspecialchars($file) . "</div>";
                    $deleted++;
                } else {
                    echo "<div class='error'>✗ Chyba při mazání: " . htmlspecialchars($file) . "</div>";
                    $errors++;
                }
            } else {
                echo "<div class='error'>✗ Chyba při zálohování: " . htmlspecialchars($file) . "</div>";
                $errors++;
            }

            flush();
            ob_flush();
        }

        echo "</div>";
        echo "<h2>Hotovo!</h2>";
        echo "<p><strong>Smazáno:</strong> {$deleted} souborů</p>";
        echo "<p><strong>Chyby:</strong> {$errors}</p>";
        echo "<p><strong>Záloha:</strong> {$backupDir}</p>";
        echo "<br><a href='/admin.php' style='color:#0f0;'>← Zpět do Admin panelu</a>";
        echo "</body></html>";
        exit;
    }
}

// Zobrazení formuláře
echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vyčištění projektu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white;
                     padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; color: #856404;
                   padding: 20px; border-radius: 10px; margin: 20px 0; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #dc3545; color: white; position: sticky; top: 0; }
        tr:hover { background: #f9f9f9; }
        .checkbox { width: 30px; text-align: center; }
        .filename { font-weight: bold; }
        .legacy { background: #f8d7da; }
        .active { background: #d4edda; }
        .btn { display: inline-block; padding: 15px 30px; background: #333;
               color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px;
               cursor: pointer; border: none; font-size: 1em; font-weight: bold; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center;
                    border: 2px solid #ddd; }
        .stat-number { font-size: 2em; font-weight: bold; color: #dc3545; }
        .stat-label { color: #666; font-size: 0.9em; }
        .actions { position: sticky; top: 0; background: white; padding: 15px 0;
                   z-index: 10; border-bottom: 2px solid #dc3545; margin-bottom: 20px; }
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
        function selectLegacy() {
            document.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => {
                cb.checked = cb.dataset.category === 'legacy';
            });
            updateStats();
        }
        function updateStats() {
            const checked = document.querySelectorAll('input[type=\"checkbox\"]:checked').length;
            let totalSize = 0;
            document.querySelectorAll('input[type=\"checkbox\"]:checked').forEach(cb => {
                totalSize += parseInt(cb.dataset.size || 0);
            });
            document.getElementById('stat-selected').textContent = checked;
            document.getElementById('stat-size').textContent = (totalSize / 1024).toFixed(1) + ' KB';
        }
        function confirmDelete() {
            const count = document.querySelectorAll('input[type=\"checkbox\"]:checked').length;
            if (count === 0) {
                alert('Nevybrali jste žádné soubory ke smazání!');
                return false;
            }
            return confirm('OPRAVDU chcete SMAZAT ' + count + ' souborů?\\n\\nPřed smazáním bude vytvořena záloha.\\n\\nPokračovat?');
        }
    </script>
</head>
<body>
<div class='container'>";

echo "<h1>🗑️ Vyčištění projektu - Smazání nepotřebných souborů</h1>";

echo "<div class='warning'>";
echo "⚠️ <strong>VAROVÁNÍ:</strong> Tento skript SMAŽE vybrané soubory z původního projektu!<br><br>";
echo "✅ <strong>Před smazáním bude vytvořena ZÁLOHA</strong> do složky /backups/<br>";
echo "✅ Můžete kdykoliv obnovit smazané soubory ze zálohy<br>";
echo "✅ Doporučujeme vybrat POUZE legacy soubory (migrace, testy, diagnostika)<br>";
echo "</div>";

$projektRoot = __DIR__;
$rootFiles = glob($projektRoot . '/*.php');
sort($rootFiles);

// Kategorizace
$aktivniStranky = [
    'index.php', 'seznam.php', 'novareklamace.php', 'statistiky.php', 'admin.php',
    'login.php', 'logout.php', 'protokol.php', 'cenik.php', 'cenova-nabidka.php',
    'analytics.php', 'aktuality.php', 'registration.php', 'password_reset.php',
    'init.php', 'gdpr.php', 'gdpr-zadost.php', 'hry.php', 'transport.php',
    'nova_aktualita.php', 'photocustomer.php', 'potvrzeni-nabidky.php',
    'qr-kontakt.php', 'sw.php', 'offline.php', 'health.php'
];

$stats = ['total' => 0, 'active' => 0, 'legacy' => 0, 'totalSize' => 0, 'legacySize' => 0];
$soubory = [];

foreach ($rootFiles as $file) {
    $basename = basename($file);
    $size = filesize($file);
    $stats['totalSize'] += $size;
    $stats['total']++;

    // Určit kategorii
    $kategorie = 'legacy';
    if (in_array($basename, $aktivniStranky)) {
        $kategorie = 'active';
        $stats['active']++;
    } else {
        $stats['legacy']++;
        $stats['legacySize'] += $size;
    }

    $soubory[] = [
        'name' => $basename,
        'size' => $size,
        'category' => $kategorie
    ];
}

// Statistiky
echo "<div class='stats'>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['total']}</div><div class='stat-label'>Celkem souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['active']}</div><div class='stat-label'>Aktivní (ponechat)</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['legacy']}</div><div class='stat-label'>Legacy (smazat)</div></div>";
echo "</div>";

echo "<p><strong>💾 Potenciální úspora:</strong> " . round($stats['legacySize'] / 1024, 1) . " KB (" . $stats['legacy'] . " souborů)</p>";

// Formulář
echo "<form method='POST' onsubmit='return confirmDelete();'>";
echo "<input type='hidden' name='delete_files' value='1'>";

// Akce
echo "<div class='actions'>";
echo "<button type='button' class='btn' onclick='selectAll()'>✓ Vybrat vše</button>";
echo "<button type='button' class='btn' onclick='deselectAll()'>✗ Zrušit výběr</button>";
echo "<button type='button' class='btn btn-danger' onclick='selectLegacy()'>🗑️ Vybrat legacy</button>";
echo "<button type='submit' class='btn btn-danger'>🔥 SMAZAT VYBRANÉ</button>";
echo "<a href='/admin.php' class='btn'>Zrušit</a>";
echo "<div style='margin-top: 10px; font-weight: bold;'>";
echo "Vybráno ke smazání: <span id='stat-selected' style='color:#dc3545;'>0</span> souborů, ";
echo "Velikost: <span id='stat-size' style='color:#dc3545;'>0 KB</span>";
echo "</div>";
echo "</div>";

// Tabulka
echo "<table>";
echo "<tr><th class='checkbox'>❌</th><th>Soubor</th><th>Velikost</th><th>Status</th></tr>";

foreach ($soubory as $soubor) {
    $checked = $soubor['category'] === 'legacy' ? 'checked' : '';
    $rowClass = $soubor['category'];

    echo "<tr class='{$rowClass}'>";
    echo "<td class='checkbox'><input type='checkbox' name='files[]' value='{$soubor['name']}' data-category='{$soubor['category']}' data-size='{$soubor['size']}' {$checked} onchange='updateStats()'></td>";
    echo "<td class='filename'>{$soubor['name']}</td>";
    echo "<td>" . round($soubor['size'] / 1024, 1) . " KB</td>";
    echo "<td>";
    if ($soubor['category'] === 'active') {
        echo "✅ Aktivní (PONECHAT)";
    } else {
        echo "🗑️ Legacy (SMAZAT)";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</form>";

echo "<script>updateStats();</script>";

echo "</div></body></html>";
?>
