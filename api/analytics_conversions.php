<?php
/**
 * Analytics Conversions API - Načtení conversion dat a funnel analýzy
 *
 * Read API pro admin UI - vrací conversion statistiky a funnel analýzy.
 *
 * Query params:
 * - date_from (optional): Počáteční datum (Y-m-d)
 * - date_to (optional): Koncové datum (Y-m-d)
 * - conversion_type (optional): Filtr podle typu
 * - utm_campaign (optional): Filtr podle kampaně
 * - device_type (optional): Filtr podle zařízení
 * - funnel_id (optional): ID funnelu pro analýzu
 * - action (optional): 'list' (default), 'stats', 'funnel_analysis', 'list_funnels'
 * - csrf_token (required): CSRF token
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #9 - Conversion Funnels
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/ConversionFunnel.php';

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
    $conversionFunnel = new ConversionFunnel($pdo);

    // ========================================
    // PARAMETRY
    // ========================================
    $action = $_GET['action'] ?? 'list';
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $conversionType = $_GET['conversion_type'] ?? null;
    $utmCampaign = $_GET['utm_campaign'] ?? null;
    $deviceType = $_GET['device_type'] ?? null;
    $funnelId = isset($_GET['funnel_id']) ? (int)$_GET['funnel_id'] : null;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 100;

    // Validace date range
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        sendJsonError('Neplatný formát data (požadováno: Y-m-d)', 400);
    }

    // ========================================
    // DISPATCH ACTION
    // ========================================

    switch ($action) {
        case 'stats':
            // Conversion statistiky
            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'conversion_type' => $conversionType
            ];

            $stats = $conversionFunnel->nactiConversionStatistiky($filters);

            sendJsonSuccess('Statistiky načteny', [
                'stats' => $stats,
                'filters' => $filters
            ]);
            break;

        case 'funnel_analysis':
            // Funnel analýza
            if (!$funnelId) {
                sendJsonError('Chybí parametr funnel_id', 400);
            }

            $analysis = $conversionFunnel->analyzFunnel($funnelId, $dateFrom, $dateTo);

            sendJsonSuccess('Funnel analýza načtena', [
                'funnel_analysis' => $analysis
            ]);
            break;

        case 'list_funnels':
            // Seznam všech funnelů
            $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === '1';
            $funnels = $conversionFunnel->nactiFunnely($activeOnly);

            sendJsonSuccess('Funnely načteny', [
                'funnels' => $funnels,
                'count' => count($funnels)
            ]);
            break;

        case 'list':
        default:
            // Seznam konverzí
            $filters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'conversion_type' => $conversionType,
                'utm_campaign' => $utmCampaign,
                'device_type' => $deviceType,
                'limit' => $limit
            ];

            $conversions = $conversionFunnel->nactiKonverze($filters);

            // Dekódovat JSON sloupce pro frontend
            foreach ($conversions as &$conv) {
                $conv['conversion_path'] = json_decode($conv['conversion_path'], true);
                $conv['metadata'] = json_decode($conv['metadata'], true);
            }

            // Agregované stats
            $statsFilters = [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'conversion_type' => $conversionType
            ];
            $stats = $conversionFunnel->nactiConversionStatistiky($statsFilters);

            sendJsonSuccess('Konverze načteny', [
                'conversions' => $conversions,
                'stats' => $stats,
                'filters' => $filters,
                'count' => count($conversions)
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Analytics Conversions API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError($e->getMessage(), 500);
}
?>
