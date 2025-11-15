<?php
/**
 * Najde přesně který control_center soubor má syntax error
 */

$files = [
    'control_center_unified.php',
    'control_center_testing.php',
    'control_center_testing_interactive.php',
    'control_center_testing_simulator.php',
    'control_center_appearance.php',
    'control_center_content.php',
    'control_center_console.php',
    'control_center_actions.php',
    'control_center_configuration.php',
    'control_center_tools.php'
];

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Find Syntax Error</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 20px; font-size: 13px; }
        h1 { color: #0ff; }
        .file { background: #111; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
        .bad { border-left: 4px solid #f00; }
        .ok { border-left: 4px solid #0f0; }
        .warn { border-left: 4px solid #ff0; }
        pre { margin: 5px 0; line-height: 1.6; }
        .highlight { background: #300; color: #f00; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>🔍 HLEDÁNÍ SYNTAX ERROR V CONTROL CENTER SOUBORECH</h1>

    <?php
    $adminLines = 664; // Počet řádků v admin.php před prvním include
    $currentLine = $adminLines;
    $problemFile = null;
    $problemLine = null;

    foreach ($files as $filename):
        $filepath = __DIR__ . '/includes/' . $filename;
        if (!file_exists($filepath)) continue;

        $lines = file($filepath);
        $fileLineCount = count($lines);
        $fileStartLine = $currentLine + 1;
        $fileEndLine = $currentLine + $fileLineCount;

        // Zkontrolovat jestli řádek 1066 je v tomto souboru
        $containsError = ($fileStartLine <= 1066 && $fileEndLine >= 1066);
        $localLineNum = 1066 - $fileStartLine + 1;

        // Hledat problematické patterny
        $content = file_get_contents($filepath);
        $hasGetCSRFToken = strpos($content, 'function getCSRFToken') !== false;

        // Hledat const/let/var bez středníku před getCSRFToken
        $suspicious = [];
        if ($hasGetCSRFToken) {
            // Najít pozici getCSRFToken
            $pos = strpos($content, 'function getCSRFToken');
            if ($pos !== false) {
                // Získat 500 znaků před funkcí
                $before = substr($content, max(0, $pos - 500), 500);

                // Hledat const/let/var
                if (preg_match('/\b(const|let|var)\s+\w+\s*=\s*[^;]+$/m', $before, $match)) {
                    $suspicious[] = "Možná chybějící středník před getCSRFToken: " . trim($match[0]);
                }
            }
        }

        $status = $containsError ? 'bad' : 'ok';
        if (count($suspicious) > 0) $status = 'warn';
    ?>

    <div class="file <?php echo $status; ?>">
        <pre><strong><?php echo htmlspecialchars($filename); ?></strong>

Řádky v admin.php: <?php echo $fileStartLine; ?> - <?php echo $fileEndLine; ?> (<?php echo $fileLineCount; ?> řádků)

<?php if ($containsError): ?>
<span class="highlight">⚠️ ŘÁDEK 1066 JE V TOMTO SOUBORU!</span>
Lokální řádek: <?php echo $localLineNum; ?>

<?php
    // Ukázat okolní řádky
    $start = max(0, $localLineNum - 10);
    $end = min(count($lines) - 1, $localLineNum + 5);
    echo "\n--- Okolní kód ---\n";
    for ($i = $start; $i <= $end; $i++) {
        $ln = $i + 1;
        $line = htmlspecialchars(rtrim($lines[$i]));
        if ($ln == $localLineNum) {
            echo sprintf("<span class='highlight'>%4d → %s</span>\n", $ln, $line);
        } else {
            echo sprintf("%4d   %s\n", $ln, $line);
        }
    }
?>
<?php
    $problemFile = $filename;
    $problemLine = $localLineNum;
endif;
?>

<?php if ($hasGetCSRFToken): ?>
✓ Obsahuje getCSRFToken() funkci
<?php endif; ?>

<?php foreach ($suspicious as $susp): ?>
<span style="color: #ff0;">⚠️ <?php echo htmlspecialchars($susp); ?></span>
<?php endforeach; ?>
</pre>
    </div>

    <?php
        $currentLine = $fileEndLine;
    endforeach;
    ?>

    <h1>📊 VÝSLEDEK</h1>
    <pre><?php
    if ($problemFile) {
        echo "❌ PROBLÉM NALEZEN!\n\n";
        echo "Soubor: includes/{$problemFile}\n";
        echo "Lokální řádek: {$problemLine}\n";
        echo "Globální řádek v admin.php: 1066\n";
    } else {
        echo "❓ Řádek 1066 nebyl nalezen v žádném souboru.\n";
        echo "Celkem řádků po includování: {$currentLine}\n";
    }
    ?></pre>

    <p style="margin-top: 40px; opacity: 0.5; font-size: 11px;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
