<?php
/**
 * LOW PRIORITY: Documentation Quality Check
 *
 * Kontroluje kvalitu dokumentace v projektu
 * - PHPDoc komentáře u funkcí
 * - README soubory
 * - Inline dokumentaci
 *
 * Použití: php scripts/improve_documentation.php
 */

echo "📚 Documentation Quality Check\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git'];

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

echo "Analyzuji " . count($files) . " PHP souborů...\n\n";

$stats = [
    'functions_without_doc' => [],
    'classes_without_doc' => [],
    'complex_functions_without_doc' => [],
    'files_without_header' => [],
];

$totalFunctions = 0;
$documentedFunctions = 0;
$totalClasses = 0;
$documentedClasses = 0;

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    // Zkontrolovat file header
    $hasFileHeader = false;
    if (preg_match('/^<\?php\s*\n\/\*\*/', $content)) {
        $hasFileHeader = true;
    }

    if (!$hasFileHeader && !preg_match('/^<\?php\s*\n\s*$/', $content)) {
        $stats['files_without_header'][] = $relativePath;
    }

    // Najít funkce a zkontrolovat dokumentaci
    preg_match_all('/(?:\/\*\*.*?\*\/\s*)?(function\s+(\w+)\s*\([^)]*\)\s*\{)/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($matches as $match) {
        $totalFunctions++;
        $fullMatch = $match[0][0];
        $funcName = $match[2][0];

        // Zkontrolovat jestli má PHPDoc
        if (preg_match('/\/\*\*.*?\*\//s', $fullMatch)) {
            $documentedFunctions++;
        } else {
            // Spočítat řádky funkce (aproximace)
            $funcStart = $match[0][1];
            $bracketCount = 1;
            $pos = $funcStart + strlen($match[1][0]);
            $funcLines = 1;

            while ($pos < strlen($content) && $bracketCount > 0) {
                if ($content[$pos] === '{') $bracketCount++;
                if ($content[$pos] === '}') $bracketCount--;
                if ($content[$pos] === "\n") $funcLines++;
                $pos++;
            }

            $issue = [
                'file' => $relativePath,
                'function' => $funcName,
                'lines' => $funcLines
            ];

            // Komplexní funkce (více než 20 řádků) bez dokumentace
            if ($funcLines > 20) {
                $stats['complex_functions_without_doc'][] = $issue;
            }

            $stats['functions_without_doc'][] = $issue;
        }
    }

    // Najít třídy a zkontrolovat dokumentaci
    preg_match_all('/(?:\/\*\*.*?\*\/\s*)?(class\s+(\w+))/s', $content, $classMatches, PREG_SET_ORDER);

    foreach ($classMatches as $match) {
        $totalClasses++;
        $fullMatch = $match[0];

        if (preg_match('/\/\*\*.*?\*\//s', $fullMatch)) {
            $documentedClasses++;
        } else {
            $stats['classes_without_doc'][] = [
                'file' => $relativePath,
                'class' => $match[2]
            ];
        }
    }
}

// Najít README soubory
$readmeFiles = [];
$readmeDirs = [];

$dirIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($dirIterator as $item) {
    if ($item->isFile() && strtolower($item->getFilename()) === 'readme.md') {
        $path = str_replace($projectRoot . '/', '', $item->getPathname());
        $readmeFiles[] = $path;
    }
    if ($item->isDir()) {
        $path = str_replace($projectRoot . '/', '', $item->getPathname());

        $excluded = false;
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($path, $excludeDir) === 0) {
                $excluded = true;
                break;
            }
        }

        if (!$excluded) {
            $readmeDirs[] = $path;
        }
    }
}

// Adresáře bez README
$dirsWithoutReadme = [];
$importantDirs = ['api', 'app', 'includes', 'scripts', 'docs'];

foreach ($importantDirs as $dir) {
    $hasReadme = false;
    foreach ($readmeFiles as $readme) {
        if (strpos($readme, $dir . '/') === 0) {
            $hasReadme = true;
            break;
        }
    }
    if (!$hasReadme && is_dir($projectRoot . '/' . $dir)) {
        $dirsWithoutReadme[] = $dir;
    }
}

// Výsledky
echo "📊 STATISTIKY:\n";
echo str_repeat("=", 70) . "\n\n";

