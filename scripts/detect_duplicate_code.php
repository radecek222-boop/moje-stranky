<?php
/**
 * MEDIUM PRIORITY: Detekce Duplicitního Kódu
 *
 * Hledá duplicitní funkce a kód v projektu
 *
 * Použití: php scripts/detect_duplicate_code.php
 */

require_once __DIR__ . '/../init.php';

echo "🔍 Detekce Duplicitního Kódu\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git'];

// Najít všechny PHP soubory
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

echo "Analyzuji " . count($files) . " PHP souborů...\n\n";

// Extrahovat funkce z každého souboru
$functions = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    // Najít všechny funkce (function name(...))
    preg_match_all('/function\s+(\w+)\s*\([^)]*\)\s*\{/i', $content, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as $idx => $match) {
        $funcName = $match[0];
        $startPos = $matches[0][$idx][1];

        // Extrahovat tělo funkce (aproximace - prvních 500 znaků)
        $body = substr($content, $startPos, 500);

        // Vytvořit hash z funkce
        $normalizedBody = preg_replace('/\s+/', ' ', $body); // Normalizovat whitespace
        $normalizedBody = preg_replace('/\/\/.*/', '', $normalizedBody); // Odstranit komentáře
        $hash = md5($normalizedBody);

        if (!isset($functions[$funcName])) {
            $functions[$funcName] = [];
        }

        $functions[$funcName][] = [
            'file' => $relativePath,
            'hash' => $hash,
            'preview' => substr($body, 0, 100)
        ];
    }
}

// Najít duplicity
$duplicates = [];
$totalDuplicates = 0;

foreach ($functions as $funcName => $occurrences) {
    if (count($occurrences) > 1) {
        // Seskupit podle hashe
        $byHash = [];
        foreach ($occurrences as $occ) {
            if (!isset($byHash[$occ['hash']])) {
                $byHash[$occ['hash']] = [];
            }
            $byHash[$occ['hash']][] = $occ;
        }

        // Pokud nějaký hash má více souborů = duplicita
        foreach ($byHash as $hash => $files) {
            if (count($files) > 1) {
                $duplicates[$funcName] = $files;
                $totalDuplicates++;
                break;
            }
        }
    }
}

// Výsledky
echo "📊 VÝSLEDKY:\n";
echo str_repeat("=", 70) . "\n";
echo "Celkem funkcí: " . array_sum(array_map('count', $functions)) . "\n";
echo "Duplicitních funkcí: {$totalDuplicates}\n\n";

if (empty($duplicates)) {
    echo "Žádné duplicitní funkce nenalezeny!\n";
} else {
    echo "🔴 DUPLICITNÍ FUNKCE:\n";
    echo str_repeat("=", 70) . "\n\n";

    $priority = 1;
    foreach ($duplicates as $funcName => $occurrences) {
        echo "#{$priority} function {$funcName}() - " . count($occurrences) . "x kopie\n";

        foreach ($occurrences as $occ) {
            echo "   📄 {$occ['file']}\n";
        }
        echo "\n";
        $priority++;
    }

    echo "\n💡 DOPORUČENÍ:\n";
    echo str_repeat("=", 70) . "\n";
    echo "1. Přesunout duplicitní funkce do společného helper souboru\n";
    echo "2. Vytvořit includes/helpers.php nebo includes/common_functions.php\n";
    echo "3. Odstranit duplicity a používat require_once\n";
    echo "4. Benefit: Lepší maintainability, menší kód, jednodušší opravy\n";
}

// Uložit report
$reportFile = __DIR__ . '/duplicate_code_report.txt';
$report = "# Duplicitní Kód - Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
$report .= "# Duplicit nalezeno: {$totalDuplicates}\n\n";

foreach ($duplicates as $funcName => $occurrences) {
    $report .= "## function {$funcName}() - " . count($occurrences) . "x\n";
    foreach ($occurrences as $occ) {
        $report .= "- {$occ['file']}\n";
    }
    $report .= "\n";
}

file_put_contents($reportFile, $report);
echo "\n📝 Report uložen: scripts/duplicate_code_report.txt\n";
