<?php
/**
 * HIGH PRIORITY: SELECT * Detection & Optimization Tool
 *
 * Detekuje všechny SELECT * dotazy v projektu a generuje doporučení
 * SELECT * je anti-pattern protože:
 * - Zhoršuje performance (přenáší zbytečná data)
 * - Zvyšuje memory usage (20-40%)
 * - Komplikuje cache
 * - Způsobuje N+1 query problémy
 *
 * Použití:
 * - CLI: php scripts/detect_select_star.php
 * - Web: Spustit z admin panelu
 */

require_once __DIR__ . '/../init.php';

// SECURITY: Admin check
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'message' => 'Admin access required']));
    }
}

echo "🔍 SELECT * Detection Tool\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git'];

$findings = [];
$totalCount = 0;

// Recursive file scanner
function scanDirectory($dir, $excludeDirs) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();

            // Check if path contains excluded directory
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

    return $files;
}

$files = scanDirectory($projectRoot, $excludeDirs);

echo "Scanning " . count($files) . " PHP files...\n\n";

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);

    foreach ($lines as $lineNum => $line) {
        // Detect SELECT * (case insensitive)
        if (preg_match('/\bSELECT\s+\*/i', $line)) {
            $totalCount++;
            $relativePath = str_replace($projectRoot . '/', '', $filePath);

            if (!isset($findings[$relativePath])) {
                $findings[$relativePath] = [];
            }

            // Extract table name if possible
            $tableName = 'unknown';
            if (preg_match('/FROM\s+`?(\w+)`?/i', $line, $matches)) {
                $tableName = $matches[1];
            }

            $findings[$relativePath][] = [
                'line' => $lineNum + 1,
                'code' => trim($line),
                'table' => $tableName
            ];
        }
    }
}

// Sort by file with most occurrences
uasort($findings, function($a, $b) {
    return count($b) - count($a);
});

echo "📊 RESULTS:\n";
echo str_repeat("=", 70) . "\n";
echo "Total SELECT * found: {$totalCount}\n";
echo "Files affected: " . count($findings) . "\n\n";

// Display findings
$priority = 1;
foreach ($findings as $file => $issues) {
    $count = count($issues);
    $icon = $count >= 4 ? '🔴' : ($count >= 2 ? '🟡' : '🟢');

    echo "{$icon} [{$priority}] {$file} ({$count} occurrences)\n";

    foreach ($issues as $issue) {
        echo "   Line {$issue['line']}: {$issue['table']}\n";
        echo "   " . substr($issue['code'], 0, 80) . (strlen($issue['code']) > 80 ? '...' : '') . "\n\n";
    }

    $priority++;

    if ($priority > 10) {
        echo "... a " . (count($findings) - 10) . " dalších souborů\n\n";
        break;
    }
}

echo str_repeat("=", 70) . "\n";
echo "💡 DOPORUČENÍ:\n";
echo str_repeat("=", 70) . "\n\n";

echo "1. REPLACE SELECT * s explicitními sloupci:\n";
echo "   PŘED: SELECT * FROM users WHERE id = ?\n";
echo "   PO:   SELECT id, name, email FROM users WHERE id = ?\n\n";

echo "2. PRIORITY:\n";
echo "   🔴 HIGH (4+ occurences): Optimalizujte ASAP\n";
echo "   🟡 MEDIUM (2-3 occurences): Optimalizujte brzy\n";
echo "   🟢 LOW (1 occurence): Optimalizujte postupně\n\n";

echo "3. VÝHODY optimalizace:\n";
echo "   - 20-40% memory usage reduction\n";
echo "   - Rychlejší query execution\n";
echo "   - Lepší MySQL cache hit rate\n";
echo "   - Explicitní API kontrakt\n\n";

echo "4. JAK OPTIMALIZOVAT:\n";
echo "   a) Zjistit strukturu tabulky: DESCRIBE table_name;\n";
echo "   b) Vybrat pouze potřebné sloupce\n";
echo "   c) Otestovat query performance: EXPLAIN SELECT ...\n";
echo "   d) Deploy a monitoring\n\n";

// Generate migration script
$migrationFile = __DIR__ . '/select_star_optimization.txt';
$migration = "# SELECT * Optimization - Migration Checklist\n";
$migration .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
$migration .= "# Total issues: {$totalCount}\n\n";

foreach ($findings as $file => $issues) {
    $migration .= "## {$file} (" . count($issues) . " issues)\n";
    foreach ($issues as $issue) {
        $migration .= "- [ ] Line {$issue['line']}: {$issue['table']}\n";
    }
    $migration .= "\n";
}

file_put_contents($migrationFile, $migration);

echo "📝 Migration checklist saved to: scripts/select_star_optimization.txt\n";

// Return JSON for API calls
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total_count' => $totalCount,
            'files_affected' => count($findings)
        ],
        'findings' => $findings
    ], JSON_PRETTY_PRINT);
}
