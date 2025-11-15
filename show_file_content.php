<?php
/**
 * Ukáže přesný obsah control_center_testing_interactive.php kolem problematické části
 */

$filePath = __DIR__ . '/includes/control_center_testing_interactive.php';
$lines = file($filePath);

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>File Content Check</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; font-size: 13px; }
        h1 { color: #4ec9b0; }
        .line { padding: 2px 5px; border-left: 3px solid transparent; }
        .line:hover { background: #2d2d30; }
        .ln { color: #858585; width: 50px; display: inline-block; }
        .highlight { background: #3a3d41; border-left: 3px solid #f48771; }
        .ok { background: #1e3a1e; border-left: 3px solid #4ec9b0; }
        pre { background: #252526; padding: 20px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📄 control_center_testing_interactive.php - Řádky 380-410</h1>

    <pre><?php
    for ($i = 379; $i <= 409 && $i < count($lines); $i++) {
        $line = htmlspecialchars($lines[$i]);
        $lineNum = $i + 1;

        $class = 'line';
        if (strpos($line, 'getCSRFToken') !== false) {
            $class .= ' highlight';
        }
        if (strpos($line, 'getCSRFTokenSync') !== false) {
            $class .= ' ok';
        }

        echo sprintf('<div class="%s"><span class="ln">%4d</span> %s</div>', $class, $lineNum, $line);
    }
    ?></pre>

    <h1>🔍 Vyhledávání funkcí:</h1>
    <pre><?php
    $content = file_get_contents($filePath);

    // Najít všechny výskyty getCSRFToken
    preg_match_all('/function\s+getCSRFToken/', $content, $matches, PREG_OFFSET_CAPTURE);

    echo "Počet výskytů 'function getCSRFToken': " . count($matches[0]) . "\n\n";

    foreach ($matches[0] as $match) {
        $offset = $match[1];
        $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;
        echo "  → Řádek $lineNum\n";
    }

    echo "\n";

    // Najít getCSRFTokenSync
    preg_match_all('/function\s+getCSRFTokenSync/', $content, $matches2, PREG_OFFSET_CAPTURE);

    echo "Počet výskytů 'function getCSRFTokenSync': " . count($matches2[0]) . "\n\n";

    foreach ($matches2[0] as $match) {
        $offset = $match[1];
        $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;
        echo "  → Řádek $lineNum\n";
    }
    ?></pre>

    <h1>📊 Shrnutí:</h1>
    <pre><?php
    $hasDuplicate = strpos($content, 'function getCSRFToken()') !== false;
    $hasFixed = strpos($content, 'function getCSRFTokenSync()') !== false;

    if ($hasDuplicate && !$hasFixed) {
        echo "❌ PROBLÉM: Soubor má duplicitní getCSRFToken() a NEMÁ opravu\n";
        echo "   Řešení: Aplikuj hotfix znovu\n";
    } elseif ($hasFixed && !$hasDuplicate) {
        echo "✅ OK: Soubor je opraven (má getCSRFTokenSync, nemá getCSRFToken)\n";
        echo "   Pokud chyba přetrvává = CACHE problém\n";
    } elseif ($hasDuplicate && $hasFixed) {
        echo "⚠️ OBOJÍ: Soubor má OBOJÍ funkce (problém!)\n";
        echo "   Řešení: Odstranit getCSRFToken(), nechat jen getCSRFTokenSync\n";
    } else {
        echo "❓ ŽÁDNÁ: Soubor nemá ani jednu funkci (divné)\n";
    }
    ?></pre>

    <p style="margin-top: 40px; opacity: 0.5; font-size: 11px;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?><br>
        File size: <?php echo filesize($filePath); ?> bytes<br>
        Last modified: <?php echo date('Y-m-d H:i:s', filemtime($filePath)); ?>
    </p>
</body>
</html>
