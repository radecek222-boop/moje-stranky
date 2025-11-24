<?php
/**
 * Analytics API
 * Poskytuje webové analytické metriky
 *
 * POZNÁMKA: Toto je základní implementace. Pro produkci doporučujeme
 * integraci s Google Analytics API nebo vlastní tracking systém.
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// BEZPEČNOST: Kontrola admin přihlášení
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Přístup odepřen. Pouze pro administrátory.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ PERFORMANCE FIX: Uvolnit session lock pro paralelní zpracování
// Audit 2025-11-24: Analytics queries - long-running
session_write_close();

$period = $_GET['period'] ?? 'week';

try {
    $pdo = getDbConnection();

    // Načíst všechna analytics data
    $stats = getBasicStats($pdo, $period);
    $topPages = getTopPages($pdo, $period);
    $referrers = getReferrers($pdo, $period);
    $browsersDevices = getBrowsersDevices($pdo, $period);
    $timeline = getVisitsTimeline($pdo, $period);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'stats' => $stats,
            'topPages' => $topPages,
            'referrers' => $referrers,
            'browsersDevices' => $browsersDevices,
            'timeline' => $timeline
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Analytics API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při načítání analytických dat.'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Získá základní statistiky pro zadané období
 */
function getBasicStats(PDO $pdo, string $period): array
{
    // Určit časové rozmezí podle období
    $dateFrom = match($period) {
        'today' => date('Y-m-d 00:00:00'),
        'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
        'year' => date('Y-m-d 00:00:00', strtotime('-365 days')),
        default => date('Y-m-d 00:00:00', strtotime('-7 days'))
    };

    // Zkontrolovat jestli tabulka pageviews existuje
    $stmtTableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($stmtTableCheck->rowCount() === 0) {
        // Tabulka neexistuje - vrátit nuly
        return [
            'totalVisits' => 0,
            'uniqueVisitors' => 0,
            'avgDuration' => 0,
            'bounceRate' => 0,
            'conversionRate' => 0,
            'totalEvents' => 0
        ];
    }

    // Celkový počet návštěv
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM wgs_pageviews
        WHERE created_at >= :date_from
    ");
    $stmtTotal->execute(['date_from' => $dateFrom]);
    $totalVisits = (int)$stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

    // Unikátní návštěvníci (podle session_id)
    $stmtUnique = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as unique_count
        FROM wgs_pageviews
        WHERE created_at >= :date_from
    ");
    $stmtUnique->execute(['date_from' => $dateFrom]);
    $uniqueVisitors = (int)$stmtUnique->fetch(PDO::FETCH_ASSOC)['unique_count'];

    // Průměrná doba na stránce (v sekundách)
    $stmtDuration = $pdo->prepare("
        SELECT AVG(visit_duration) as avg_duration
        FROM wgs_pageviews
        WHERE created_at >= :date_from AND visit_duration > 0
    ");
    $stmtDuration->execute(['date_from' => $dateFrom]);
    $avgDuration = (int)($stmtDuration->fetch(PDO::FETCH_ASSOC)['avg_duration'] ?? 0);

    // Bounce rate (odskočení bez další interakce)
    $stmtBounce = $pdo->prepare("
        SELECT
            COUNT(*) as total_sessions,
            SUM(CASE WHEN single_page = 1 THEN 1 ELSE 0 END) as bounced_sessions
        FROM (
            SELECT session_id, COUNT(*) as pages, (COUNT(*) = 1) as single_page
            FROM wgs_pageviews
            WHERE created_at >= :date_from
            GROUP BY session_id
        ) as sessions
    ");
    $stmtBounce->execute(['date_from' => $dateFrom]);
    $bounceData = $stmtBounce->fetch(PDO::FETCH_ASSOC);
    $totalSessions = (int)($bounceData['total_sessions'] ?? 0);
    $bouncedSessions = (int)($bounceData['bounced_sessions'] ?? 0);
    $bounceRate = $totalSessions > 0 ? round(($bouncedSessions / $totalSessions) * 100, 1) : 0;

    // Konverze (založeno na reklamacích vytvořených ve stejném období)
    $stmtConversion = $pdo->prepare("
        SELECT COUNT(*) as total_claims
        FROM wgs_reklamace
        WHERE created_at >= :date_from
    ");
    $stmtConversion->execute(['date_from' => $dateFrom]);
    $totalClaims = (int)$stmtConversion->fetch(PDO::FETCH_ASSOC)['total_claims'];

    // Konverzní poměr (claims/visitors), max 100%
    $rawConversionRate = $uniqueVisitors > 0 ? ($totalClaims / $uniqueVisitors) * 100 : 0;
    $conversionRate = min(100, round($rawConversionRate, 1));

    return [
        'totalVisits' => $totalVisits,
        'uniqueVisitors' => $uniqueVisitors,
        'avgDuration' => $avgDuration,
        'bounceRate' => $bounceRate,
        'conversionRate' => $conversionRate,
        'totalEvents' => 0 // Pro budoucí implementaci
    ];
}

/**
 * Získá nejnavštěvovanější stránky
 */
function getTopPages(PDO $pdo, string $period): array
{
    $dateFrom = getDateFrom($period);

    $stmtTableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($stmtTableCheck->rowCount() === 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            page_url,
            page_title,
            COUNT(*) as visits,
            COUNT(DISTINCT session_id) as unique_visitors,
            AVG(visit_duration) as avg_duration
        FROM wgs_pageviews
        WHERE created_at >= :date_from
        GROUP BY page_url, page_title
        ORDER BY visits DESC
        LIMIT 10
    ");
    $stmt->execute(['date_from' => $dateFrom]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Získá zdroje návštěvnosti (referrers)
 */
function getReferrers(PDO $pdo, string $period): array
{
    $dateFrom = getDateFrom($period);

    $stmtTableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($stmtTableCheck->rowCount() === 0) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN referrer = '' OR referrer IS NULL THEN 'Přímý přístup'
                WHEN referrer LIKE '%google%' THEN 'Google'
                WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                WHEN referrer LIKE '%seznam%' THEN 'Seznam'
                WHEN referrer LIKE '%instagram%' THEN 'Instagram'
                ELSE referrer
            END as referrer_source,
            COUNT(*) as visits,
            COUNT(DISTINCT session_id) as unique_visitors
        FROM wgs_pageviews
        WHERE created_at >= :date_from
        GROUP BY referrer_source
        ORDER BY visits DESC
        LIMIT 10
    ");
    $stmt->execute(['date_from' => $dateFrom]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Získá prohlížeče a zařízení
 */
function getBrowsersDevices(PDO $pdo, string $period): array
{
    $dateFrom = getDateFrom($period);

    $stmtTableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($stmtTableCheck->rowCount() === 0) {
        return ['browsers' => [], 'devices' => []];
    }

    // Prohlížeče
    $stmtBrowsers = $pdo->prepare("
        SELECT
            user_agent,
            COUNT(*) as visits
        FROM wgs_pageviews
        WHERE created_at >= :date_from AND user_agent IS NOT NULL
        GROUP BY user_agent
        ORDER BY visits DESC
        LIMIT 5
    ");
    $stmtBrowsers->execute(['date_from' => $dateFrom]);
    $browsers = $stmtBrowsers->fetchAll(PDO::FETCH_ASSOC);

    // Zařízení (podle screen_resolution)
    $stmtDevices = $pdo->prepare("
        SELECT
            screen_resolution,
            COUNT(*) as visits
        FROM wgs_pageviews
        WHERE created_at >= :date_from AND screen_resolution IS NOT NULL
        GROUP BY screen_resolution
        ORDER BY visits DESC
        LIMIT 5
    ");
    $stmtDevices->execute(['date_from' => $dateFrom]);
    $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

    return [
        'browsers' => $browsers,
        'devices' => $devices
    ];
}

/**
 * Získá časovou osu návštěv (pro graf)
 */
function getVisitsTimeline(PDO $pdo, string $period): array
{
    $dateFrom = getDateFrom($period);

    $stmtTableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
    if ($stmtTableCheck->rowCount() === 0) {
        return [];
    }

    // Formát podle období
    $dateFormat = match($period) {
        'today' => '%Y-%m-%d %H:00:00',
        'week' => '%Y-%m-%d',
        'month' => '%Y-%m-%d',
        'year' => '%Y-%m',
        default => '%Y-%m-%d'
    };

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(created_at, :date_format) as time_period,
            COUNT(*) as visits,
            COUNT(DISTINCT session_id) as unique_visitors
        FROM wgs_pageviews
        WHERE created_at >= :date_from
        GROUP BY time_period
        ORDER BY time_period ASC
    ");
    $stmt->execute([
        'date_format' => $dateFormat,
        'date_from' => $dateFrom
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Pomocná funkce pro získání počátečního data podle období
 */
function getDateFrom(string $period): string
{
    return match($period) {
        'today' => date('Y-m-d 00:00:00'),
        'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
        'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
        'year' => date('Y-m-d 00:00:00', strtotime('-365 days')),
        default => date('Y-m-d 00:00:00', strtotime('-7 days'))
    };
}
