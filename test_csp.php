<?php
/**
 * TEST CSP HEADER
 * Zkontrolovat jestli nový security_headers.php se načetl
 * URL: https://www.wgs-service.cz/test_csp.php
 */

// Načíst init.php (includuje security_headers.php)
require_once __DIR__ . '/init.php';

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>CSP Test</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre {
            background: white;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 CSP Header Test</h1>

    <h2>Aktuální CSP z security_headers.php:</h2>
    <pre><?php
    // Načíst CSP ze security_headers.php
    $csp = [
        "default-src 'self' https:",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://fonts.googleapis.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com",
        "img-src 'self' data: blob: https: https://tile.openstreetmap.org https://*.tile.openstreetmap.org",
        "font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com",
        "connect-src 'self' https:",
        "frame-src 'self' blob:", // Povolit blob URLs v iframe (PDF preview)
        "worker-src 'self' blob:", // Povolit PDF.js workers
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'"
    ];

    $cspString = implode("; ", $csp);
    echo htmlspecialchars($cspString);
    ?></pre>

    <h2>✅ Kontrola frame-src:</h2>
    <?php
    if (strpos($cspString, "frame-src 'self' blob:") !== false) {
        echo '<p class="success">✅ frame-src \'self\' blob: PŘÍTOMNO!</p>';
    } else {
        echo '<p class="error">❌ frame-src \'self\' blob: CHYBÍ!</p>';
    }

    if (strpos($cspString, "worker-src 'self' blob:") !== false) {
        echo '<p class="success">✅ worker-src \'self\' blob: PŘÍTOMNO!</p>';
    } else {
        echo '<p class="error">❌ worker-src \'self\' blob: CHYBÍ!</p>';
    }
    ?>

    <h2>📋 Response Headers (skutečné):</h2>
    <p>Otevři Developer Tools → Network → Reload → Klikni na "test_csp.php" → Headers → Response Headers</p>
    <p>Zkontroluj jestli skutečný CSP header obsahuje <code>frame-src 'self' blob:</code></p>

    <h2>🔄 Soubor security_headers.php:</h2>
    <pre><?php
    $securityHeadersPath = __DIR__ . '/includes/security_headers.php';
    if (file_exists($securityHeadersPath)) {
        echo "✅ Soubor existuje\n";
        echo "📅 Poslední modifikace: " . date('Y-m-d H:i:s', filemtime($securityHeadersPath)) . "\n";
        echo "📏 Velikost: " . filesize($securityHeadersPath) . " bytes\n\n";

        // Zobrazit obsah souboru
        echo "OBSAH:\n";
        echo htmlspecialchars(file_get_contents($securityHeadersPath));
    } else {
        echo "❌ Soubor nenalezen!";
    }
    ?></pre>
</body>
</html>
