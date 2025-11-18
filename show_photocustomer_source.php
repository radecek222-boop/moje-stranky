<?php
/**
 * Zobrazení aktuálního zdrojového kódu photocustomer.php ze serveru
 */
require_once "init.php";

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('403 - Admin only');
}

$file = __DIR__ . '/photocustomer.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Photocustomer Source</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff88; padding: 20px; }
        pre { background: #2a2a2a; padding: 15px; overflow-x: auto; }
        .highlight { background: yellow; color: black; }
    </style>
</head>
<body>
    <h1>photocustomer.php - Aktuální verze ze serveru</h1>
    <p>Timestamp: <?php echo date('Y-m-d H:i:s', filemtime($file)); ?></p>
    <p>Velikost: <?php echo filesize($file); ?> bytů</p>

    <h2>Řádky 1-30 (autentizační logika):</h2>
    <pre><?php
    for ($i = 0; $i < min(30, count($lines)); $i++) {
        $lineNum = $i + 1;
        $line = htmlspecialchars($lines[$i]);

        // Highlight autentizační řádky
        if (stripos($line, 'BEZPEČNOST') !== false ||
            stripos($line, 'isAdmin') !== false ||
            stripos($line, 'isTechnik') !== false ||
            stripos($line, 'role') !== false) {
            echo sprintf("<span class='highlight'>%3d: %s</span>\n", $lineNum, $line);
        } else {
            echo sprintf("%3d: %s\n", $lineNum, $line);
        }
    }
    ?></pre>

    <p><a href="invalidate_photocustomer.php" style="color: #ffc107;">→ Invalidovat OPcache a reload</a></p>
</body>
</html>
