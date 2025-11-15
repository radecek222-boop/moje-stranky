<?php
/**
 * Vyrendruje admin.php a najde řádek 1066
 */

// Nastav session aby admin.php fungoval
session_start();
$_SESSION['is_admin'] = true;
$_SESSION['admin_id'] = 'DEBUG';

// Zachytit output
ob_start();

// Nastav parametry
$_GET['tab'] = 'control_center';

// Include admin.php
try {
    include __DIR__ . '/admin.php';
} catch (Throwable $e) {
    echo "CHYBA: " . $e->getMessage();
}

$html = ob_get_clean();

// Rozdělit na řádky
$lines = explode("\n", $html);
$totalLines = count($lines);

// Najít řádek 1066
$targetLine = 1066;

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rendered Line 1066</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; font-size: 12px; }
        h1 { color: #4ec9b0; }
        .line { padding: 2px 5px; border-left: 3px solid transparent; }
        .target { background: #3a1a1a; border-left: 3px solid #f48771; color: #fff; }
        .ln { color: #858585; width: 60px; display: inline-block; text-align: right; margin-right: 10px; }
        pre { line-height: 1.6; }
        .highlight { background: #f48771; color: #000; padding: 0 3px; }
    </style>
</head>
<body>
    <h1>🔍 RENDEROVANÝ ADMIN.PHP - ŘÁDEK 1066</h1>

    <pre>Celkem řádků po renderování: <?php echo $totalLines; ?>

Target řádek: <?php echo $targetLine; ?>

<?php if ($targetLine <= $totalLines): ?>
✅ Řádek <?php echo $targetLine; ?> existuje
<?php else: ?>
❌ Řádek <?php echo $targetLine; ?> NEEXISTUJE (máme jen <?php echo $totalLines; ?> řádků)
<?php endif; ?>
</pre>

    <h2>Řádky <?php echo max(1, $targetLine - 10); ?> - <?php echo min($totalLines, $targetLine + 10); ?>:</h2>

    <pre><?php
    $start = max(0, $targetLine - 11);
    $end = min($totalLines - 1, $targetLine + 9);

    for ($i = $start; $i <= $end; $i++) {
        $lineNum = $i + 1;
        $line = htmlspecialchars($lines[$i]);

        // Highlight řádku 1066
        $class = ($lineNum == $targetLine) ? 'target' : 'line';

        // Zvýraznit getCSRFToken
        if (strpos($line, 'getCSRFToken') !== false) {
            $line = str_replace('getCSRFToken', '<span class="highlight">getCSRFToken</span>', $line);
        }

        echo sprintf('<div class="%s"><span class="ln">%d</span>%s</div>', $class, $lineNum, $line) . "\n";
    }
    ?></pre>

    <h2>Analýza řádku <?php echo $targetLine; ?>:</h2>
    <pre><?php
    if ($targetLine <= $totalLines) {
        $theLine = $lines[$targetLine - 1];
        echo "Obsah:\n";
        echo htmlspecialchars($theLine) . "\n\n";

        echo "Délka: " . strlen($theLine) . " znaků\n";

        // Hledat podezřelé patterny
        if (preg_match('/\b(const|let|var)\s+\w+\s*=\s*[^;]+$/', $theLine, $match)) {
            echo "\n⚠️ MOŽNÁ CHYBA: Proměnná bez středníku:\n";
            echo htmlspecialchars($match[0]) . "\n";
        }

        if (strpos($theLine, 'function') !== false) {
            echo "\n✓ Obsahuje 'function'\n";
        }

        if (strpos($theLine, 'getCSRFToken') !== false) {
            echo "\n✓ Obsahuje 'getCSRFToken'\n";
        }
    } else {
        echo "❌ Řádek neexistuje\n";
    }
    ?></pre>

    <p style="margin-top: 40px; opacity: 0.5; font-size: 11px;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
