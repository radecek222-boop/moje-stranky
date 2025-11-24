<?php
/**
 * DEBUG verze track_heatmap.php
 * Postupně testuje každý krok s debug výstupem
 */

// Vypnout output buffering pro okamžitý výstup
while (ob_get_level()) {
    ob_end_clean();
}

// Nastavit plain text pro čitelnost
header('Content-Type: text/plain; charset=utf-8');

echo "=== TRACK HEATMAP DEBUG ===\n\n";

try {
    echo "1. Loading init.php...";
    require_once __DIR__ . '/../init.php';
    echo " OK\n";

    echo "2. Loading csrf_helper.php...";
    require_once __DIR__ . '/../includes/csrf_helper.php';
    echo " OK\n";

    echo "3. Loading api_response.php...";
    require_once __DIR__ . '/../includes/api_response.php';
    echo " OK\n";

    echo "4. Loading rate_limiter.php...";
    require_once __DIR__ . '/../includes/rate_limiter.php';
    echo " OK\n";

    echo "5. Checking REQUEST_METHOD...";
    echo " [" . $_SERVER['REQUEST_METHOD'] . "]";
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo " FAILED - not POST\n";
        exit(1);
    }
    echo " OK\n";

    echo "6. Getting DB connection...";
    $pdo = getDbConnection();
    echo " OK\n";

    echo "7. Getting client IP...";
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    echo " [" . $clientIp . "] OK\n";

    echo "8. Creating RateLimiter...";
    $rateLimiter = new RateLimiter($pdo);
    echo " OK\n";

    echo "9. Checking rate limit...";
    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_heatmap', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        echo " BLOCKED: " . $rateLimitResult['message'] . "\n";
        exit(1);
    }
    echo " OK (allowed)\n";

    echo "10. Reading php://input...";
    $rawInput = file_get_contents('php://input');
    echo " [" . strlen($rawInput) . " bytes]";
    if (empty($rawInput)) {
        echo " EMPTY!\n";
        echo "    Trying \$_POST instead...\n";
    } else {
        echo " OK\n";
    }

    echo "11. Decoding JSON...";
    $inputData = json_decode($rawInput, true);

    if (!$inputData) {
        echo " FAILED, trying \$_POST...\n";
        $inputData = $_POST;
        echo "    Using \$_POST: " . json_encode($inputData) . "\n";
    } else {
        echo " OK\n";
        echo "    Data keys: " . implode(', ', array_keys($inputData)) . "\n";
    }

    echo "12. Getting CSRF token...";
    $csrfToken = $inputData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    echo " [" . substr($csrfToken, 0, 20) . "...]";
    if (empty($csrfToken)) {
        echo " EMPTY!\n";
        exit(1);
    }
    echo " OK\n";

    echo "13. Validating CSRF token...";
    if (!function_exists('validateCSRFToken')) {
        echo " FUNCTION NOT FOUND!\n";
        exit(1);
    }

    $isValid = validateCSRFToken($csrfToken);
    if (!$isValid) {
        echo " INVALID!\n";
        echo "    Token from request: " . $csrfToken . "\n";
        echo "    Session ID: " . session_id() . "\n";
        echo "    Session csrf_token: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "\n";
        exit(1);
    }
    echo " VALID\n";

    echo "14. Validating page_url...";
    if (empty($inputData['page_url'])) {
        echo " MISSING!\n";
        exit(1);
    }
    $pageUrl = filter_var($inputData['page_url'], FILTER_VALIDATE_URL);
    if (!$pageUrl) {
        echo " INVALID URL!\n";
        exit(1);
    }
    echo " OK [" . $pageUrl . "]\n";

    echo "15. Validating device_type...";
    if (empty($inputData['device_type'])) {
        echo " MISSING!\n";
        exit(1);
    }
    $deviceType = $inputData['device_type'];
    $allowed = ['desktop', 'mobile', 'tablet'];
    if (!in_array($deviceType, $allowed)) {
        echo " INVALID [" . $deviceType . "]!\n";
        exit(1);
    }
    echo " OK [" . $deviceType . "]\n";

    echo "16. Processing clicks...";
    $clicksCount = 0;
    if (!empty($inputData['clicks']) && is_array($inputData['clicks'])) {
        $clicksCount = count($inputData['clicks']);
        echo " [" . $clicksCount . " clicks]";

        foreach ($inputData['clicks'] as $click) {
            if (!isset($click['x_percent']) || !isset($click['y_percent'])) {
                continue;
            }

            $xPercent = round((float)$click['x_percent'], 2);
            $yPercent = round((float)$click['y_percent'], 2);

            // Test SQL prepare
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
                    last_click = NOW()
            ");

            $stmt->execute([
                'page_url' => $pageUrl,
                'device_type' => $deviceType,
                'click_x_percent' => $xPercent,
                'click_y_percent' => $yPercent,
                'viewport_width' => $click['viewport_width'] ?? null,
                'viewport_height' => $click['viewport_height'] ?? null
            ]);
        }
        echo " OK\n";
    } else {
        echo " [0 clicks] OK\n";
    }

    echo "17. Processing scroll_depths...";
    $scrollCount = 0;
    if (!empty($inputData['scroll_depths']) && is_array($inputData['scroll_depths'])) {
        $scrollCount = count($inputData['scroll_depths']);
        echo " [" . $scrollCount . " depths]";

        foreach ($inputData['scroll_depths'] as $depth) {
            $bucket = floor($depth / 10) * 10;

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
                    last_reach = NOW()
            ");

            $stmt->execute([
                'page_url' => $pageUrl,
                'device_type' => $deviceType,
                'scroll_depth_bucket' => $bucket,
                'viewport_width' => $inputData['viewport_width'] ?? null,
                'viewport_height' => $inputData['viewport_height'] ?? null
            ]);
        }
        echo " OK\n";
    } else {
        echo " [0 depths] OK\n";
    }

    echo "\n=== SUCCESS ===\n";
    echo "Clicks aggregated: " . $clicksCount . "\n";
    echo "Scroll depths updated: " . $scrollCount . "\n";

} catch (Throwable $e) {
    echo "\n\n=== ERROR ===\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
