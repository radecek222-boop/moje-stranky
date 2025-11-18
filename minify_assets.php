<?php
/**
 * Asset Minification Tool
 * Minifikuje JS a CSS soubory pro optimalizaci výkonu
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - Admin access required');
}

/**
 * Minifikuje JavaScript kód
 */
function minifyJS($code) {
    // 1. Odstranit jednořádkové komentáře
    $code = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/', '', $code);

    // 2. Odstranit víceřádkové komentáře
    $code = preg_replace('!/\*.*?\*/!s', '', $code);

    // 3. Odstranit whitespace okolo operátorů
    $code = preg_replace('/\s*([\{\}\;\:\,\=\<\>\&\|\!\+\-\*\/\%\(\)\[\]])\s*/', '$1', $code);

    // 4. Odstranit prázdné řádky a nadbytečné mezery
    $code = preg_replace('/\s+/', ' ', $code);

    // 5. Odstranit mezery kolem {  } ;
    $code = str_replace([' {', '{ ', ' }', '} ', ' ;', '; '], ['{', '{', '}', '}', ';', ';'], $code);

    return trim($code);
}

/**
 * Minifikuje CSS kód
 */
function minifyCSS($code) {
    // 1. Odstranit komentáře
    $code = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code);

    // 2. Odstranit whitespace
    $code = preg_replace('/\s+/', ' ', $code);

    // 3. Odstranit mezery okolo : ; { } , >
    $code = str_replace([' {', '{ ', ' }', '} ', ' :', ': ', ' ;', '; ', ' ,', ', ', ' >'],
                        ['{', '{', '}', '}', ':', ':', ';', ';', ',', ',', '>'], $code);

    // 4. Odstranit poslední ; před }
    $code = str_replace(';}', '}', $code);

    return trim($code);
}

// HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Asset Minification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1400px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; margin: 10px 0; background: #d4edda; border-left: 4px solid #28a745; }
        .error { color: #dc3545; padding: 10px; margin: 10px 0; background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { color: #856404; padding: 10px; margin: 10px 0; background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { color: #0c5460; padding: 10px; margin: 10px 0; background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .summary { margin-top: 30px; padding: 20px; background: #e7f3ff; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .size-reduction { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
<h1>🗜️ Asset Minification</h1>
";

$rootDir = __DIR__;
$jsDir = $rootDir . '/assets/js';
$cssDir = $rootDir . '/assets/css';

$minified = 0;
$skipped = 0;
$errors = 0;
$totalSizeBefore = 0;
$totalSizeAfter = 0;

echo "<h2>JavaScript Files</h2>";
echo "<table><tr><th>File</th><th>Original Size</th><th>Minified Size</th><th>Reduction</th><th>Status</th></tr>";

// Minifikace JS souborů
if (is_dir($jsDir)) {
    $jsFiles = glob($jsDir . '/*.js');

    foreach ($jsFiles as $file) {
        $basename = basename($file);

        // Skip již minifikovaných souborů
        if (strpos($basename, '.min.js') !== false) {
            $skipped++;
            continue;
        }

        try {
            $originalCode = file_get_contents($file);
            $originalSize = strlen($originalCode);
            $totalSizeBefore += $originalSize;

            $minifiedCode = minifyJS($originalCode);
            $minifiedSize = strlen($minifiedCode);
            $totalSizeAfter += $minifiedSize;

            $reduction = $originalSize > 0 ? round((($originalSize - $minifiedSize) / $originalSize) * 100, 1) : 0;

            // Uložit minified verzi
            $minFile = str_replace('.js', '.min.js', $file);
            file_put_contents($minFile, $minifiedCode);

            echo "<tr>
                <td>{$basename}</td>
                <td>" . number_format($originalSize) . " B</td>
                <td>" . number_format($minifiedSize) . " B</td>
                <td class='size-reduction'>-{$reduction}%</td>
                <td><span style='color: #28a745;'>✅ Minified</span></td>
            </tr>";

            $minified++;

        } catch (Exception $e) {
            echo "<tr>
                <td>{$basename}</td>
                <td colspan='4'><span style='color: #dc3545;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span></td>
            </tr>";
            $errors++;
        }
    }
}

echo "</table>";

echo "<h2>CSS Files</h2>";
echo "<table><tr><th>File</th><th>Original Size</th><th>Minified Size</th><th>Reduction</th><th>Status</th></tr>";

// Minifikace CSS souborů
if (is_dir($cssDir)) {
    $cssFiles = glob($cssDir . '/*.css');

    foreach ($cssFiles as $file) {
        $basename = basename($file);

        // Skip již minifikovaných souborů
        if (strpos($basename, '.min.css') !== false) {
            $skipped++;
            continue;
        }

        try {
            $originalCode = file_get_contents($file);
            $originalSize = strlen($originalCode);
            $totalSizeBefore += $originalSize;

            $minifiedCode = minifyCSS($originalCode);
            $minifiedSize = strlen($minifiedCode);
            $totalSizeAfter += $minifiedSize;

            $reduction = $originalSize > 0 ? round((($originalSize - $minifiedSize) / $originalSize) * 100, 1) : 0;

            // Uložit minified verzi
            $minFile = str_replace('.css', '.min.css', $file);
            file_put_contents($minFile, $minifiedCode);

            echo "<tr>
                <td>{$basename}</td>
                <td>" . number_format($originalSize) . " B</td>
                <td>" . number_format($minifiedSize) . " B</td>
                <td class='size-reduction'>-{$reduction}%</td>
                <td><span style='color: #28a745;'>✅ Minified</span></td>
            </tr>";

            $minified++;

        } catch (Exception $e) {
            echo "<tr>
                <td>{$basename}</td>
                <td colspan='4'><span style='color: #dc3545;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span></td>
            </tr>";
            $errors++;
        }
    }
}

echo "</table>";

$totalReduction = $totalSizeBefore > 0 ? round((($totalSizeBefore - $totalSizeAfter) / $totalSizeBefore) * 100, 1) : 0;

echo "<div class='summary'>
    <h2>📊 Summary</h2>
    <ul>
        <li><strong>✅ Minified:</strong> {$minified} files</li>
        <li><strong>⏭️ Skipped:</strong> {$skipped} files (already .min files)</li>
        <li><strong>❌ Errors:</strong> {$errors} files</li>
    </ul>
    <h3>Size Reduction</h3>
    <ul>
        <li><strong>Original Total:</strong> " . number_format($totalSizeBefore) . " B (" . round($totalSizeBefore / 1024, 2) . " KB)</li>
        <li><strong>Minified Total:</strong> " . number_format($totalSizeAfter) . " B (" . round($totalSizeAfter / 1024, 2) . " KB)</li>
        <li><strong>Saved:</strong> <span class='size-reduction'>" . number_format($totalSizeBefore - $totalSizeAfter) . " B (" . round(($totalSizeBefore - $totalSizeAfter) / 1024, 2) . " KB) - {$totalReduction}%</span></li>
    </ul>
    <div class='info'>
        <strong>💡 Next Step:</strong> Update your HTML files to use .min.js and .min.css versions for production.
        <br><br>Example: Change <code>script.js</code> → <code>script.min.js</code>
    </div>
    <p><a href='/admin.php'>← Back to Admin Dashboard</a></p>
</div>";

echo "</div></body></html>";
