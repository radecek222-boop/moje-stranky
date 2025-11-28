<?php
/**
 * MEDIUM PRIORITY: Cleanup Zakomentovaného Kódu
 *
 * Detekuje a volitelně odstraňuje zakomentovaný kód
 *
 * POZOR: Tento skript JEN DETEKUJE problematické soubory
 * Automatické mazání NENÍ zapnuté - bezpečnost především!
 *
 * Použití: php scripts/cleanup_commented_code.php
 */

require_once __DIR__ . '/../init.php';

// SECURITY: Admin check
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Admin přístup vyžadován']));
    }
}

echo "🧹 Cleanup Zakomentovaného Kódu\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git', 'docs'];

// Najít PHP soubory
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();

        $excluded = false;
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($path, '/' . $excludeDir . '/') !== false) {
                $excluded = true;
                break;
            }
        }

        if (!$excluded) {
            $files[] = $path;
        }
    }
}

echo "Skenuji " . count($files) . " PHP souborů...\n\n";

$results = [];
$totalCommentedLines = 0;

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    $commentedCodeLines = 0;
    $commentBlocks = [];
    $currentBlock = null;

    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);

        // Detekovat zakomentovaný kód (ne dokumentace!)
        // Heuristika: řádek začínající // nebo /* a obsahující typický kód
        $isCommentedCode = false;

        // Jednořádkové komentáře s kódem
        if (preg_match('/^\/\/\s*(\$|if|for|while|function|return|echo|\{|\}|;)/', $trimmed)) {
            $isCommentedCode = true;
        }

        // Víceřádkové komentáře (bez PHPDoc)
        if (preg_match('/^\/\*(?!\*)/', $trimmed)) {
            $currentBlock = ['start' => $lineNum + 1, 'lines' => []];
        }

        if ($currentBlock !== null) {
            $currentBlock['lines'][] = $trimmed;

            if (preg_match('/\*\//', $trimmed)) {
                // Konec bloku - zkontrolovat jestli obsahuje kód
                $blockText = implode(' ', $currentBlock['lines']);
                if (preg_match('/(\$|if\s*\(|for\s*\(|while\s*\(|function\s+|\{|\}|;)/', $blockText)) {
                    $commentBlocks[] = $currentBlock;
                    $commentedCodeLines += count($currentBlock['lines']);
                }
                $currentBlock = null;
            }
        }

        if ($isCommentedCode) {
            $commentedCodeLines++;
        }
    }

    if ($commentedCodeLines > 0) {
        $results[$relativePath] = [
            'lines' => $commentedCodeLines,
            'blocks' => $commentBlocks
        ];
        $totalCommentedLines += $commentedCodeLines;
    }
}

// Seřadit podle počtu řádků
uasort($results, function($a, $b) {
    return $b['lines'] - $a['lines'];
});

// Výsledky
echo "📊 VÝSLEDKY:\n";
echo str_repeat("=", 70) . "\n";
echo "Celkem zakomentovaných řádků kódu: {$totalCommentedLines}\n";
echo "Souborů s zakomentovaným kódem: " . count($results) . "\n\n";

if (empty($results)) {
    echo "Žádný zakomentovaný kód nenalezen!\n";
} else {
    echo "🔴 SOUBORY S NEJVÍCE ZAKOMENTOVANÝM KÓDEM:\n";
    echo str_repeat("=", 70) . "\n\n";

    $count = 0;
    foreach ($results as $file => $data) {
        $count++;
        $icon = $data['lines'] > 100 ? '🔴' : ($data['lines'] > 50 ? '🟡' : '🟢');

        echo "{$icon} [{$count}] {$file}\n";
        echo "   Zakomentovaných řádků: {$data['lines']}\n";
        echo "   Bloků: " . count($data['blocks']) . "\n\n";

        if ($count >= 15) {
            $remaining = count($results) - 15;
            if ($remaining > 0) {
                echo "... a {$remaining} dalších souborů\n\n";
            }
            break;
        }
    }

    echo str_repeat("=", 70) . "\n";
    echo "💡 DOPORUČENÍ:\n";
    echo str_repeat("=", 70) . "\n\n";

    echo "1. PRIORITA:\n";
    echo "   🔴 HIGH (100+ řádků): Vyčistit co nejdříve\n";
    echo "   🟡 MEDIUM (50-100 řádků): Vyčistit brzy\n";
    echo "   🟢 LOW (<50 řádků): Vyčistit postupně\n\n";

    echo "2. JAK VYČISTIT:\n";
    echo "   a) Ručně projít každý soubor\n";
    echo "   b) Určit jestli je kód potřeba (ne = smazat)\n";
    echo "   c) Pokud ano, přesunout do /backups s datem\n";
    echo "   d) Smazat z hlavního souboru\n\n";

    echo "3. BENEFIT:\n";
    echo "   - Čistší kód (lepší čitelnost)\n";
    echo "   - Menší soubory\n";
    echo "   - Snazší maintenance\n";
    echo "   - Méně zmatků pro nové vývojáře\n\n";

    echo "⚠️  VAROVÁNÍ:\n";
    echo "   - NIKDY nemazat automaticky!\n";
    echo "   - Vždy ručně zkontrolovat co je zakomentováno\n";
    echo "   - Některé komentáře mohou být důležité poznámky\n";
    echo "   - Udělat backup před čištěním!\n";
}

// Uložit report
$reportFile = __DIR__ . '/commented_code_report.txt';
$report = "# Zakomentovaný Kód - Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
$report .= "# Celkem řádků: {$totalCommentedLines}\n";
$report .= "# Souborů: " . count($results) . "\n\n";

foreach ($results as $file => $data) {
    $report .= "## {$file} ({$data['lines']} řádků)\n";
    $report .= "Bloků: " . count($data['blocks']) . "\n\n";
}

file_put_contents($reportFile, $report);
echo "\n📝 Report uložen: scripts/commented_code_report.txt\n";

// Return JSON pro API
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total_lines' => $totalCommentedLines,
            'files_affected' => count($results)
        ],
        'results' => $results
    ], JSON_PRETTY_PRINT);
}
