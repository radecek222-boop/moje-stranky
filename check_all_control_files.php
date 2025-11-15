<?php
/**
 * Kontrola VŠECH control_center souborů pro duplicitní getCSRFToken
 */

$dir = __DIR__ . '/includes/';
$files = glob($dir . 'control_center*.php');

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>All Control Center Files Check</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; font-size: 13px; }
        h1 { color: #4ec9b0; }
        .file { background: #252526; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
        .ok { border-left: 4px solid #4ec9b0; }
        .warn { border-left: 4px solid #ce9178; }
        .bad { border-left: 4px solid #f48771; }
        .filename { color: #4ec9b0; font-size: 16px; margin-bottom: 10px; }
        pre { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🔍 VŠECHNY CONTROL CENTER SOUBORY</h1>

    <?php foreach ($files as $file): ?>
        <?php
        $filename = basename($file);
        $content = file_get_contents($file);
        $lines = file($file);

        // Hledat getCSRFToken funkce
        preg_match_all('/function\s+(getCSRFToken\w*)\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE);

        $status = 'ok';
        if (count($matches[0]) > 1) {
            $status = 'bad'; // Více funkcí v jednom souboru
        } elseif (count($matches[0]) == 1 && strpos($matches[1][0][0], 'Sync') === false) {
            // Má getCSRFToken (ne Sync)
            $status = 'warn';
        }
        ?>

        <div class="file <?php echo $status; ?>">
            <div class="filename"><?php echo htmlspecialchars($filename); ?></div>

            <pre>Velikost: <?php echo number_format(strlen($content)); ?> bytů
Řádků: <?php echo count($lines); ?>
Poslední změna: <?php echo date('Y-m-d H:i:s', filemtime($file)); ?></pre>

            <?php if (count($matches[0]) > 0): ?>
                <pre><strong>Nalezené funkce (<?php echo count($matches[0]); ?>):</strong>
<?php foreach ($matches[0] as $idx => $match): ?>
<?php
    $funcName = $matches[1][$idx][0];
    $offset = $match[1];
    $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;
    echo "  → {$funcName}() na řádku {$lineNum}\n";
?>
<?php endforeach; ?>
</pre>
            <?php else: ?>
                <pre>✓ Žádné getCSRFToken funkce</pre>
            <?php endif; ?>

            <?php if ($status == 'bad'): ?>
                <pre style="color: #f48771;"><strong>⚠️ PROBLÉM: Více getCSRFToken funkcí v jednom souboru!</strong></pre>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <h1>📊 CELKOVÉ SHRNUTÍ</h1>
    <pre><?php
    $totalFiles = count($files);
    $filesWithGetCSRF = 0;
    $filesWithMultiple = 0;

    foreach ($files as $file) {
        $content = file_get_contents($file);
        preg_match_all('/function\s+getCSRFToken/', $content, $matches);
        if (count($matches[0]) > 0) $filesWithGetCSRF++;
        if (count($matches[0]) > 1) $filesWithMultiple++;
    }

    echo "Celkem control_center souborů: {$totalFiles}\n";
    echo "Souborů s getCSRFToken*: {$filesWithGetCSRF}\n";
    echo "Souborů s duplicitou: {$filesWithMultiple}\n\n";

    if ($filesWithMultiple > 0) {
        echo "❌ PROBLÉM: Některé soubory mají více getCSRFToken funkcí!\n";
    } elseif ($filesWithGetCSRF > 1) {
        echo "⚠️ UPOZORNĚNÍ: {$filesWithGetCSRF} souborů má getCSRFToken - možná kolize při includování!\n";
    } else {
        echo "✅ OK: Žádné duplicity\n";
    }
    ?></pre>

    <p style="margin-top: 40px; opacity: 0.5; font-size: 11px;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
