<?php
/**
 * Analytics Bot Activity API
 *
 * Endpoint pro načítání bot detekční aktivity (admin dashboard)
 *
 * Metoda: GET
 * Autentizace: Vyžaduje přihlášení (admin nebo user s právem analytics)
 * Rate limiting: 100 požadavků/hodinu
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #3 - Bot Detection Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/BotDetector.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers (pokud potřeba)
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze GET metoda
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Pouze GET metoda je povolena', 405);
}

// Autentizace - pouze přihlášení uživatelé
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Extrakce dat ze session před uvolněním zámku
$userId = $_SESSION['user_id'] ?? 'admin';

try {
    $pdo = getDbConnection();

    // Rate limiting - 100 požadavků za hodinu per user
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($userId, 'bot_activity_api', [
        'max_attempts' => 100,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    // ========================================
    // PARSOVÁNÍ PARAMETRŮ
    // ========================================
    $action = $_GET['action'] ?? 'statistiky';

    switch ($action) {
        // ------------------------------------------------------------
        // ACTION: statistiky
        // Vrací agregované statistiky bot aktivity
        // ------------------------------------------------------------
        case 'statistiky':
            // Validace období
            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
            $to = $_GET['to'] ?? date('Y-m-d');

            // Validace formátu data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                sendJsonError('Neplatný formát data (očekáváno YYYY-MM-DD)', 400);
            }

            // Filtry (volitelné)
            $filters = [];

            if (isset($_GET['threat_level'])) {
                $povolenoThreatLevel = ['none', 'low', 'medium', 'high', 'critical'];
                $threatLevel = $_GET['threat_level'];

                if (in_array($threatLevel, $povolenoThreatLevel)) {
                    $filters['threat_level'] = $threatLevel;
                } else {
                    sendJsonError('Neplatný threat_level (povoleno: none, low, medium, high, critical)', 400);
                }
            }

            if (isset($_GET['is_bot'])) {
                $filters['is_bot'] = ($_GET['is_bot'] === 'true' || $_GET['is_bot'] === '1');
            }

            // Načíst statistiky
            $botDetector = new BotDetector($pdo);
            $statistiky = $botDetector->nactiStatistiky($from, $to, $filters);

            // Celkové sumarizace
            $celkovaStatistika = [
                'celkem_detekci' => 0,
                'celkem_botu' => 0,
                'celkem_whitelisted' => 0,
                'prumerne_bot_score' => 0
            ];

            $celkemBotScore = 0;
            $pocetRadku = count($statistiky);

            foreach ($statistiky as $row) {
                $celkovaStatistika['celkem_detekci'] += (int)$row['pocet_detekci'];
                $celkovaStatistika['celkem_botu'] += (int)$row['pocet_botu'];
                $celkovaStatistika['celkem_whitelisted'] += (int)$row['pocet_whitelisted'];
                $celkemBotScore += (float)$row['prumerne_bot_score'];
            }

            if ($pocetRadku > 0) {
                $celkovaStatistika['prumerne_bot_score'] = round($celkemBotScore / $pocetRadku, 2);
            }

            sendJsonSuccess('Statistiky načteny', [
                'obdobi' => [
                    'from' => $from,
                    'to' => $to
                ],
                'filtry' => $filters,
                'celkova_statistika' => $celkovaStatistika,
                'denni_statistiky' => $statistiky
            ]);
            break;

        // ------------------------------------------------------------
        // ACTION: detekce_relace
        // Vrací detekční záznamy pro konkrétní relaci
        // ------------------------------------------------------------
        case 'detekce_relace':
            $sessionId = $_GET['session_id'] ?? '';

            if (empty($sessionId)) {
                sendJsonError('Chybí parametr session_id', 400);
            }

            $botDetector = new BotDetector($pdo);
            $detekce = $botDetector->nactiDetekceRelace($sessionId);

            sendJsonSuccess('Detekční záznamy načteny', [
                'session_id' => $sessionId,
                'pocet_detekci' => count($detekce),
                'detekce' => $detekce
            ]);
            break;

        // ------------------------------------------------------------
        // ACTION: top_boti
        // Vrací top N botů (podle počtu detekčních záznamů)
        // ------------------------------------------------------------
        case 'top_boti':
            $limit = (int)($_GET['limit'] ?? 10);
            $limit = min($limit, 100); // Max 100

            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
            $to = $_GET['to'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT
                    fingerprint_id,
                    user_agent,
                    COUNT(*) AS pocet_detekci,
                    AVG(bot_score) AS prumerne_bot_score,
                    MAX(threat_level) AS max_threat_level,
                    SUM(CASE WHEN is_whitelisted = 1 THEN 1 ELSE 0 END) AS pocet_whitelisted
                FROM wgs_analytics_bot_detections
                WHERE detection_timestamp >= :from
                  AND detection_timestamp <= :to
                  AND is_bot = 1
                GROUP BY fingerprint_id, user_agent
                ORDER BY pocet_detekci DESC
                LIMIT :limit
            ");

            $stmt->bindValue(':from', $from . ' 00:00:00', PDO::PARAM_STR);
            $stmt->bindValue(':to', $to . ' 23:59:59', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $topBoti = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Top boti načteni', [
                'obdobi' => [
                    'from' => $from,
                    'to' => $to
                ],
                'limit' => $limit,
                'top_boti' => $topBoti
            ]);
            break;

        // ------------------------------------------------------------
        // ACTION: threat_level_distribuce
        // Vrací distribuci threat levelů (pro pie chart)
        // ------------------------------------------------------------
        case 'threat_level_distribuce':
            $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
            $to = $_GET['to'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT
                    threat_level,
                    COUNT(*) AS pocet
                FROM wgs_analytics_bot_detections
                WHERE detection_timestamp >= :from
                  AND detection_timestamp <= :to
                GROUP BY threat_level
                ORDER BY FIELD(threat_level, 'critical', 'high', 'medium', 'low', 'none')
            ");

            $stmt->execute([
                'from' => $from . ' 00:00:00',
                'to' => $to . ' 23:59:59'
            ]);

            $distribuce = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Distribuce threat levelů načtena', [
                'obdobi' => [
                    'from' => $from,
                    'to' => $to
                ],
                'distribuce' => $distribuce
            ]);
            break;

        // ------------------------------------------------------------
        // Neplatná akce
        // ------------------------------------------------------------
        default:
            sendJsonError('Neplatná akce (povoleno: statistiky, detekce_relace, top_boti, threat_level_distribuce)', 400);
    }

} catch (PDOException $e) {
    // Logování chyby bez expozice detailů
    error_log('Analytics Bot Activity API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při zpracování požadavku', 500);

} catch (Exception $e) {
    // Obecná chyba
    error_log('Analytics Bot Activity API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}
?>
