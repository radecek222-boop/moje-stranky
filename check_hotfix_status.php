<?php
/**
 * Zkontroluje jestli je hotfix aplikován
 */

$filePath = __DIR__ . '/includes/control_center_testing_interactive.php';
$content = file_get_contents($filePath);

// Hledat problematickou část
$hasDuplicateFunction = strpos($content, 'function getCSRFToken()') !== false;
$hasFixedVersion = strpos($content, 'function getCSRFTokenSync()') !== false;

?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hotfix Status Check</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; padding: 40px; }
        .ok { color: #0f0; }
        .bad { color: #f00; }
        pre { background: #111; padding: 20px; border: 1px solid #333; }
    </style>
</head>
<body>
    <h1>🔍 HOTFIX STATUS CHECK</h1>

    <h2>Soubor: control_center_testing_interactive.php</h2>

    <p>Má duplicitní getCSRFToken():
        <strong class="<?php echo $hasDuplicateFunction ? 'bad' : 'ok'; ?>">
            <?php echo $hasDuplicateFunction ? '✗ ANO (PROBLÉM!)' : '✓ NE'; ?>
        </strong>
    </p>

    <p>Má opravenou getCSRFTokenSync():
        <strong class="<?php echo $hasFixedVersion ? 'ok' : 'bad'; ?>">
            <?php echo $hasFixedVersion ? '✓ ANO' : '✗ NE (PROBLÉM!)'; ?>
        </strong>
    </p>

    <h2>Velikost souboru:</h2>
    <p><?php echo filesize($filePath); ?> bytů</p>

    <h2>Poslední modifikace:</h2>
    <p><?php echo date('Y-m-d H:i:s', filemtime($filePath)); ?></p>

    <hr>

    <?php if ($hasDuplicateFunction): ?>
        <h2 style="color: #f00;">❌ HOTFIX NEBYL APLIKOVÁN!</h2>
        <p>Server pořád má starou verzi s duplicitní funkcí.</p>
        <p><strong>Řešení:</strong> Proveď git pull nebo aplikuj hotfix znovu.</p>
    <?php elseif ($hasFixedVersion): ?>
        <h2 style="color: #0f0;">✅ HOTFIX JE APLIKOVÁN!</h2>
        <p>Pokud pořád vidíš chybu, je to CACHE problém.</p>
        <p><strong>Řešení:</strong> Hard refresh (Cmd+Shift+R) nebo inkognito okno.</p>
    <?php else: ?>
        <h2 style="color: #ff0;">⚠️ NEZNÁMÝ STAV</h2>
        <p>Soubor má nějaký jiný obsah.</p>
    <?php endif; ?>

    <hr>
    <p style="font-size: 12px; color: #666;">
        Check time: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
