<?php
/**
 * LOW PRIORITY: Dead Code Detection
 *
 * Detekuje nepoužívané funkce, třídy a metody v projektu
 *
 * Použití: php scripts/detect_dead_code.php
 */

echo "💀 Dead Code Detection\n";
echo str_repeat("=", 70) . "\n\n";

$projectRoot = __DIR__ . '/..';
$excludeDirs = ['vendor', 'backups', 'node_modules', '.git', 'setup'];

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

// Krok 1: Najít všechny definované funkce
$definedFunctions = [];
$definedClasses = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);
    $relativePath = str_replace($projectRoot . '/', '', $filePath);

    // Najít funkce
    preg_match_all('/function\s+(\w+)\s*\(/i', $content, $funcMatches);
    foreach ($funcMatches[1] as $funcName) {
        if (!isset($definedFunctions[$funcName])) {
            $definedFunctions[$funcName] = [];
        }
        $definedFunctions[$funcName][] = $relativePath;
    }

    // Najít třídy
    preg_match_all('/class\s+(\w+)/i', $content, $classMatches);
    foreach ($classMatches[1] as $className) {
        if (!isset($definedClasses[$className])) {
            $definedClasses[$className] = [];
        }
        $definedClasses[$className][] = $relativePath;
    }
}

// Krok 2: Najít všechna volání funkcí
$usedFunctions = [];
$usedClasses = [];

foreach ($files as $filePath) {
    $content = file_get_contents($filePath);

    // Najít volání funkcí (aproximace)
    foreach (array_keys($definedFunctions) as $funcName) {
        // Hledat $funcName( ale ne function $funcName(
        if (preg_match('/(?<!function\s)' . preg_quote($funcName, '/') . '\s*\(/i', $content)) {
            $usedFunctions[$funcName] = true;
        }
    }

    // Najít použití tříd (new ClassName, ClassName::, : ClassName)
    foreach (array_keys($definedClasses) as $className) {
        if (preg_match('/(new\s+' . preg_quote($className, '/') . '|' . preg_quote($className, '/') . '\s*::|\s:\s*' . preg_quote($className, '/') . ')/i', $content)) {
            $usedClasses[$className] = true;
        }
    }
}

// Krok 3: Najít dead code
$deadFunctions = [];
$deadClasses = [];

foreach ($definedFunctions as $funcName => $locations) {
    // Přeskočit built-in a magic funkce
    $skip = ['__construct', '__destruct', '__get', '__set', '__call', '__toString', '__invoke'];
    if (in_array($funcName, $skip)) {
        continue;
    }

    if (!isset($usedFunctions[$funcName])) {
        $deadFunctions[$funcName] = $locations;
    }
}

foreach ($definedClasses as $className => $locations) {
    if (!isset($usedClasses[$className])) {
        $deadClasses[$className] = $locations;
    }
}

// Výsledky
echo "📊 VÝSLEDKY:\n";
echo str_repeat("=", 70) . "\n";
echo "Definované funkce: " . count($definedFunctions) . "\n";
echo "Použité funkce: " . count($usedFunctions) . "\n";
echo "Dead funkce: " . count($deadFunctions) . "\n\n";

echo "Definované třídy: " . count($definedClasses) . "\n";
echo "Použité třídy: " . count($usedClasses) . "\n";
echo "Dead třídy: " . count($deadClasses) . "\n\n";

if (!empty($deadFunctions)) {
    echo "💀 DEAD FUNKCE (potenciálně nepoužívané):\n";
    echo str_repeat("=", 70) . "\n\n";

    $count = 0;
    foreach ($deadFunctions as $funcName => $locations) {
        $count++;
        echo "#{$count} function {$funcName}()\n";
        foreach ($locations as $loc) {
            echo "   📄 {$loc}\n";
        }
        echo "\n";

        if ($count >= 20) {
            $remaining = count($deadFunctions) - 20;
            if ($remaining > 0) {
                echo "... a {$remaining} dalších funkcí\n\n";
            }
            break;
        }
    }
}

if (!empty($deadClasses)) {
    echo "💀 DEAD TŘÍDY (potenciálně nepoužívané):\n";
    echo str_repeat("=", 70) . "\n\n";

    $count = 0;
    foreach ($deadClasses as $className => $locations) {
        $count++;
        echo "#{$count} class {$className}\n";
        foreach ($locations as $loc) {
            echo "   📄 {$loc}\n";
        }
        echo "\n";

        if ($count >= 10) {
            $remaining = count($deadClasses) - 10;
            if ($remaining > 0) {
                echo "... a {$remaining} dalších tříd\n\n";
            }
            break;
        }
    }
}

echo str_repeat("=", 70) . "\n";
echo "⚠️  DŮLEŽITÉ VAROVÁNÍ:\n";
echo str_repeat("=", 70) . "\n\n";

echo "1. Tato analýza je APROXIMACE - může mít false positives!\n";
echo "2. Některé funkce mohou být:\n";
echo "   - Volané dynamicky (\$funcName())\n";
echo "   - API endpointy (volané z JS/frontendu)\n";
echo "   - Callback funkce\n";
echo "   - Webhook handlery\n";
echo "3. NIKDY nemazat bez manuálního ověření!\n";
echo "4. Vždy otestovat funkčnost před smazáním\n\n";

echo "💡 DOPORUČENÍ:\n";
echo str_repeat("=", 70) . "\n\n";

echo "1. Projít každou dead funkci manuálně\n";
echo "2. Zkontrolovat git history - kdy naposledy použita\n";
echo "3. Hledat v JS/frontend kódu\n";
echo "4. Pokud opravdu nepoužita: přesunout do /backups\n";
echo "5. Po týdnu v produkci: smazat definitivně\n\n";

// Uložit report
$reportFile = __DIR__ . '/dead_code_report.txt';
$report = "# Dead Code Report\n";
$report .= "# Vygenerováno: " . date('Y-m-d H:i:s') . "\n";
$report .= "# Dead funkcí: " . count($deadFunctions) . "\n";
$report .= "# Dead tříd: " . count($deadClasses) . "\n\n";

$report .= "## Dead Funkce:\n\n";
foreach ($deadFunctions as $funcName => $locations) {
    $report .= "function {$funcName}()\n";
    foreach ($locations as $loc) {
        $report .= "  - {$loc}\n";
    }
    $report .= "\n";
}

$report .= "\n## Dead Třídy:\n\n";
foreach ($deadClasses as $className => $locations) {
    $report .= "class {$className}\n";
    foreach ($locations as $loc) {
        $report .= "  - {$loc}\n";
    }
    $report .= "\n";
}

file_put_contents($reportFile, $report);
echo "📝 Report uložen: scripts/dead_code_report.txt\n";
