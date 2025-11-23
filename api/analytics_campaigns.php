<?php
/**
 * Analytics Campaigns API - Načtení statistik UTM kampaní
 *
 * Read API pro admin UI - vrací agregované campaign metriky.
 *
 * Query params:
 * - date_from (optional): Počáteční datum (Y-m-d)
 * - date_to (optional): Koncové datum (Y-m-d)
 * - utm_source (optional): Filtr podle zdroje
 * - utm_medium (optional): Filtr podle média
 * - utm_campaign (optional): Filtr podle kampaně
 * - device_type (optional): Filtr podle zařízení (desktop/mobile/tablet)
 * - group_by (optional): Seskupení (source, medium, campaign, content, term, device) - default: campaign
 * - order_by (optional): Řazení (sessions, conversions, conversion_rate, revenue) - default: sessions
 * - limit (optional): Limit výsledků (default: 50, max: 1000)
 * - csrf_token (required): CSRF token
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #8 - UTM Campaign Tracking
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

    $pdo = getDbConnection();

    // ========================================
    // PARAMETRY
    // ========================================
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $utmSource = $_GET['utm_source'] ?? null;
    $utmMedium = $_GET['utm_medium'] ?? null;
    $utmCampaign = $_GET['utm_campaign'] ?? null;
    $deviceType = $_GET['device_type'] ?? null;
    $groupBy = $_GET['group_by'] ?? 'campaign';
    $orderBy = $_GET['order_by'] ?? 'sessions';
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 50;

    // Validace date range
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        sendJsonError('Neplatný formát data (požadováno: Y-m-d)', 400);
    }

    // ========================================
    // BUILD SQL QUERY
    // ========================================

    // GROUP BY mapping
    $groupByMapping = [
        'source' => 'utm_source',
        'medium' => 'utm_medium',
        'campaign' => 'utm_campaign',
        'content' => 'utm_content',
        'term' => 'utm_term',
        'device' => 'device_type',
        'full' => 'utm_source, utm_medium, utm_campaign, utm_content, utm_term, device_type'
    ];

    $groupByClause = $groupByMapping[$groupBy] ?? 'utm_campaign';

    // ORDER BY mapping
    $orderByMapping = [
        'sessions' => 'total_sessions DESC',
        'conversions' => 'total_conversions DESC',
        'conversion_rate' => 'avg_conversion_rate DESC',
        'revenue' => 'total_revenue DESC',
        'bounce_rate' => 'avg_bounce_rate ASC'
    ];

    $orderByClause = $orderByMapping[$orderBy] ?? 'total_sessions DESC';

    // WHERE clauses
    $whereClauses = [
        'date >= :date_from',
        'date <= :date_to'
    ];

    $params = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo
    ];

    if ($utmSource) {
        $whereClauses[] = 'utm_source = :utm_source';
        $params['utm_source'] = $utmSource;
    }

    if ($utmMedium) {
        $whereClauses[] = 'utm_medium = :utm_medium';
        $params['utm_medium'] = $utmMedium;
    }

    if ($utmCampaign) {
        $whereClauses[] = 'utm_campaign = :utm_campaign';
        $params['utm_campaign'] = $utmCampaign;
    }

    if ($deviceType && in_array($deviceType, ['desktop', 'mobile', 'tablet'])) {
        $whereClauses[] = 'device_type = :device_type';
        $params['device_type'] = $deviceType;
    }

    $whereClause = implode(' AND ', $whereClauses);

    // ========================================
    // QUERY: CAMPAIGN DATA
    // ========================================

    $stmt = $pdo->prepare("
        SELECT
            utm_source,
            utm_medium,
            utm_campaign,
            utm_content,
            utm_term,
            device_type,

            -- Traffic metriky
            SUM(sessions_count) as total_sessions,
            SUM(pageviews_count) as total_pageviews,
            SUM(unique_visitors) as total_unique_visitors,

            -- Engagement metriky
            AVG(avg_session_duration) as avg_duration,
            AVG(avg_pages_per_session) as avg_pages_per_session,
            AVG(bounce_rate) as avg_bounce_rate,

            -- Conversion metriky
            SUM(conversions_count) as total_conversions,
            SUM(conversion_value) as total_revenue,
            AVG(conversion_rate) as avg_conversion_rate,

            -- Attribution
            SUM(first_click_conversions) as total_first_click_conversions,
            SUM(last_click_conversions) as total_last_click_conversions,
            SUM(linear_attribution_value) as total_linear_attribution_value

        FROM wgs_analytics_utm_campaigns
        WHERE {$whereClause}
        GROUP BY {$groupByClause}
        ORDER BY {$orderByClause}
        LIMIT :limit
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formátování výsledků
    foreach ($campaigns as &$campaign) {
        $campaign['total_sessions'] = (int)$campaign['total_sessions'];
        $campaign['total_pageviews'] = (int)$campaign['total_pageviews'];
        $campaign['total_unique_visitors'] = (int)$campaign['total_unique_visitors'];
        $campaign['avg_duration'] = round($campaign['avg_duration'], 2);
        $campaign['avg_pages_per_session'] = round($campaign['avg_pages_per_session'], 2);
        $campaign['avg_bounce_rate'] = round($campaign['avg_bounce_rate'], 2);
        $campaign['total_conversions'] = (int)$campaign['total_conversions'];
        $campaign['total_revenue'] = round($campaign['total_revenue'], 2);
        $campaign['avg_conversion_rate'] = round($campaign['avg_conversion_rate'], 2);
        $campaign['total_first_click_conversions'] = (int)$campaign['total_first_click_conversions'];
        $campaign['total_last_click_conversions'] = (int)$campaign['total_last_click_conversions'];
        $campaign['total_linear_attribution_value'] = round($campaign['total_linear_attribution_value'], 2);
    }

    // ========================================
    // QUERY: TOTALS
    // ========================================

    $stmt = $pdo->prepare("
        SELECT
            SUM(sessions_count) as total_sessions,
            SUM(pageviews_count) as total_pageviews,
            SUM(unique_visitors) as total_unique_visitors,
            SUM(conversions_count) as total_conversions,
            SUM(conversion_value) as total_revenue,
            AVG(conversion_rate) as avg_conversion_rate,
            AVG(bounce_rate) as avg_bounce_rate
        FROM wgs_analytics_utm_campaigns
        WHERE {$whereClause}
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $totals['total_sessions'] = (int)$totals['total_sessions'];
    $totals['total_pageviews'] = (int)$totals['total_pageviews'];
    $totals['total_unique_visitors'] = (int)$totals['total_unique_visitors'];
    $totals['total_conversions'] = (int)$totals['total_conversions'];
    $totals['total_revenue'] = round($totals['total_revenue'], 2);
    $totals['avg_conversion_rate'] = round($totals['avg_conversion_rate'], 2);
    $totals['avg_bounce_rate'] = round($totals['avg_bounce_rate'], 2);

    // ========================================
    // QUERY: TIMELINE DATA (denní agregace)
    // ========================================

    $stmt = $pdo->prepare("
        SELECT
            date,
            SUM(sessions_count) as sessions,
            SUM(conversions_count) as conversions,
            SUM(conversion_value) as revenue
        FROM wgs_analytics_utm_campaigns
        WHERE {$whereClause}
        GROUP BY date
        ORDER BY date ASC
    ");

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($timeline as &$point) {
        $point['sessions'] = (int)$point['sessions'];
        $point['conversions'] = (int)$point['conversions'];
        $point['revenue'] = round($point['revenue'], 2);
    }

    // ========================================
    // RESPONSE
    // ========================================

    sendJsonSuccess('Campaign data načtena', [
        'campaigns' => $campaigns,
        'totals' => $totals,
        'timeline' => $timeline,
        'filters' => [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'device_type' => $deviceType,
            'group_by' => $groupBy,
            'order_by' => $orderBy
        ],
        'count' => count($campaigns)
    ]);

} catch (PDOException $e) {
    error_log('Analytics Campaigns API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při načítání campaign dat', 500);

} catch (Exception $e) {
    error_log('Analytics Campaigns API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}
?>
