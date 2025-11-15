<?php
/**
 * Optimalizace count() a strlen() v loop podmínkách
 *
 * Přesouvá count() a strlen() volání mimo loop podmínky
 *
 * Použití: php scripts/optimize_loops.php
 */

echo "⚡ Optimalizuji loops...\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git'];

// Najít soubory s count()/strlen() v loops
$filesToFix = [];

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
            $content = file_get_contents($path);
            if (preg_match('/for\s*\([^)]*(?:count\(|strlen\()[^)]*\)/i', $content)) {
                $filesToFix[] = $path;
            }
        }
    }
}

echo "📋 Nalezeno " . count($filesToFix) . " souborů k optimalizaci\n\n";

$totalFixed = 0;
$filesModified = 0;

foreach ($filesToFix as $filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    $fixedInFile = 0;

    // Pattern: $count_i = count($arr);
 for ($i = ...; $i < $count_i; $i++)
    // Fix: $count = count($arr); for ($i = ...; $i < $count; $i++)
    $count = 0;
    $content = preg_replace_callback(
        '/(\s+)(for\s*\(\s*\$(\w+)\s*=\s*([^;]+);\s*\$\3\s*([<>]=?)\s*count\(([^\)]+)\)\s*;\s*([^)]+)\))/i',
        function ($m) use (&$count) {
            $count++;
            $indent = $m[1];
            $forLoop = $m[2];
            $varName = $m[3];
            $init = $m[4];
            $operator = $m[5];
            $arrayVar = trim($m[6]);
            $increment = $m[7];

            // Generovat unikátní název proměnné pro count
            $countVar = 'count_' . $varName;

            return $indent . '$' . $countVar . ' = count(' . $arrayVar . ');' . "\n" .
                   $indent . 'for ($' . $varName . ' = ' . $init . '; $' . $varName . ' ' . $operator . ' $' . $countVar . '; ' . $increment . ')';
        },
        $content
    );
    $fixedInFile += $count;

    // Pattern: $len_i = strlen($str);
 for ($i = ...; $i < $len_i; $i++)
    // Fix: $len = strlen($str); for ($i = ...; $i < $len; $i++)
    $count = 0;
    $content = preg_replace_callback(
        '/(\s+)(for\s*\(\s*\$(\w+)\s*=\s*([^;]+);\s*\$\3\s*([<>]=?)\s*strlen\(([^\)]+)\)\s*;\s*([^)]+)\))/i',
        function ($m) use (&$count) {
            $count++;
            $indent = $m[1];
            $forLoop = $m[2];
            $varName = $m[3];
            $init = $m[4];
            $operator = $m[5];
            $strVar = trim($m[6]);
            $increment = $m[7];

            // Generovat unikátní název proměnné pro strlen
            $lenVar = 'len_' . $varName;

            return $indent . '$' . $lenVar . ' = strlen(' . $strVar . ');' . "\n" .
                   $indent . 'for ($' . $varName . ' = ' . $init . '; $' . $varName . ' ' . $operator . ' $' . $lenVar . '; ' . $increment . ')';
        },
        $content
    );
    $fixedInFile += $count;

    if ($fixedInFile > 0) {
        file_put_contents($filePath, $content);
        $filesModified++;
        $totalFixed += $fixedInFile;
        echo "✓ {$relativePath} - optimalizováno {$fixedInFile} loops\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ DOKONČENO!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Statistiky:\n";
echo "  Soubory upraveny: {$filesModified}\n";
echo "  Loops optimalizováno: {$totalFixed}\n\n";

echo "💡 Výhody:\n";
echo "  - Rychlejší vykonávání loops\n";
echo "  - count()/strlen() volán pouze 1x místo N×\n";
echo "  - Lepší performance při velkých arrays/strings\n\n";
