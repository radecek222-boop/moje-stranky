<?php
/**
 * FIXED TEST - s VALUES() opravenou query
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#f0f0f0; padding:20px;'>";
echo "=== HEATMAP FIXED TEST ===\n\n";

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

try {
    require_once __DIR__ . '/init.php';
    require_once __DIR__ . '/includes/csrf_helper.php';
    require_once __DIR__ . '/includes/api_response.php';
    require_once __DIR__ . '/includes/rate_limiter.php';
    
    $pdo = getDbConnection();
    echo "✓ Dependencies loaded, DB connected\n\n";

    // Test data
    $normalizedUrl = 'https://www.wgs-service.cz/test.php';
    $deviceType = 'desktop';
    
    echo "TEST 1: Click agregace s VALUES()\n";
    echo "=====================================\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO wgs_analytics_heatmap_clicks (
            page_url, device_type, click_x_percent, click_y_percent,
            click_count, viewport_width_avg, viewport_height_avg,
            first_click, last_click
        ) VALUES (
            :page_url, :device_type, :click_x_percent, :click_y_percent,
            1, :viewport_width, :viewport_height, NOW(), NOW()
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
    
    echo "Executing click query...\n";
    $stmt->execute([
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType,
        'click_x_percent' => 50.0,
        'click_y_percent' => 30.0,
        'viewport_width' => 1920,
        'viewport_height' => 1080
    ]);
    echo "✓ Click query SUCCESS!\n\n";

    echo "TEST 2: Scroll agregace s VALUES()\n";
    echo "=====================================\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO wgs_analytics_heatmap_scroll (
            page_url, device_type, scroll_depth_bucket,
            reach_count, viewport_width_avg, viewport_height_avg,
            first_reach, last_reach
        ) VALUES (
            :page_url, :device_type, :scroll_depth_bucket,
            1, :viewport_width, :viewport_height, NOW(), NOW()
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
    
    echo "Executing scroll query...\n";
    $stmt->execute([
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType,
        'scroll_depth_bucket' => 0,
        'viewport_width' => 1920,
        'viewport_height' => 1080
    ]);
    echo "✓ Scroll query SUCCESS!\n\n";

    echo "=== ✓✓✓ ALL TESTS PASSED ✓✓✓ ===\n";
    echo "\nOpraven SQL query funguje správně!\n";
    echo "track_heatmap.php by měl fungovat nyní.\n";

} catch (Throwable $e) {
    echo "\n!!! ERROR !!!\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
