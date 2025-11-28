<?php
/**
 * Automatická migrace @ operátorů na správné error handling
 *
 * Nahrazuje @ suppress operátory za try-catch nebo if kontroly
 *
 * Použití: php scripts/fix_at_operators.php
 */

echo "🔧 Migruji @ operátory...\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git'];

// Pattern pro detekci @ operátorů
$patterns = [
    '@file_get_contents',
    '@fopen',
    '@mkdir',
    '@unlink',
    '@file_put_contents',
    '@copy',
    '@rename',
    '@rmdir',
    '@json_decode',
    '@simplexml_load_file',
];

// Najít soubory s @ operátory
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
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $filesToFix[$path] = true;
                    break;
                }
            }
        }
    }
}

echo "📋 Nalezeno " . count($filesToFix) . " souborů s @ operátory\n\n";

$totalFixed = 0;
$filesModified = 0;

foreach (array_keys($filesToFix) as $filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    $fixedInFile = 0;

    // Fix @file_get_contents
    $count = 0;
    $content = preg_replace_callback(
        '/\$(\w+)\s*=\s*@file_get_contents\(([^)]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $var = $m[1];
            $arg = $m[2];
            return "\${$var} = file_get_contents({$arg});\n" .
                   "if (\${$var} === false) {\n" .
                   "    error_log('Failed to read file: ' . {$arg});\n" .
                   "    \${$var} = '';\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    // Fix @fopen
    $count = 0;
    $content = preg_replace_callback(
        '/\$(\w+)\s*=\s*@fopen\(([^)]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $var = $m[1];
            $arg = $m[2];
            return "\${$var} = fopen({$arg});\n" .
                   "if (\${$var} === false) {\n" .
                   "    error_log('Failed to open file: ' . {$arg});\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    // Fix @mkdir
    $count = 0;
    $content = preg_replace_callback(
        '/@mkdir\(([^)]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $arg = $m[1];
            return "if (!is_dir({$arg})) {\n" .
                   "    if (!mkdir({$arg}) && !is_dir({$arg})) {\n" .
                   "        error_log('Failed to create directory: ' . {$arg});\n" .
                   "    }\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    // Fix @unlink
    $count = 0;
    $content = preg_replace_callback(
        '/@unlink\(([^)]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $arg = $m[1];
            return "if (file_exists({$arg})) {\n" .
                   "    if (!unlink({$arg})) {\n" .
                   "        error_log('Failed to delete file: ' . {$arg});\n" .
                   "    }\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    // Fix @file_put_contents
    $count = 0;
    $content = preg_replace_callback(
        '/@file_put_contents\(([^;]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $arg = $m[1];
            return "if (file_put_contents({$arg}) === false) {\n" .
                   "    error_log('Failed to write file');\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    // Fix @json_decode (simple replacement)
    $count = 0;
    $content = preg_replace_callback(
        '/\$(\w+)\s*=\s*@json_decode\(([^)]+)\);/',
        function ($m) use (&$count) {
            $count++;
            $var = $m[1];
            $arg = $m[2];
            return "\${$var} = json_decode({$arg});\n" .
                   "if (json_last_error() !== JSON_ERROR_NONE) {\n" .
                   "    error_log('JSON decode error: ' . json_last_error_msg());\n" .
                   "    \${$var} = null;\n" .
                   "}";
        },
        $content
    );
    $fixedInFile += $count;

    if ($fixedInFile > 0) {
        file_put_contents($filePath, $content);
        $filesModified++;
        $totalFixed += $fixedInFile;
        echo "✓ {$relativePath} - opraveno {$fixedInFile} @ operátorů\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "DOKONČENO!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Statistiky:\n";
echo "  Soubory upraveny: {$filesModified}\n";
echo "  @ operátorů opraveno: {$totalFixed}\n\n";

echo "💡 Další kroky:\n";
echo "  1. Zkontrolujte vygenerovaný kód\n";
echo "  2. Otestujte aplikaci\n";
echo "  3. Ověřte error logging\n\n";
