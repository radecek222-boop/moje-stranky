<?php
/**
 * LOW PRIORITY: Minor Optimizations Detection
 *
 * Detekuje drobné optimalizační příležitosti:
 * - Neoptimální loops
 * - Zbytečné array_* volání
 * - Opakované string operace
 * - Neefektivní conditionals
 *
 * Použití: php scripts/minor_optimizations.php
 */

echo "⚡ Minor Optimizations Detection\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git', 'setup'];

// Optimalizační patterny
$patterns = [
    [
        'name' => 'count() v loop podmínce',
        'pattern' => '/for\s*\([^;]*;\s*[^;]*<\s*count\s*\(/i',
        'issue' => 'count() volán každou iteraci',
        'fix' => '$count = count($array); for (...; $i < $count; ...)',
        'severity' => 'MEDIUM'
    ],
    [
        'name' => 'strlen() v loop podmínce',
        'pattern' => '/for\s*\([^;]*;\s*[^;]*<\s*strlen\s*\(/i',
        'issue' => 'strlen() volán každou iteraci',
        'fix' => '$len = strlen($str); for (...; $i < $len; ...)',
        'severity' => 'LOW'
    ],
    [
        'name' => 'array_key_exists s in_array',
        'pattern' => '/in_array\s*\([^,]+,\s*array_keys\s*\(/i',
        'issue' => 'Neefektivní - array_keys vytváří nové pole',
        'fix' => 'array_key_exists($key, $array)',
        'severity' => 'LOW'
    ],
    [
        'name' => '!empty() místo isset() && $var',
        'pattern' => '/isset\s*\([^)]+\)\s*&&\s*\$\w+\s*!==?\s*[\'"]\s*[\'"]/',
        'issue' => 'Lze zjednodušit',
        'fix' => '!empty($var)',
        'severity' => 'LOW'
    ],
    [
        'name' => 'Zbytečný array_values po array_map',
        'pattern' => '/array_values\s*\(\s*array_map\s*\(/i',
        'issue' => 'array_map už vrací numerické indexy',
        'fix' => 'Odstranit array_values()',
        'severity' => 'LOW'
    ],
    [
        'name' => 'Double array_merge v loopu',
        'pattern' => '/\$\w+\s*=\s*array_merge\s*\(\s*\$\w+\s*,/',
        'issue' => 'array_merge v loopu je pomalý',
        'fix' => 'Použít $array[] = $item',
        'severity' => 'MEDIUM'
    ],
    [
        'name' => 'file_get_contents bez kontroly',
        'pattern' => '/\$\w+\s*=\s*file_get_contents\s*\([^)]+\)\s*;(?!\s*if)/',
        'issue' => 'Chybí kontrola chyby',
        'fix' => 'if (($content = file_get_contents(...)) !== false)',
        'severity' => 'MEDIUM'
    ],
    [
        'name' => 'fopen bez fclose',
        'pattern' => '/\$\w+\s*=\s*fopen\s*\([^;]+;(?!.*fclose)/s',
        'issue' => 'Možný resource leak',
        'fix' => 'Přidat fclose() nebo použít file_get_contents()',
        'severity' => 'MEDIUM'
    ],
];

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

$findings = [];
$totalIssues = 0;

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    foreach ($patterns as $pattern) {
        foreach ($lines as $lineNum => $line) {
            if (preg_match($pattern['pattern'], $line)) {
                if (!isset($findings[$relativePath])) {
                    $findings[$relativePath] = [];
                }

                $findings[$relativePath][] = [
                    'line' => $lineNum + 1,
                    'name' => $pattern['name'],
                    'issue' => $pattern['issue'],
                    'fix' => $pattern['fix'],
                    'severity' => $pattern['severity'],
                    'code' => trim($line)
                ];
                $totalIssues++;
            }
        }
    }
}

