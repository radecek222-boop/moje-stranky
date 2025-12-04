<?php
/**
 * Analytics Heatmap API - Získání heatmap dat pro vizualizaci
 *
 * Read API pro admin UI - vrací agregovaná data z heatmap tabulek.
 *
 * Podporuje 2 typy heatmap:
 * - click: Click positions s intensity
 * - scroll: Scroll depth buckets s reach percentages
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze GET metoda
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Pouze GET metoda je povolena', 405);
}

try {
    // ========================================
    // AUTHENTICATION CHECK (admin only)
    // ========================================
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        sendJsonError('Přístup odepřen - pouze pro admins', 403);
    }

    // ========================================
    // CSRF VALIDACE
    // ========================================
    $csrfToken = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    $pdo = getDbConnection();

    // ========================================
    // VALIDACE PARAMETRŮ
    // ========================================
    if (empty($_GET['page_url'])) {
        sendJsonError('Chybí povinný parametr: page_url', 400);
    }

    if (empty($_GET['type'])) {
        sendJsonError('Chybí povinný parametr: type (click nebo scroll)', 400);
    }

    $pageUrl = filter_var($_GET['page_url'], FILTER_VALIDATE_URL);
    if (!$pageUrl) {
        sendJsonError('Neplatná URL adresa', 400);
    }

    // Normalizace URL (stejně jako v track_heatmap.php)
    $parsedUrl = parse_url($pageUrl);
    $normalizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '/');

    $type = sanitizeInput($_GET['type']);
    if (!in_array($type, ['click', 'scroll'])) {
        sendJsonError('Neplatný type (povolené: click, scroll)', 400);
    }

    $deviceType = sanitizeInput($_GET['device_type'] ?? null);
    if ($deviceType && !in_array($deviceType, ['desktop', 'mobile', 'tablet'])) {
        sendJsonError('Neplatný device_type (povolené: desktop, mobile, tablet)', 400);
    }

    $minIntensity = isset($_GET['min_intensity']) ? (int)$_GET['min_intensity'] : 1;

    // ========================================
    // FETCH CLICK HEATMAP DATA
    // ========================================
    if ($type === 'click') {
        $sql = "
            SELECT
                click_x_percent AS x,
                click_y_percent AS y,
                click_count AS count,
                viewport_width_avg,
                viewport_height_avg,
                country_code,
                city,
                latitude,
                longitude
            FROM wgs_analytics_heatmap_clicks
            WHERE page_url = :page_url
              AND click_count >= :min_intensity
        ";

        $params = [
            'page_url' => $normalizedUrl,
            'min_intensity' => $minIntensity
        ];

        if ($deviceType) {
            $sql .= " AND device_type = :device_type";
            $params['device_type'] = $deviceType;
        }

        $sql .= " ORDER BY click_count DESC LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vypočítat max intensity pro normalizaci
        $maxIntensity = 0;
        $totalClicks = 0;
        foreach ($points as $point) {
            $count = (int)$point['count'];
            if ($count > $maxIntensity) {
                $maxIntensity = $count;
            }
            $totalClicks += $count;
        }

        // Přidat normalized intensity (0-100) a geolokaci
        foreach ($points as &$point) {
            $point['x'] = (float)$point['x'];
            $point['y'] = (float)$point['y'];
            $point['count'] = (int)$point['count'];
            $point['intensity'] = $maxIntensity > 0 ? round(($point['count'] / $maxIntensity) * 100) : 0;
            $point['viewport_width_avg'] = $point['viewport_width_avg'] ? (int)$point['viewport_width_avg'] : null;
            $point['viewport_height_avg'] = $point['viewport_height_avg'] ? (int)$point['viewport_height_avg'] : null;
            $point['country_code'] = $point['country_code'] ?? null;
            $point['city'] = $point['city'] ?? null;
            $point['latitude'] = $point['latitude'] ? (float)$point['latitude'] : null;
            $point['longitude'] = $point['longitude'] ? (float)$point['longitude'] : null;
        }

        // Agregovat statistiky geolokace
        $geoStats = [];
        $stmtGeo = $pdo->prepare("
            SELECT country_code, city, SUM(click_count) as total_clicks
            FROM wgs_analytics_heatmap_clicks
            WHERE page_url = :page_url AND country_code IS NOT NULL
            GROUP BY country_code, city
            ORDER BY total_clicks DESC
            LIMIT 10
        ");
        $stmtGeo->execute(['page_url' => $normalizedUrl]);
        $geoStats = $stmtGeo->fetchAll(PDO::FETCH_ASSOC);

        sendJsonSuccess('Click heatmap data načtena', [
            'type' => 'click',
            'page_url' => $normalizedUrl,
            'device_type' => $deviceType ?? 'all',
            'total_clicks' => $totalClicks,
            'max_intensity' => $maxIntensity,
            'points_count' => count($points),
            'points' => $points,
            'geo_stats' => $geoStats
        ]);
    }

    // ========================================
    // FETCH SCROLL HEATMAP DATA
    // ========================================
    elseif ($type === 'scroll') {
        $sql = "
            SELECT
                scroll_depth_bucket AS depth,
                reach_count,
                viewport_width_avg,
                viewport_height_avg,
                country_code,
                city
            FROM wgs_analytics_heatmap_scroll
            WHERE page_url = :page_url
        ";

        $params = [
            'page_url' => $normalizedUrl
        ];

        if ($deviceType) {
            $sql .= " AND device_type = :device_type";
            $params['device_type'] = $deviceType;
        }

        $sql .= " ORDER BY scroll_depth_bucket ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vytvoř buckets array (0, 10, 20, ..., 100)
        $buckets = [];
        $maxReachCount = 0;

        foreach ($rows as $row) {
            $reachCount = (int)$row['reach_count'];
            if ($reachCount > $maxReachCount) {
                $maxReachCount = $reachCount;
            }

            $buckets[] = [
                'depth' => (int)$row['depth'],
                'reach_count' => $reachCount,
                'percentage' => 0, // Vypočteme níže
                'viewport_width_avg' => $row['viewport_width_avg'] ? (int)$row['viewport_width_avg'] : null,
                'viewport_height_avg' => $row['viewport_height_avg'] ? (int)$row['viewport_height_avg'] : null,
                'country_code' => $row['country_code'] ?? null,
                'city' => $row['city'] ?? null
            ];
        }

        // Vypočítat percentage (relative to max)
        foreach ($buckets as &$bucket) {
            $bucket['percentage'] = $maxReachCount > 0 ? round(($bucket['reach_count'] / $maxReachCount) * 100) : 0;
        }

        // Total views = reach_count at depth 0
        $totalViews = 0;
        foreach ($buckets as $bucket) {
            if ($bucket['depth'] === 0) {
                $totalViews = $bucket['reach_count'];
                break;
            }
        }

        // Agregovat statistiky geolokace pro scroll
        $geoStats = [];
        $stmtGeo = $pdo->prepare("
            SELECT country_code, city, SUM(reach_count) as total_views
            FROM wgs_analytics_heatmap_scroll
            WHERE page_url = :page_url AND country_code IS NOT NULL
            GROUP BY country_code, city
            ORDER BY total_views DESC
            LIMIT 10
        ");
        $stmtGeo->execute(['page_url' => $normalizedUrl]);
        $geoStats = $stmtGeo->fetchAll(PDO::FETCH_ASSOC);

        sendJsonSuccess('Scroll heatmap data načtena', [
            'type' => 'scroll',
            'page_url' => $normalizedUrl,
            'device_type' => $deviceType ?? 'all',
            'total_views' => $totalViews,
            'buckets_count' => count($buckets),
            'buckets' => $buckets,
            'geo_stats' => $geoStats
        ]);
    }

} catch (PDOException $e) {
    error_log('Analytics Heatmap API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při načítání dat', 500);

} catch (Exception $e) {
    error_log('Analytics Heatmap API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

/**
 * Pomocná funkce pro sanitizaci vstupu
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
?>