echo "FUNKCE:\n";
echo "  Celkem: {$totalFunctions}\n";
echo "  Dokumentované: {$documentedFunctions}\n";
$funcCoverage = $totalFunctions > 0 ? round(($documentedFunctions / $totalFunctions) * 100, 1) : 0;
echo "  Coverage: {$funcCoverage}%\n";
echo "  Bez dokumentace: " . count($stats['functions_without_doc']) . "\n";
echo "  Komplexní bez dokumentace: " . count($stats['complex_functions_without_doc']) . "\n\n";

echo "TŘÍDY:\n";
echo "  Celkem: {$totalClasses}\n";
echo "  Dokumentované: {$documentedClasses}\n";
$classCoverage = $totalClasses > 0 ? round(($documentedClasses / $totalClasses) * 100, 1) : 0;
echo "  Coverage: {$classCoverage}%\n";
echo "  Bez dokumentace: " . count($stats['classes_without_doc']) . "\n\n";

echo "SOUBORY:\n";
echo "  Celkem PHP: " . count($files) . "\n";
echo "  Bez file header: " . count($stats['files_without_header']) . "\n\n";

echo "README:\n";
echo "  Nalezené README: " . count($readmeFiles) . "\n";
echo "  Důležité adresáře bez README: " . count($dirsWithoutReadme) . "\n\n";

// Detailní výsledky
if (!empty($stats['complex_functions_without_doc'])) {
    echo "🔴 KOMPLEXNÍ FUNKCE BEZ DOKUMENTACE (priorita):\n";
    echo str_repeat("=", 70) . "\n\n";

    usort($stats['complex_functions_without_doc'], function($a, $b) {
        return $b['lines'] - $a['lines'];
    });

    $count = 0;
    foreach ($stats['complex_functions_without_doc'] as $func) {
        $count++;
        echo "#{$count} {$func['function']}() - {$func['lines']} řádků\n";
        echo "   📄 {$func['file']}\n\n";

        if ($count >= 10) {
            $remaining = count($stats['complex_functions_without_doc']) - 10;
            if ($remaining > 0) {
                echo "... a {$remaining} dalších\n\n";
            }
            break;
        }
    }
}

if (!empty($dirsWithoutReadme)) {
    echo "📁 DŮLEŽITÉ ADRESÁŘE BEZ README:\n";
    echo str_repeat("=", 70) . "\n\n";

    foreach ($dirsWithoutReadme as $dir) {
        echo "  - {$dir}/\n";
    }
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "💡 DOPORUČENÍ:\n";
echo str_repeat("=", 70) . "\n\n";

echo "1. PRIORITA - Dokumentovat:\n";
echo "   - Komplexní funkce (20+ řádků)\n";
echo "   - Všechny public API funkce\n";
echo "   - Všechny třídy\n\n";

echo "2. PHPDoc TEMPLATE:\n";
echo "   /**\n";
echo "    * Krátký popis funkce\n";
echo "    *\n";
echo "    * @param string \$param Popis parametru\n";
echo "    * @return array Popis návratové hodnoty\n";
echo "    * @throws Exception Kdy může hodit exception\n";
echo "    */\n\n";

echo "3. README v důležitých adresářích:\n";
foreach ($dirsWithoutReadme as $dir) {
    echo "   - {$dir}/README.md\n";
}
echo "\n";

echo "4. FILE HEADER TEMPLATE:\n";
echo "   <?php\n";
echo "   /**\n";
echo "    * Název souboru\n";
echo "    * Krátký popis účelu souboru\n";
echo "    */\n\n";

// Uložit report
$reportFile = __DIR__ . '/documentation_report.txt';
$report = "# Documentation Quality Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n\n";

$report .= "## Statistiky:\n\n";
$report .= "Funkce: {$documentedFunctions}/{$totalFunctions} ({$funcCoverage}%)\n";
$report .= "Třídy: {$documentedClasses}/{$totalClasses} ({$classCoverage}%)\n";
$report .= "Soubory bez headeru: " . count($stats['files_without_header']) . "\n\n";

$report .= "## Komplexní funkce bez dokumentace:\n\n";
foreach ($stats['complex_functions_without_doc'] as $func) {
    $report .= "{$func['function']}() - {$func['lines']} řádků\n";
    $report .= "  File: {$func['file']}\n\n";
}

$report .= "\n## Adresáře bez README:\n\n";
foreach ($dirsWithoutReadme as $dir) {
    $report .= "- {$dir}/\n";
}

file_put_contents($reportFile, $report);
echo "📝 Report uložen: scripts/documentation_report.txt\n";