// Seřadit podle severity a počtu
uasort($findings, function($a, $b) {
    $severityOrder = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];

    $maxSeverityA = 'LOW';
    $maxSeverityB = 'LOW';

    foreach ($a as $issue) {
        if ($severityOrder[$issue['severity']] < $severityOrder[$maxSeverityA]) {
            $maxSeverityA = $issue['severity'];
        }
    }

    foreach ($b as $issue) {
        if ($severityOrder[$issue['severity']] < $severityOrder[$maxSeverityB]) {
            $maxSeverityB = $issue['severity'];
        }
    }

    if ($maxSeverityA !== $maxSeverityB) {
        return $severityOrder[$maxSeverityA] - $severityOrder[$maxSeverityB];
    }

    return count($b) - count($a);
});

// Výsledky
echo "📊 VÝSLEDKY:\n";
echo str_repeat("=", 70) . "\n";
echo "Celkem optimization opportunities: {$totalIssues}\n";
echo "Souborů s issues: " . count($findings) . "\n\n";

if (empty($findings)) {
    echo "✅ Žádné minor optimization issues nenalezeny!\n";
} else {
    echo "⚡ OPTIMIZATION OPPORTUNITIES:\n";
    echo str_repeat("=", 70) . "\n\n";

    $fileCount = 0;
    foreach ($findings as $file => $issues) {
        $fileCount++;

        // Určit nejvyšší severity
        $maxSeverity = 'LOW';
        $severityOrder = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
        foreach ($issues as $issue) {
            if ($severityOrder[$issue['severity']] < $severityOrder[$maxSeverity]) {
                $maxSeverity = $issue['severity'];
            }
        }

        $icon = $maxSeverity === 'HIGH' ? '🔴' : ($maxSeverity === 'MEDIUM' ? '🟡' : '🟢');

        echo "{$icon} [{$fileCount}] {$file} ({$maxSeverity})\n";
        echo "   Issues: " . count($issues) . "\n";

        // Zobrazit prvních 3 issues
        $shown = 0;
        foreach ($issues as $issue) {
            $shown++;
            echo "   Line {$issue['line']}: {$issue['name']}\n";
            echo "     Problém: {$issue['issue']}\n";
            echo "     Fix: {$issue['fix']}\n";

            if ($shown >= 2) {
                $remaining = count($issues) - 2;
                if ($remaining > 0) {
                    echo "   ... a {$remaining} dalších\n";
                }
                break;
            }
        }
        echo "\n";

        if ($fileCount >= 15) {
            $remaining = count($findings) - 15;
            if ($remaining > 0) {
                echo "... a {$remaining} dalších souborů\n\n";
            }
            break;
        }
    }

    echo str_repeat("=", 70) . "\n";
    echo "💡 DOPORUČENÍ:\n";
    echo str_repeat("=", 70) . "\n\n";

    echo "PRIORITA:\n";
    echo "  🔴 HIGH - Opravit brzy (výkon impact)\n";
    echo "  🟡 MEDIUM - Opravit postupně\n";
    echo "  🟢 LOW - Nice to have\n\n";

    echo "TOP OPTIMALIZACE:\n";
    echo "1. count() v loop → cache do proměnné (2-10x rychlejší)\n";
    echo "2. array_merge v loop → použít \$arr[] = \$item (100x rychlejší)\n";
    echo "3. Zkontrolovat file operations → přidat error handling\n";
    echo "4. Zbytečné array funkce → simplifikovat\n\n";

    echo "BENEFIT:\n";
    echo "  - 5-20% rychlejší execution\n";
    echo "  - Lepší memory usage\n";
    echo "  - Čitelnější kód\n";
    echo "  - Méně potential bugs\n\n";
}

// Uložit report
$reportFile = __DIR__ . '/optimizations_report.txt';
$report = "# Minor Optimizations Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
$report .= "# Celkem issues: {$totalIssues}\n";
$report .= "# Souborů: " . count($findings) . "\n\n";

foreach ($findings as $file => $issues) {
    $report .= "## {$file} (" . count($issues) . " issues)\n\n";
    foreach ($issues as $issue) {
        $report .= "Line {$issue['line']} [{$issue['severity']}]: {$issue['name']}\n";
        $report .= "  Problém: {$issue['issue']}\n";
        $report .= "  Fix: {$issue['fix']}\n";
        $report .= "  Code: {$issue['code']}\n\n";
    }
    $report .= "\n";
}

file_put_contents($reportFile, $report);
echo "📝 Report uložen: scripts/optimizations_report.txt\n";
