<?php
/**
 * Automatické odstranění dead code
 *
 * Odstraní nepoužívané funkce a třídy identifikované v dead_code_report.txt
 *
 * Použití: php scripts/remove_dead_code.php
 */

echo "🗑️  Odstraňuji dead code...\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$reportFile = __DIR__ . '/dead_code_report.txt';

if (!file_exists($reportFile)) {
    die("Report nenalezen: {$reportFile}\n");
}

$report = file_get_contents($reportFile);

// Parsovat dead funkce z reportu
$deadFunctions = [];
preg_match_all('/function (\w+)\(\)\s+- (.+)/', $report, $funcMatches, PREG_SET_ORDER);

foreach ($funcMatches as $match) {
    $deadFunctions[] = [
        'name' => $match[1],
        'file' => $projectRoot . '/' . trim($match[2])
    ];
}

// Parsovat dead třídy
$deadClasses = [];
preg_match_all('/class (\w+)\s+- (.+)/', $report, $classMatches, PREG_SET_ORDER);

foreach ($classMatches as $match) {
    $deadClasses[] = [
        'name' => $match[1],
        'file' => $projectRoot . '/' . trim($match[2])
    ];
}

echo "📋 Nalezeno:\n";
echo "  Dead funkce: " . count($deadFunctions) . "\n";
echo "  Dead třídy: " . count($deadClasses) . "\n\n";

$removedFunctions = 0;
$removedClasses = 0;

// Odstranit funkce
echo "🔧 Odstraňuji funkce...\n\n";

foreach ($deadFunctions as $func) {
    $file = $func['file'];
    $name = $func['name'];

    if (!file_exists($file)) {
        echo "⚠️  Soubor nenalezen: {$file}\n";
        continue;
    }

    $content = file_get_contents($file);
    $originalContent = $content;

    // Pattern pro funkci včetně PHPDoc
    $pattern = '/(?:\/\*\*.*?\*\/\s*)?' .  // Volitelný PHPDoc
               'function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/s';

    // Najít funkci
    if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
        $start = $match[0][1];

        // Najít konec funkce (matching closing brace)
        $bracketCount = 1;
        $pos = $start + strlen($match[0][0]);

        while ($pos < strlen($content) && $bracketCount > 0) {
            if ($content[$pos] === '{') $bracketCount++;
            if ($content[$pos] === '}') $bracketCount--;
            $pos++;
        }

        // Odstranit funkci včetně trailing newline
        $end = $pos;
        if (isset($content[$end]) && $content[$end] === "\n") {
            $end++;
        }

        // Odstranit i předcházející prázdné řádky
        $beforeStart = $start;
        while ($beforeStart > 0 && in_array($content[$beforeStart - 1], ["\n", "\r", " ", "\t"])) {
            $beforeStart--;
            if ($content[$beforeStart] === "\n") {
                break;
            }
        }

        $content = substr($content, 0, $beforeStart) . substr($content, $end);

        file_put_contents($file, $content);
        $removedFunctions++;

        $relativePath = str_replace($projectRoot . '/', '', $file);
        echo "✓ Odstraněna funkce {$name}() z {$relativePath}\n";
    } else {
        echo "⚠️  Funkce {$name}() nenalezena v " . str_replace($projectRoot . '/', '', $file) . "\n";
    }
}

echo "\n";

// Odstranit třídy
echo "🔧 Odstraňuji třídy...\n\n";

foreach ($deadClasses as $class) {
    $file = $class['file'];
    $name = $class['name'];

    if (!file_exists($file)) {
        echo "⚠️  Soubor nenalezen: {$file}\n";
        continue;
    }

    $content = file_get_contents($file);

    // Pattern pro třídu včetně PHPDoc
    $pattern = '/(?:\/\*\*.*?\*\/\s*)?' .  // Volitelný PHPDoc
               'class\s+' . preg_quote($name, '/') . '\s*(?:extends\s+\w+\s*)?(?:implements\s+[\w,\s]+\s*)?\{/s';

    // Najít třídu
    if (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE)) {
        $start = $match[0][1];

        // Najít konec třídy
        $bracketCount = 1;
        $pos = $start + strlen($match[0][0]);

        while ($pos < strlen($content) && $bracketCount > 0) {
            if ($content[$pos] === '{') $bracketCount++;
            if ($content[$pos] === '}') $bracketCount--;
            $pos++;
        }

        $end = $pos;

        // Odstranit i předcházející prázdné řádky
        $beforeStart = $start;
        while ($beforeStart > 0 && in_array($content[$beforeStart - 1], ["\n", "\r", " ", "\t"])) {
            $beforeStart--;
            if ($content[$beforeStart] === "\n") {
                break;
            }
        }

        $content = substr($content, 0, $beforeStart) . substr($content, $end);

        file_put_contents($file, $content);
        $removedClasses++;

        $relativePath = str_replace($projectRoot . '/', '', $file);
        echo "✓ Odstraněna třída {$name} z {$relativePath}\n";
    } else {
        echo "⚠️  Třída {$name} nenalezena v " . str_replace($projectRoot . '/', '', $file) . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "DOKONČENO!\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Statistiky:\n";
echo "  Odstraněno funkcí: {$removedFunctions}/" . count($deadFunctions) . "\n";
echo "  Odstraněno tříd: {$removedClasses}/" . count($deadClasses) . "\n\n";

echo "💡 Další kroky:\n";
echo "  1. Zkontrolujte, že aplikace funguje správně\n";
echo "  2. Spusťte testy\n";
echo "  3. Commitněte změny\n\n";
