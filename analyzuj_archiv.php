<?php
/**
 * Analýza složky archiv/ - Co se používá?
 *
 * Tento skript analyzuje složku archiv/ a zjistí:
 * 1. Které soubory jsou v archivu
 * 2. Jestli se na ně odkazuje v aktivním kódu
 * 3. Které lze bezpečně smazat
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
    <title>Analýza složky archiv/</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; background: white;
                     padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center;
                    border: 2px solid #ddd; }
        .stat-number { font-size: 2em; font-weight: bold; color: #333; }
        .stat-label { color: #666; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.85em; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; position: sticky; top: 0; }
        tr:hover { background: #f9f9f9; }
        .safe { background: #d4edda; }
        .used { background: #fff3cd; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px;
               cursor: pointer; border: none; font-weight: bold; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 15px; border-radius: 5px; margin: 20px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404;
                   padding: 15px; border-radius: 5px; margin: 20px 0; }
        .loading { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Analýza složky archiv/</h1>";

$archivDir = __DIR__ . '/archiv';
$projektRoot = __DIR__;

// Načíst všechny soubory z archivu
$archivFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($archivDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($archivDir . '/', '', $file->getPathname());
        $archivFiles[] = [
            'name' => $relativePath,
            'path' => $file->getPathname(),
            'size' => $file->getSize(),
            'type' => strtolower($file->getExtension())
        ];
    }
}

// Statistiky
$stats = [
    'total' => count($archivFiles),
    'totalSize' => array_sum(array_column($archivFiles, 'size')),
    'php' => 0,
    'sql' => 0,
    'js' => 0,
    'md' => 0,
    'other' => 0
];

foreach ($archivFiles as $file) {
    switch ($file['type']) {
        case 'php': $stats['php']++; break;
        case 'sql': $stats['sql']++; break;
        case 'js': $stats['js']++; break;
        case 'md': $stats['md']++; break;
        default: $stats['other']++; break;
    }
}

echo "<div class='stats'>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['total']}</div><div class='stat-label'>Celkem souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>" . round($stats['totalSize'] / 1024 / 1024, 2) . " MB</div><div class='stat-label'>Celková velikost</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['php']}</div><div class='stat-label'>PHP souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>" . ($stats['sql'] + $stats['js'] + $stats['md'] + $stats['other']) . "</div><div class='stat-label'>Ostatní</div></div>";
echo "</div>";

echo "<div class='info'>";
echo "<strong>🔍 Kontrola použití:</strong> Skript kontroluje, jestli se na soubory z archivu odkazuje v aktivním kódu.<br>";
echo "✅ <strong>Bezpečné k smazání</strong> = Žádný odkaz nenalezen<br>";
echo "⚠️ <strong>Možná používáno</strong> = Nalezen odkaz (require, include, nebo zmínka)";
echo "</div>";

echo "<div class='loading'>⏳ Kontroluji odkazy v kódu... (může to trvat chvíli)</div>";

flush();
ob_flush();

// Zkontrolovat odkazy na soubory z archivu
$aktivniSoubory = [];
$aktivniIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projektRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($aktivniIterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();

        // Přeskočit samotný archiv
        if (strpos($path, '/archiv/') !== false) continue;
        if (strpos($path, '/vendor/') !== false) continue;
        if (strpos($path, '/node_modules/') !== false) continue;
        if (strpos($path, '/backups/') !== false) continue;

        $aktivniSoubory[] = $path;
    }
}

// Projít soubory z archivu a zkontrolovat odkazy
$vysledky = [];
$bezpecneSmazat = 0;
$moznaPoužívano = 0;

foreach ($archivFiles as $archivFile) {
    $fileName = basename($archivFile['name']);
    $found = false;
    $foundIn = [];

    // Hledat jméno souboru v aktivních souborech (bez archiv/ prefixy)
    foreach ($aktivniSoubory as $aktivniSoubor) {
        $content = @file_get_contents($aktivniSoubor);
        if ($content && stripos($content, $fileName) !== false) {
            $found = true;
            $foundIn[] = basename($aktivniSoubor);
            if (count($foundIn) >= 3) break; // Max 3 příklady
        }
    }

    $vysledky[] = [
        'file' => $archivFile,
        'used' => $found,
        'foundIn' => $foundIn
    ];

    if ($found) {
        $moznaPoužívano++;
    } else {
        $bezpecneSmazat++;
    }
}

// Smazat loading zprávu
echo "<script>document.querySelector('.loading').remove();</script>";

echo "<div class='stats'>";
echo "<div class='stat-box' style='border-color: #28a745;'><div class='stat-number' style='color: #28a745;'>{$bezpecneSmazat}</div><div class='stat-label'>Bezpečné k smazání</div></div>";
echo "<div class='stat-box' style='border-color: #ffc107;'><div class='stat-number' style='color: #ffc107;'>{$moznaPoužívano}</div><div class='stat-label'>Možná používáno</div></div>";
echo "</div>";

if ($bezpecneSmazat > 0) {
    $usporaSize = 0;
    foreach ($vysledky as $v) {
        if (!$v['used']) {
            $usporaSize += $v['file']['size'];
        }
    }

    echo "<div class='warning'>";
    echo "<strong>💾 Potenciální úspora:</strong> " . round($usporaSize / 1024 / 1024, 2) . " MB ({$bezpecneSmazat} souborů)";
    echo "</div>";
}

// Tabulka výsledků
echo "<table>";
echo "<tr><th>Soubor</th><th>Typ</th><th>Velikost</th><th>Status</th><th>Nalezeno v</th></tr>";

// Seřadit - nejdřív bezpečné k smazání
usort($vysledky, function($a, $b) {
    if ($a['used'] === $b['used']) {
        return $b['file']['size'] - $a['file']['size']; // Větší soubory první
    }
    return $a['used'] ? 1 : -1; // Nepoužívané první
});

foreach ($vysledky as $v) {
    $file = $v['file'];
    $rowClass = $v['used'] ? 'used' : 'safe';
    $status = $v['used'] ? '⚠️ Možná používáno' : '✅ Bezpečné smazat';
    $foundInText = $v['used'] ? implode(', ', array_slice($v['foundIn'], 0, 3)) : '—';

    echo "<tr class='{$rowClass}'>";
    echo "<td><strong>{$file['name']}</strong></td>";
    echo "<td>{$file['type']}</td>";
    echo "<td>" . round($file['size'] / 1024, 1) . " KB</td>";
    echo "<td>{$status}</td>";
    echo "<td style='font-size: 0.8em; color: #666;'>{$foundInText}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🗑️ Smazání archivu</h2>";
echo "<div class='warning'>";
echo "<strong>⚠️ DOPORUČENÍ:</strong><br>";
echo "• Většina souborů v archivu je pravděpodobně legacy kód<br>";
echo "• Před smazáním bude vytvořena ZÁLOHA<br>";
echo "• Můžete smazat celou složku archiv/ najednou";
echo "</div>";

echo "<form method='POST' action='smaz_archiv.php' onsubmit='return confirm(\"Opravdu SMAZAT celou složku archiv/ ({$stats['total']} souborů, " . round($stats['totalSize'] / 1024 / 1024, 2) . " MB)?\\n\\nPřed smazáním bude vytvořena záloha.\");'>";
echo "<button type='submit' class='btn btn-danger'>🔥 SMAZAT CELOU SLOŽKU ARCHIV/</button>";
echo "<a href='/admin.php' class='btn'>← Zpět</a>";
echo "</form>";

echo "</div></body></html>";
?>
