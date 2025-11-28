<?php
/**
 * Minimální test track_heatmap.php - postupné testování komponent
 */

// MUSÍME vypnout output buffering pro okamžitý výstup
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== MINIMAL TRACK HEATMAP TEST ===\n\n";

// 1. TEST: Basic PHP
echo "1. Basic PHP: OK\n";
flush();

// 2. TEST: Init.php
echo "2. Loading init.php...";
try {
    require_once __DIR__ . '/init.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 3. TEST: CSRF Helper
echo "3. Loading csrf_helper.php...";
try {
    require_once __DIR__ . '/includes/csrf_helper.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 4. TEST: API Response
echo "4. Loading api_response.php...";
try {
    require_once __DIR__ . '/includes/api_response.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 5. TEST: Rate Limiter
echo "5. Loading rate_limiter.php...";
try {
    require_once __DIR__ . '/includes/rate_limiter.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 6. TEST: DB Connection
echo "6. Testing DB connection...";
try {
    $pdo = getDbConnection();
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 7. TEST: Rate Limiter Instance
echo "7. Creating RateLimiter instance...";
try {
    $rateLimiter = new RateLimiter($pdo);
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 8. TEST: Prepare test data
echo "8. Preparing test data...";
$normalizedUrl = 'https://www.wgs-service.cz/test.php';
$deviceType = 'desktop';
$xPercent = 50.0;
$yPercent = 30.0;
$viewportWidth = 1920;
$viewportHeight = 1080;
echo " OK\n";
flush();

// 9. TEST: SQL Insert Click (dry run - prepare only)
echo "9. Testing SQL prepare for clicks...";
try {
    $stmt = $pdo->prepare("
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
            viewport_width_avg = IF(VALUES(viewport_width_avg) IS NOT NULL,
                (viewport_width_avg * click_count + VALUES(viewport_width_avg)) / (click_count + 1),
                viewport_width_avg
            ),
            viewport_height_avg = IF(VALUES(viewport_height_avg) IS NOT NULL,
                (viewport_height_avg * click_count + VALUES(viewport_height_avg)) / (click_count + 1),
                viewport_height_avg
            ),
            last_click = NOW()
    ");
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    exit(1);
}
flush();

// 10. TEST: SQL Execute Click
echo "10. Testing SQL execute for clicks...";
try {
    $stmt->execute([
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType,
        'click_x_percent' => $xPercent,
        'click_y_percent' => $yPercent,
        'viewport_width' => $viewportWidth,
        'viewport_height' => $viewportHeight
    ]);
    echo " OK (inserted/updated)\n";
} catch (Throwable $e) {
    echo " FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    exit(1);
}
flush();

// 11. TEST: SQL Insert Scroll (dry run)
echo "11. Testing SQL prepare for scroll...";
try {
    $bucket = 10;
    $stmt = $pdo->prepare("
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
            viewport_width_avg = IF(VALUES(viewport_width_avg) IS NOT NULL,
                (viewport_width_avg * reach_count + VALUES(viewport_width_avg)) / (reach_count + 1),
                viewport_width_avg
            ),
            viewport_height_avg = IF(VALUES(viewport_height_avg) IS NOT NULL,
                (viewport_height_avg * reach_count + VALUES(viewport_height_avg)) / (reach_count + 1),
                viewport_height_avg
            ),
            last_reach = NOW()
    ");
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

// 12. TEST: SQL Execute Scroll
echo "12. Testing SQL execute for scroll...";
try {
    $stmt->execute([
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType,
        'scroll_depth_bucket' => $bucket,
        'viewport_width' => $viewportWidth,
        'viewport_height' => $viewportHeight
    ]);
    echo " OK (inserted/updated)\n";
} catch (Throwable $e) {
    echo " FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
flush();

echo "\n=== ALL TESTS PASSED ===\n";
echo "Track heatmap komponenty fungují správně!\n";
?>
