<?php
/**
 * Track Heatmap API - Agregace dat pro heatmap
 *
 * Tento endpoint přijímá click a scroll data z Modulu #5 a agreguje je
 * do heatmap tabulek pro rychlou vizualizaci.
 *
 * Používá UPSERT pattern (INSERT ON DUPLICATE KEY UPDATE) pro efektivní agregaci.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST metoda je povolena', 405);
}

try {
    $pdo = getDbConnection();

    // Rate limiting - 1000 požadavků za hodinu per IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_heatmap', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // Získání JSON dat
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (!$inputData) {
        $inputData = $_POST;
    }

    // POZNÁMKA: CSRF validace NENÍ potřeba pro heatmap tracking
    // Důvody:
    // 1. Je to pasivní tracking (read-only data collection)
    // 2. Každý návštěvník trackuje své vlastní kliky
    // 3. Není zde žádná "nežádoucí akce" kterou by CSRF mohlo zneužít
    // 4. Rate limiting (1000 req/hour) už chrání před abuse
    // 5. Session cookies nefungují správně při AJAX calls ze stránek
    //
    // CSRF je relevantní pro: DELETE, UPDATE, CREATE akcí admin operací
    // CSRF NENÍ relevantní pro: Pasivní analytics tracking

    // ✅ PERFORMANCE FIX: Uvolnit session lock pro paralelní zpracování
    // Audit 2025-11-24: Heatmap tracking - vysoká frekvence requestů
    session_write_close();

    // ========================================
    // VALIDACE POVINNÝCH POLÍ
    // ========================================
    if (empty($inputData['page_url'])) {
        sendJsonError('Chybí povinné pole: page_url', 400);
    }

    if (empty($inputData['device_type'])) {
        sendJsonError('Chybí povinné pole: device_type', 400);
    }

    // ========================================
    // SANITIZACE A VALIDACE DAT
    // ========================================
    // Bezpečná URL validace (FILTER_VALIDATE_URL selže na hash/query/diakritice)
    $pageUrlRaw = trim($inputData['page_url']);

    // Odstranit hash a query parametry před parsováním
    $pageUrlClean = strtok($pageUrlRaw, '?#');

    // Bezpečné parse_url
    $parsedUrl = parse_url($pageUrlClean);

    if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
        sendJsonError('Neplatná URL adresa', 400);
    }

    // Bezpečná normalizace URL
    $scheme = $parsedUrl['scheme'] ?? 'https';
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] ?? '/';
    $normalizedUrl = $scheme . '://' . $host . $path;

    // Validace device_type
    $deviceType = sanitizeInput($inputData['device_type']);
    $povoleneDeviceTypes = ['desktop', 'mobile', 'tablet'];
    if (!in_array($deviceType, $povoleneDeviceTypes)) {
        sendJsonError('Neplatný device_type (povolené: desktop, mobile, tablet)', 400);
    }

    $clicksAggregated = 0;
    $scrollBucketsUpdated = 0;

    // ========================================
    // AGREGACE CLICK DATA
    // ========================================
    if (!empty($inputData['clicks']) && is_array($inputData['clicks'])) {
        foreach ($inputData['clicks'] as $click) {
            // Akceptovat oba formáty: x/y (z JS) nebo x_percent/y_percent (starší formát)
            $xValue = $click['x'] ?? $click['x_percent'] ?? null;
            $yValue = $click['y'] ?? $click['y_percent'] ?? null;

            if ($xValue === null || $yValue === null) {
                continue; // Přeskočit neplatné kliky
            }

            $xPercent = round((float)$xValue, 2);
            $yPercent = round((float)$yValue, 2);

            // Validace rozsahu (0-100%)
            if ($xPercent < 0 || $xPercent > 100 || $yPercent < 0 || $yPercent > 100) {
                continue;
            }

            // Viewport data (optional)
            $viewportWidth = isset($click['viewport_width']) ? (int)$click['viewport_width'] : null;
            $viewportHeight = isset($click['viewport_height']) ? (int)$click['viewport_height'] : null;

            // UPSERT: INSERT nebo UPDATE click_count
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

            $stmt->execute([
                'page_url' => $normalizedUrl,
                'device_type' => $deviceType,
                'click_x_percent' => $xPercent,
                'click_y_percent' => $yPercent,
                'viewport_width' => $viewportWidth,
                'viewport_height' => $viewportHeight
            ]);

            $clicksAggregated++;
        }
    }

    // ========================================
    // AGREGACE SCROLL DATA
    // ========================================
    // Akceptovat oba formáty: scrolls (z JS) nebo scroll_depths (starší formát)
    $scrollData = $inputData['scrolls'] ?? $inputData['scroll_depths'] ?? [];
    if (!empty($scrollData) && is_array($scrollData)) {
        foreach ($scrollData as $scrollDepth) {
            $depth = (int)$scrollDepth;

            // Validace rozsahu (0-100)
            if ($depth < 0 || $depth > 100) {
                continue;
            }

            // Bucket do 10% intervalů (0, 10, 20, ..., 100)
            $bucket = floor($depth / 10) * 10;

            // Viewport data (optional)
            $viewportWidth = isset($inputData['viewport_width']) ? (int)$inputData['viewport_width'] : null;
            $viewportHeight = isset($inputData['viewport_height']) ? (int)$inputData['viewport_height'] : null;

            // UPSERT: INSERT nebo UPDATE reach_count
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

            $stmt->execute([
                'page_url' => $normalizedUrl,
                'device_type' => $deviceType,
                'scroll_depth_bucket' => $bucket,
                'viewport_width' => $viewportWidth,
                'viewport_height' => $viewportHeight
            ]);

            $scrollBucketsUpdated++;
        }
    }

    // ========================================
    // LOGOVÁNÍ (debug)
    // ========================================
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log(sprintf(
            '[Track Heatmap] Page: %s | Device: %s | Clicks: %d | Scroll Buckets: %d',
            $normalizedUrl,
            $deviceType,
            $clicksAggregated,
            $scrollBucketsUpdated
        ));
    }

    // ========================================
    // ODPOVĚĎ
    // ========================================
    sendJsonSuccess('Heatmap data agregována', [
        'clicks_aggregated' => $clicksAggregated,
        'scroll_buckets_updated' => $scrollBucketsUpdated,
        'page_url' => $normalizedUrl,
        'device_type' => $deviceType
    ]);

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    error_log('Track Heatmap API Error: ' . $errorMsg);
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Detekce specifických chyb pro lepší debugging
    if (strpos($errorMsg, "doesn't exist") !== false || strpos($errorMsg, 'Base table or view not found') !== false) {
        // Tabulka neexistuje - spusťte migraci
        error_log('Track Heatmap: TABULKY NEEXISTUJÍ - spusťte /migrace_module6_heatmaps.php?execute=1');
        sendJsonError('Heatmap tabulky neexistují. Spusťte migraci.', 500);
    } else {
        sendJsonError('Chyba při zpracování požadavku', 500);
    }

} catch (Exception $e) {
    error_log('Track Heatmap API Unexpected Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

/**
 * Pomocná funkce pro sanitizaci vstupu
 */
function sanitizeInput($input): ?string
{
    if ($input === null || $input === '') {
        return null;
    }

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
