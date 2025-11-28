<?php
/**
 * Debug verze track_heatmap.php - zobrazí všechny errory
 */

// VYPNOUT veškerý error handling - zobrazit raw PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre style='background:#f0f0f0; padding:20px; font-family:monospace;'>";
echo "=== TRACK HEATMAP DEBUG ===\n\n";

// Simulovat POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

try {
    echo "1. Loading dependencies...\n";
    require_once __DIR__ . '/init.php';
    require_once __DIR__ . '/includes/csrf_helper.php';
    require_once __DIR__ . '/includes/api_response.php';
    require_once __DIR__ . '/includes/rate_limiter.php';
    echo "   ✓ All dependencies loaded\n\n";

    echo "2. Getting DB connection...\n";
    $pdo = getDbConnection();
    echo "   ✓ DB connected\n\n";

    echo "3. Testing Rate Limiter...\n";
    $clientIp = '127.0.0.1';
    $rateLimiter = new RateLimiter($pdo);
    
    echo "   Calling checkLimit()...\n";
    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_heatmap', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);
    echo "   ✓ Rate limit check passed\n";
    echo "   Result: " . json_encode($rateLimitResult, JSON_PRETTY_PRINT) . "\n\n";

    echo "4. Preparing test data...\n";
    $inputData = [
        'page_url' => 'https://www.wgs-service.cz/cenik.php',
        'device_type' => 'desktop',
        'clicks' => [
            ['x_percent' => 50, 'y_percent' => 30, 'viewport_width' => 1920, 'viewport_height' => 1080]
        ],
        'scroll_depths' => [0, 10, 20],
        'csrf_token' => 'test_token_bypass'
    ];
    echo "   ✓ Test data prepared\n\n";

    echo "5. Validating URL...\n";
    $pageUrl = filter_var($inputData['page_url'], FILTER_VALIDATE_URL);
    echo "   Validated URL: $pageUrl\n";
    
    $parsedUrl = parse_url($pageUrl);
    $normalizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '/');
    echo "   Normalized URL: $normalizedUrl\n\n";

    echo "6. Processing CLICKS...\n";
    $clicksAggregated = 0;
    
    foreach ($inputData['clicks'] as $click) {
        echo "   Click: x={$click['x_percent']}%, y={$click['y_percent']}%\n";
        
        $xPercent = round((float)$click['x_percent'], 2);
        $yPercent = round((float)$click['y_percent'], 2);
        
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
        
        $stmt->execute([
            'page_url' => $normalizedUrl,
            'device_type' => 'desktop',
            'click_x_percent' => $xPercent,
            'click_y_percent' => $yPercent,
            'viewport_width' => $click['viewport_width'] ?? null,
            'viewport_height' => $click['viewport_height'] ?? null
        ]);
        
        $clicksAggregated++;
        echo "   ✓ Click agregován\n";
    }
    echo "   Total clicks: $clicksAggregated\n\n";

    echo "7. Processing SCROLL DEPTHS...\n";
    $scrollBucketsUpdated = 0;
    
    foreach ($inputData['scroll_depths'] as $scrollDepth) {
        $depth = (int)$scrollDepth;
        $bucket = floor($depth / 10) * 10;
        
        echo "   Scroll depth: {$depth}% → bucket: {$bucket}%\n";
        
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
        
        $stmt->execute([
            'page_url' => $normalizedUrl,
            'device_type' => 'desktop',
            'scroll_depth_bucket' => $bucket,
            'viewport_width' => null,
            'viewport_height' => null
        ]);
        
        $scrollBucketsUpdated++;
        echo "   ✓ Scroll bucket agregován\n";
    }
    echo "   Total scroll buckets: $scrollBucketsUpdated\n\n";

    echo "=== SUCCESS ===\n";
    echo "Clicks agregated: $clicksAggregated\n";
    echo "Scroll buckets updated: $scrollBucketsUpdated\n";

} catch (Throwable $e) {
    echo "\n!!! FATAL ERROR !!!\n\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n</pre>";
?>
