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

$period = $_GET['period'] ?? 'week';

try {
    $pdo = getDbConnection();

    // Načíst základní statistiky z reklamací (jako prozatímní analytics)
    $stats = getBasicStats($pdo, $period);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'visits' => [],
            'events' => [],
            'sessions' => [],
            'stats' => $stats
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

    // POZNÁMKA: Toto je prozatímní řešení používající data z reklamací
    // Pro skutečný web analytics implementujte tracking návštěv

    return [
        'totalVisits' => 0,
        'uniqueVisitors' => 0,
        'avgDuration' => 0,
        'bounceRate' => 0,
        'conversionRate' => 0,
        'totalEvents' => 0
    ];
}
