<?php
/**
 * LOW PRIORITY: Legacy Functions Detection
 *
 * Detekuje zastaralé PHP funkce a navrhuje moderní alternativy
 *
 * Použití: php scripts/detect_legacy_functions.php
 */

echo "🕰️  Legacy Functions Detection\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git', 'setup'];

// Definovat legacy funkce a jejich náhrady
$legacyFunctions = [
    // Deprecated PHP funkce
    'mysql_connect' => ['replacement' => 'PDO nebo mysqli', 'severity' => 'CRITICAL'],
    'mysql_query' => ['replacement' => 'PDO->query() nebo mysqli_query()', 'severity' => 'CRITICAL'],
    'mysql_fetch_array' => ['replacement' => 'PDO fetch() nebo mysqli_fetch_array()', 'severity' => 'CRITICAL'],
    'ereg' => ['replacement' => 'preg_match()', 'severity' => 'HIGH'],
    'eregi' => ['replacement' => 'preg_match() s /i flag', 'severity' => 'HIGH'],
    'split' => ['replacement' => 'explode() nebo preg_split()', 'severity' => 'HIGH'],
    'session_register' => ['replacement' => '$_SESSION', 'severity' => 'HIGH'],

    // Nebezpečné funkce
    'eval' => ['replacement' => 'Přepsat bez eval()', 'severity' => 'CRITICAL'],
    'create_function' => ['replacement' => 'anonymous function nebo closure', 'severity' => 'HIGH'],
    'extract' => ['replacement' => 'manuální přiřazení proměnných', 'severity' => 'MEDIUM'],

    // Zastaralé string funkce
    'money_format' => ['replacement' => 'NumberFormatter', 'severity' => 'MEDIUM'],

    // Error handling
    '@' => ['replacement' => 'try-catch nebo kontrola chyb', 'severity' => 'MEDIUM'],

    // Array funkce
    'each' => ['replacement' => 'foreach', 'severity' => 'MEDIUM'],
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

    foreach ($lines as $lineNum => $line) {
        foreach ($legacyFunctions as $legacyFunc => $info) {
            // Speciální případ pro @
            if ($legacyFunc === '@') {
                if (preg_match('/\@[a-zA-Z_]/', $line)) {
                    if (!isset($findings[$relativePath])) {
                        $findings[$relativePath] = [];
                    }
                    $findings[$relativePath][] = [
                        'line' => $lineNum + 1,
                        'function' => '@',
                        'code' => trim($line),
                        'replacement' => $info['replacement'],
                        'severity' => $info['severity']
                    ];
                    $totalIssues++;
                }
                continue;
            }

            // Hledat volání legacy funkce
            if (preg_match('/\b' . preg_quote($legacyFunc, '/') . '\s*\(/i', $line)) {
                if (!isset($findings[$relativePath])) {
                    $findings[$relativePath] = [];
                }
                $findings[$relativePath][] = [
                    'line' => $lineNum + 1,
                    'function' => $legacyFunc,
                    'code' => trim($line),
                    'replacement' => $info['replacement'],
                    'severity' => $info['severity']
                ];
                $totalIssues++;
            }
        }
    }
}

// Seřadit podle severity a počtu issues
uasort($findings, function($a, $b) {
    $severityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];

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
echo "Celkem legacy issues: {$totalIssues}\n";
echo "Souborů s legacy kódem: " . count($findings) . "\n\n";

if (empty($findings)) {
    echo "Žádné legacy funkce nenalezeny!\n";
} else {
    echo "🕰️  LEGACY FUNKCE:\n";
    echo str_repeat("=", 70) . "\n\n";

    $fileCount = 0;
    foreach ($findings as $file => $issues) {
        $fileCount++;

        // Určit nejvyšší severity
        $maxSeverity = 'LOW';
        $severityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
        foreach ($issues as $issue) {
            if ($severityOrder[$issue['severity']] < $severityOrder[$maxSeverity]) {
                $maxSeverity = $issue['severity'];
            }
        }

        $icon = $maxSeverity === 'CRITICAL' ? '🔴' : ($maxSeverity === 'HIGH' ? '🟠' : '🟡');

        echo "{$icon} [{$fileCount}] {$file} ({$maxSeverity})\n";
        echo "   Issues: " . count($issues) . "\n";

        // Zobrazit prvních 5 issues
        $shown = 0;
        foreach ($issues as $issue) {
            $shown++;
            echo "   Line {$issue['line']}: {$issue['function']}() → {$issue['replacement']}\n";
            if ($shown >= 3) {
                $remaining = count($issues) - 3;
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
    echo "  🔴 CRITICAL - Nefunkční v PHP 7+, opravit ASAP!\n";
    echo "  🟠 HIGH - Deprecated, opravit brzy\n";
    echo "  🟡 MEDIUM - Bad practice, opravit postupně\n\n";

    echo "JAK OPRAVIT:\n";
    echo "1. Začít s CRITICAL issues\n";
    echo "2. Použít doporučené náhrady z reportu\n";
    echo "3. Otestovat po každé změně\n";
    echo "4. Update dokumentaci\n\n";

    echo "PŘÍKLADY NÁHRAD:\n";
    echo "  mysql_* → PDO nebo mysqli\n";
    echo "  ereg() → preg_match()\n";
    echo "  @ → try-catch nebo if(isset())\n";
    echo "  extract() → \$var = \$array['key']\n\n";
}

// Uložit report
$reportFile = __DIR__ . '/legacy_functions_report.txt';
$report = "# Legacy Functions Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
$report .= "# Celkem issues: {$totalIssues}\n";
$report .= "# Souborů: " . count($findings) . "\n\n";

foreach ($findings as $file => $issues) {
    $report .= "## {$file} (" . count($issues) . " issues)\n\n";
    foreach ($issues as $issue) {
        $report .= "Line {$issue['line']} [{$issue['severity']}]: {$issue['function']}()\n";
        $report .= "  Replacement: {$issue['replacement']}\n";
        $report .= "  Code: {$issue['code']}\n\n";
    }
    $report .= "\n";
}

file_put_contents($reportFile, $report);
echo "📝 Report uložen: scripts/legacy_functions_report.txt\n";
