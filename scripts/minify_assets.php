<?php
/**
 * Minifikace JS a CSS souborů
 * Vytvoří .min.js a .min.css verze všech assets
 */

require_once __DIR__ . '/../init.php';

echo "=== MINIFIKACE JS A CSS SOUBORŮ ===\n\n";

$assetsDir = __DIR__ . '/../assets';
$minified = 0;
$skipped = 0;
$errors = 0;

/**
 * Minify JavaScript
 */
function minifyJS($code) {
    // Odstranit komentáře
    $code = preg_replace('!/\*.*?\*/!s', '', $code);
    $code = preg_replace('/\/\/.*$/m', '', $code);

    // Odstranit whitespace
    $code = preg_replace('/\s+/', ' ', $code);
    $code = preg_replace('/\s*([{}();,:])\s*/', '$1', $code);

    return trim($code);
}

/**
 * Minify CSS
 */
function minifyCSS($code) {
    // Odstranit komentáře
    $code = preg_replace('!/\*.*?\*/!s', '', $code);

    // Odstranit whitespace
    $code = preg_replace('/\s+/', ' ', $code);
    $code = preg_replace('/\s*([{}();,:])\s*/', '$1', $code);
    $code = preg_replace('/;}/', '}', $code);

    return trim($code);
}

try {
    // Minifikovat JS soubory
    echo "📦 Minifikuji JavaScript soubory...\n";
    $jsFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($assetsDir . '/js', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($jsFiles as $file) {
        if ($file->getExtension() === 'js' && !str_contains($file->getFilename(), '.min.')) {
            $filePath = $file->getPathname();
            $minPath = str_replace('.js', '.min.js', $filePath);

            // Skip pokud minifikovaná verze už existuje a je novější
            if (file_exists($minPath) && filemtime($minPath) >= filemtime($filePath)) {
                echo "  ⏭️  " . $file->getFilename() . " - SKIP (již aktuální)\n";
                $skipped++;
                continue;
            }

            try {
                $code = file_get_contents($filePath);
                $minified_code = minifyJS($code);
                file_put_contents($minPath, $minified_code);

                $originalSize = filesize($filePath);
                $minifiedSize = filesize($minPath);
                $savings = round((1 - ($minifiedSize / $originalSize)) * 100, 1);

                echo "  ✅ " . $file->getFilename() . " → .min.js ({$savings}% úspora)\n";
                $minified++;
            } catch (Exception $e) {
                echo "  ❌ " . $file->getFilename() . " - CHYBA: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    // Minifikovat CSS soubory
    echo "\n📦 Minifikuji CSS soubory...\n";
    $cssFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($assetsDir . '/css', RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($cssFiles as $file) {
        if ($file->getExtension() === 'css' && !str_contains($file->getFilename(), '.min.')) {
            $filePath = $file->getPathname();
            $minPath = str_replace('.css', '.min.css', $filePath);

            // Skip pokud minifikovaná verze už existuje a je novější
            if (file_exists($minPath) && filemtime($minPath) >= filemtime($filePath)) {
                echo "  ⏭️  " . $file->getFilename() . " - SKIP (již aktuální)\n";
                $skipped++;
                continue;
            }

            try {
                $code = file_get_contents($filePath);
                $minified_code = minifyCSS($code);
                file_put_contents($minPath, $minified_code);

                $originalSize = filesize($filePath);
                $minifiedSize = filesize($minPath);
                $savings = round((1 - ($minifiedSize / $originalSize)) * 100, 1);

                echo "  ✅ " . $file->getFilename() . " → .min.css ({$savings}% úspora)\n";
                $minified++;
            } catch (Exception $e) {
                echo "  ❌ " . $file->getFilename() . " - CHYBA: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    echo "\n=== SHRNUTÍ ===\n";
    echo "Minifikováno: {$minified}\n";
    echo "Přeskočeno: {$skipped}\n";
    echo "Chyby: {$errors}\n";

    if ($minified > 0) {
        echo "\n✅ SUCCESS: Soubory byly minifikovány!\n";
        echo "💡 TIP: Pro produkci používejte .min.js a .min.css verze\n";
    } else if ($skipped > 0) {
        echo "\nℹ️  INFO: Všechny soubory jsou již aktuální\n";
    }

} catch (Exception $e) {
    echo "❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
