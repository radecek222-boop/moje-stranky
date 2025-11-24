<?php
/**
 * Debug script pro track_heatmap.php
 * Simuluje POST request a zobrazí detailní chybu
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Track Heatmap API</title>
    <style>
        body { font-family: monospace; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Debug Track Heatmap API</h1>";

// ===================================================
// 1. KONTROLA TABULEK
// ===================================================
echo "<h2>1. Kontrola heatmap tabulek</h2>";

try {
    $pdo = getDbConnection();
    echo "<div class='success'>DB pripojeni: OK</div>";

    // Kontrola clicks tabulky
    $stmt = $pdo->query("DESCRIBE wgs_analytics_heatmap_clicks");
    $clicksCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'><strong>wgs_analytics_heatmap_clicks sloupce:</strong><br>" . implode(', ', $clicksCols) . "</div>";

    // Kontrola scroll tabulky
    $stmt = $pdo->query("DESCRIBE wgs_analytics_heatmap_scroll");
    $scrollCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'><strong>wgs_analytics_heatmap_scroll sloupce:</strong><br>" . implode(', ', $scrollCols) . "</div>";

} catch (PDOException $e) {
    echo "<div class='error'>DB chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 2. SIMULACE INSERT (CLICKS)
// ===================================================
echo "<h2>2. Test INSERT do wgs_analytics_heatmap_clicks</h2>";

try {
    $testUrl = 'https://test.example.com/debug';
    $testDevice = 'desktop';
    $testX = 50.00;
    $testY = 25.00;

    $sql = "
        INSERT INTO wgs_analytics_heatmap_clicks (
            page_url,
            device_type,
            click_x_percent,
            click_y_percent,
            click_count,
            viewport_width_avg,
            viewport_height_avg,
            first_click,
            last_click
        ) VALUES (
            :page_url,
            :device_type,
            :click_x_percent,
            :click_y_percent,
            1,
            :viewport_width,
            :viewport_height,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            click_count = click_count + 1,
            last_click = NOW()
    ";

    echo "<div class='info'><strong>SQL:</strong><pre>" . htmlspecialchars($sql) . "</pre></div>";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'page_url' => $testUrl,
        'device_type' => $testDevice,
        'click_x_percent' => $testX,
        'click_y_percent' => $testY,
        'viewport_width' => 1920,
        'viewport_height' => 1080
    ]);

    if ($result) {
        echo "<div class='success'>INSERT clicks: OK</div>";

        // Smazat testovací záznam
        $pdo->exec("DELETE FROM wgs_analytics_heatmap_clicks WHERE page_url = '{$testUrl}'");
        echo "<div class='info'>Testovaci zaznam smazan</div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'><strong>INSERT clicks CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'><strong>SQL State:</strong> " . $e->getCode() . "</div>";
}

// ===================================================
// 3. SIMULACE INSERT (SCROLL)
// ===================================================
echo "<h2>3. Test INSERT do wgs_analytics_heatmap_scroll</h2>";

try {
    $sql = "
        INSERT INTO wgs_analytics_heatmap_scroll (
            page_url,
            device_type,
            scroll_depth_bucket,
            reach_count,
            viewport_width_avg,
            viewport_height_avg,
            first_reach,
            last_reach
        ) VALUES (
            :page_url,
            :device_type,
            :scroll_depth_bucket,
            1,
            :viewport_width,
            :viewport_height,
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            reach_count = reach_count + 1,
            last_reach = NOW()
    ";

    echo "<div class='info'><strong>SQL:</strong><pre>" . htmlspecialchars($sql) . "</pre></div>";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'page_url' => $testUrl,
        'device_type' => $testDevice,
        'scroll_depth_bucket' => 50,
        'viewport_width' => 1920,
        'viewport_height' => 1080
    ]);

    if ($result) {
        echo "<div class='success'>INSERT scroll: OK</div>";

        // Smazat testovací záznam
        $pdo->exec("DELETE FROM wgs_analytics_heatmap_scroll WHERE page_url = '{$testUrl}'");
        echo "<div class='info'>Testovaci zaznam smazan</div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'><strong>INSERT scroll CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 4. KONTROLA CSRF
// ===================================================
echo "<h2>4. Kontrola CSRF</h2>";

require_once __DIR__ . '/includes/csrf_helper.php';

if (function_exists('generateCSRFToken')) {
    $token = generateCSRFToken();
    echo "<div class='success'>generateCSRFToken(): OK</div>";
    echo "<div class='info'>Token: " . substr($token, 0, 20) . "...</div>";

    if (function_exists('validateCSRFToken')) {
        $valid = validateCSRFToken($token);
        echo $valid
            ? "<div class='success'>validateCSRFToken(): OK</div>"
            : "<div class='error'>validateCSRFToken(): FAILED</div>";
    }
} else {
    echo "<div class='error'>generateCSRFToken() neexistuje!</div>";
}

// ===================================================
// 5. KONTROLA RATE LIMITER
// ===================================================
echo "<h2>5. Kontrola Rate Limiter</h2>";

require_once __DIR__ . '/includes/rate_limiter.php';

try {
    $rateLimiter = new RateLimiter($pdo);
    echo "<div class='success'>RateLimiter instance: OK</div>";

    $result = $rateLimiter->checkLimit('test_debug_ip', 'track_heatmap', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    echo "<div class='info'>checkLimit result: " . json_encode($result) . "</div>";

} catch (Exception $e) {
    echo "<div class='error'>RateLimiter chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// ===================================================
// 6. SOUHRN
// ===================================================
echo "<h2>Souhrn</h2>";
echo "<div class='info'>";
echo "Pokud vse vyse ukazuje OK, problem muze byt:<br>";
echo "1. Zmeny z PR jeste nejsou na produkci (mergni PR!)<br>";
echo "2. PHP error pred JSON vystupen (zkontroluj error log)<br>";
echo "3. CSRF token expiroval nebo neni validni<br>";
echo "</div>";

echo "<p><strong>PHP verze:</strong> " . phpversion() . "</p>";
echo "<p><strong>Datum:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "</div></body></html>";
?>
