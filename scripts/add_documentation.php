<?php
/**
 * Automatické přidání PHPDoc komentářů
 *
 * Automaticky přidává základní PHPDoc komentáře k funkcím bez dokumentace
 *
 * Použití: php scripts/add_documentation.php
 */

echo "📝 Přidávám PHPDoc komentáře...\n";
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

$totalAdded = 0;
$filesModified = 0;

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    // Najít všechny funkce
    $pattern = '/(function\s+(\w+)\s*\(([^)]*)\))/';

    $result = preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    if (!$result) {
        continue;
    }

    // Procházet odzadu, aby offsety zůstaly platné
    $matches = array_reverse($matches);

    foreach ($matches as $match) {
        $fullMatch = $match[0][0];
        $offset = $match[0][1];
        $funcName = $match[2][0];
        $params = $match[3][0];

        // Zkontrolovat, že před funkcí NENÍ PHPDoc
        $before = substr($content, max(0, $offset - 200), 200);
        if (preg_match('/\/\*\*.*?\*\/\s*$/s', $before)) {
            continue; // Už má dokumentaci
        }

        // Parsovat parametry
        $paramDocs = [];
        if (trim($params)) {
            $paramList = explode(',', $params);
            foreach ($paramList as $param) {
                $param = trim($param);
                // Extrahovat název parametru
                if (preg_match('/\$(\w+)/', $param, $paramMatch)) {
                    $paramName = $paramMatch[1];

                    // Detekovat typ z type hinting
                    $type = 'mixed';
                    if (preg_match('/(string|int|bool|array|float|object|\w+)\s+\$/', $param, $typeMatch)) {
                        $type = $typeMatch[1];
                    }

                    // Generovat popis podle názvu
                    $desc = ucfirst(str_replace('_', ' ', $paramName));
                    $paramDocs[] = " * @param {$type} \${$paramName} {$desc}";
                }
            }
        }

        // Generovat PHPDoc
        $funcDesc = ucfirst(str_replace('_', ' ', $funcName));
        $doc = "/**\n";
        $doc .= " * {$funcDesc}\n";

        if (!empty($paramDocs)) {
            $doc .= " *\n";
            $doc .= implode("\n", $paramDocs) . "\n";
        }

        $doc .= " */\n";

        // Získat odsazení před funkcí
        $lineStart = strrpos(substr($content, 0, $offset), "\n");
        if ($lineStart !== false) {
            $lineContent = substr($content, $lineStart + 1, $offset - $lineStart - 1);
            $indent = '';
            if (preg_match('/^(\s+)/', $lineContent, $indentMatch)) {
                $indent = $indentMatch[1];
            }

            // Přidat odsazení k dokumentaci
            $doc = implode("\n", array_map(function($line) use ($indent) {
                return $line ? $indent . $line : $line;
            }, explode("\n", $doc)));
        }

        // Vložit PHPDoc před funkci
        $content = substr($content, 0, $offset) . $doc . substr($content, $offset);
        $totalAdded++;
    }

    // Uložit soubor, pokud se změnil
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        $filesModified++;

        // Spočítat přidané komentáře v tomto souboru
        $addedInFile = substr_count($content, '/**') - substr_count($originalContent, '/**');
        echo "✓ {$relativePath} - přidáno {$addedInFile} PHPDoc\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "DOKONČENO!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Statistiky:\n";
echo "  Soubory upraveny: {$filesModified}\n";
echo "  PHPDoc přidáno: {$totalAdded}\n\n";

echo "💡 Další kroky:\n";
echo "  1. Zkontrolujte přidané komentáře\n";
echo "  2. Upravte generické popisy na specifické\n";
echo "  3. Přidejte @return anotace kde chybí\n";
echo "  4. Přidejte @throws anotace pro funkce s exceptions\n\n";
