<?php
/**
 * Analytics User Scores API
 *
 * Read API pro získání user engagement/frustration/interest scores.
 *
 * Actions:
 * - list: Seznam sessions se scores
 * - detail: Detail scores pro session
 * - distribution: Distribuce scores (histogram)
 * - stats: Agregované statistiky
 * - recalculate: Přepočítat scores pro session
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #10 - User Interest AI Scoring
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/UserScoreCalculator.php';

header('Content-Type: application/json; charset=utf-8');

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
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    $pdo = getDbConnection();
    $scoreCalculator = new UserScoreCalculator($pdo);

    // ========================================
    // PARAMETRY
    // ========================================
    $action = $_GET['action'] ?? 'list';
    $sessionId = $_GET['session_id'] ?? $_POST['session_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $scoreType = $_GET['score_type'] ?? null; // engagement, frustration, interest
    $minScore = isset($_GET['min_score']) ? (float) $_GET['min_score'] : null;
    $maxScore = isset($_GET['max_score']) ? (float) $_GET['max_score'] : null;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // ========================================
    // ACTION ROUTING
    // ========================================
    switch ($action) {
        // ========================================
        // LIST - Seznam sessions se scores
        // ========================================
        case 'list':
            $sql = "
            SELECT
                us.*,
                s.session_start,
                s.session_end,
                s.device_type,
                s.country,
                s.city
            FROM wgs_analytics_user_scores us
            LEFT JOIN wgs_analytics_sessions s ON us.session_id = s.session_id
            WHERE 1=1
            ";

            $params = [];

            // Date range filter
            if ($dateFrom) {
                $sql .= " AND DATE(us.created_at) >= :date_from";
                $params['date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $sql .= " AND DATE(us.created_at) <= :date_to";
                $params['date_to'] = $dateTo;
            }

            // Score type filter
            if ($scoreType && in_array($scoreType, ['engagement', 'frustration', 'interest'])) {
                if ($minScore !== null) {
                    $sql .= " AND us.{$scoreType}_score >= :min_score";
                    $params['min_score'] = $minScore;
                }
                if ($maxScore !== null) {
                    $sql .= " AND us.{$scoreType}_score <= :max_score";
                    $params['max_score'] = $maxScore;
                }
            }

            $sql .= " ORDER BY us.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);

            // Bind všechny parametry kromě LIMIT/OFFSET
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            // LIMIT a OFFSET musí být bindnuty jako INT
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Dekódovat JSON sloupce
            foreach ($scores as &$score) {
                $score['engagement_factors'] = json_decode($score['engagement_factors'], true);
                $score['frustration_factors'] = json_decode($score['frustration_factors'], true);
                $score['interest_factors'] = json_decode($score['interest_factors'], true);
            }

            // Total count - OPRAVA: použít prepared statement místo SQL injection
            $countSql = "SELECT COUNT(*) as total FROM wgs_analytics_user_scores WHERE 1=1";
            $countParams = [];

            if ($dateFrom) {
                $countSql .= " AND DATE(created_at) >= :date_from";
                $countParams['date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $countSql .= " AND DATE(created_at) <= :date_to";
                $countParams['date_to'] = $dateTo;
            }

            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            sendJsonSuccess('Scores načteny', [
                'scores' => $scores,
                'total' => (int) $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        // ========================================
        // DETAIL - Detail scores pro session
        // ========================================
        case 'detail':
            if (!$sessionId) {
                sendJsonError('Chybí session_id');
            }

            $scores = $scoreCalculator->nactiScores($sessionId);

            if (!$scores) {
                sendJsonError('Scores pro tuto session nenalezeny', 404);
            }

            sendJsonSuccess('Score detail načten', [
                'scores' => $scores
            ]);
            break;

        // ========================================
        // DISTRIBUTION - Distribuce scores (histogram)
        // ========================================
        case 'distribution':
            $scoreTypeParam = $_GET['score_type'] ?? 'engagement';

            if (!in_array($scoreTypeParam, ['engagement', 'frustration', 'interest'])) {
                sendJsonError('Neplatný score_type (engagement, frustration, interest)');
            }

            $column = "{$scoreTypeParam}_score";

            // Histogram buckets (0-10, 10-20, 20-30, ..., 90-100)
            $buckets = [];
            for ($i = 0; $i < 100; $i += 10) {
                $buckets[] = [
                    'min' => $i,
                    'max' => $i + 10,
                    'count' => 0
                ];
            }

            // Zjistit count pro každý bucket
            foreach ($buckets as &$bucket) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt
                    FROM wgs_analytics_user_scores
                    WHERE {$column} >= :min AND {$column} < :max
                    AND DATE(created_at) >= :date_from
                    AND DATE(created_at) <= :date_to
                ");
                $stmt->execute([
                    'min' => $bucket['min'],
                    'max' => $bucket['max'],
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ]);
                $bucket['count'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            }

            sendJsonSuccess('Distribuce načtena', [
                'score_type' => $scoreTypeParam,
                'distribution' => $buckets
            ]);
            break;

        // ========================================
        // STATS - Agregované statistiky
        // ========================================
        case 'stats':
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total_sessions,
                    AVG(engagement_score) as avg_engagement,
                    AVG(frustration_score) as avg_frustration,
                    AVG(interest_score) as avg_interest,
                    MAX(engagement_score) as max_engagement,
                    MAX(frustration_score) as max_frustration,
                    MAX(interest_score) as max_interest,
                    MIN(engagement_score) as min_engagement,
                    MIN(frustration_score) as min_frustration,
                    MIN(interest_score) as min_interest
                FROM wgs_analytics_user_scores
                WHERE DATE(created_at) >= :date_from
                AND DATE(created_at) <= :date_to
            ");
            $stmt->execute([
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Format numbers
            $stats['total_sessions'] = (int) $stats['total_sessions'];
            $stats['avg_engagement'] = round((float) $stats['avg_engagement'], 2);
            $stats['avg_frustration'] = round((float) $stats['avg_frustration'], 2);
            $stats['avg_interest'] = round((float) $stats['avg_interest'], 2);
            $stats['max_engagement'] = round((float) $stats['max_engagement'], 2);
            $stats['max_frustration'] = round((float) $stats['max_frustration'], 2);
            $stats['max_interest'] = round((float) $stats['max_interest'], 2);
            $stats['min_engagement'] = round((float) $stats['min_engagement'], 2);
            $stats['min_frustration'] = round((float) $stats['min_frustration'], 2);
            $stats['min_interest'] = round((float) $stats['min_interest'], 2);

            sendJsonSuccess('Statistiky načteny', [
                'stats' => $stats
            ]);
            break;

        // ========================================
        // RECALCULATE - Přepočítat scores pro session
        // ========================================
        case 'recalculate':
            if (!$sessionId) {
                sendJsonError('Chybí session_id');
            }

            $success = $scoreCalculator->aktualizujScores($sessionId);

            if ($success) {
                $scores = $scoreCalculator->nactiScores($sessionId);
                sendJsonSuccess('Scores přepočítány', [
                    'session_id' => $sessionId,
                    'scores' => $scores
                ]);
            } else {
                sendJsonError('Chyba při přepočítání scores');
            }
            break;

        // ========================================
        // DEFAULT - Neplatná akce
        // ========================================
        default:
            sendJsonError('Neplatná akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("API User Scores - Database error: " . $e->getMessage());
    sendJsonError('Chyba databáze při zpracování požadavku');
} catch (Exception $e) {
    error_log("API User Scores - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}
?>
