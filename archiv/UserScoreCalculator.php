<?php
/**
 * User Score Calculator
 *
 * Třída pro výpočet AI-based engagement, frustration a interest skóre
 * pro každou session na základě user behaviorálních metrik.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #10 - User Interest AI Scoring
 */

class UserScoreCalculator
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================
    // MAIN PUBLIC METHODS
    // ========================================

    /**
     * Vypočítá engagement score pro session (0-100)
     *
     * Faktory:
     * - Click count (20%)
     * - Scroll depth average (20%)
     * - Session duration (20%)
     * - Mouse movement entropy (15%)
     * - Pageviews count (15%)
     * - Interaction diversity (10%)
     *
     * @param string $sessionId
     * @return array ['score' => float, 'factors' => array]
     */
    public function vypocitejEngagementScore(string $sessionId): array
    {
        $sessionData = $this->nactiSessionData($sessionId);
        $eventsData = $this->nactiEventsData($sessionId);

        if (!$sessionData) {
            return ['score' => 0.00, 'factors' => []];
        }

        // Faktory
        $totalClicks = $eventsData['click_count'] ?? 0;
        $avgScrollDepth = $sessionData['avg_scroll_depth'] ?? 0;
        $sessionDuration = $sessionData['session_duration'] ?? 0; // sekundy
        $mouseEntropy = $eventsData['mouse_entropy'] ?? 0; // 0-1
        $pageviews = $sessionData['pageview_count'] ?? 0;
        $uniqueEvents = $eventsData['unique_event_types'] ?? 0;

        // Výpočet sub-scores (0-100)
        $clickScore = min(($totalClicks / 20) * 100, 100); // Max 20 kliků = 100
        $scrollScore = min($avgScrollDepth, 100); // 0-100 (%)
        $durationScore = min(($sessionDuration / 300) * 100, 100); // Max 5min = 100
        $mouseScore = $mouseEntropy * 100; // 0-1 → 0-100
        $pageviewScore = min(($pageviews / 10) * 100, 100); // Max 10 stránek = 100
        $diversityScore = min(($uniqueEvents / 5) * 100, 100); // Max 5 typů = 100

        // Vážený průměr
        $engagementScore = (
            $clickScore * 0.20 +
            $scrollScore * 0.20 +
            $durationScore * 0.20 +
            $mouseScore * 0.15 +
            $pageviewScore * 0.15 +
            $diversityScore * 0.10
        );

        $factors = [
            'total_clicks' => $totalClicks,
            'click_score' => round($clickScore, 2),
            'avg_scroll_depth' => round($avgScrollDepth, 2),
            'scroll_score' => round($scrollScore, 2),
            'session_duration' => $sessionDuration,
            'duration_score' => round($durationScore, 2),
            'mouse_entropy' => round($mouseEntropy, 2),
            'mouse_score' => round($mouseScore, 2),
            'pageviews' => $pageviews,
            'pageview_score' => round($pageviewScore, 2),
            'unique_event_types' => $uniqueEvents,
            'diversity_score' => round($diversityScore, 2)
        ];

        return [
            'score' => round($engagementScore, 2),
            'factors' => $factors
        ];
    }

    /**
     * Vypočítá frustration score pro session (0-100)
     *
     * Faktory:
     * - Rage clicks (30%)
     * - Erratic scrolling (25%)
     * - Hesitation time (20%)
     * - Quick exits (15%)
     * - Error clicks (10%)
     *
     * @param string $sessionId
     * @return array ['score' => float, 'factors' => array]
     */
    public function vypocitejFrustrationScore(string $sessionId): array
    {
        $sessionData = $this->nactiSessionData($sessionId);
        $eventsData = $this->nactiEventsData($sessionId);

        if (!$sessionData) {
            return ['score' => 0.00, 'factors' => []];
        }

        // Faktory
        $rageClicks = $eventsData['rage_click_count'] ?? 0;
        $scrollVariance = $eventsData['scroll_variance'] ?? 0; // 0-1000+
        $hesitationTime = $eventsData['hesitation_time'] ?? 0; // sekundy bez interakce
        $sessionDuration = $sessionData['session_duration'] ?? 0;
        $hasQuickExit = ($sessionDuration > 0 && $sessionDuration < 10); // < 10s
        $errorClicks = $eventsData['error_click_count'] ?? 0;

        // Výpočet sub-scores (0-100)
        $rageClickScore = min(($rageClicks / 5) * 100, 100); // Max 5 rage = 100
        $erraticScrollScore = min(($scrollVariance / 1000) * 100, 100); // High variance = frustrace
        $hesitationScore = min(($hesitationTime / 30) * 100, 100); // 30s+ hesitation = 100
        $quickExitScore = $hasQuickExit ? 100 : 0;
        $errorClickScore = min(($errorClicks / 10) * 100, 100); // Max 10 error clicks = 100

        // Vážený průměr
        $frustrationScore = (
            $rageClickScore * 0.30 +
            $erraticScrollScore * 0.25 +
            $hesitationScore * 0.20 +
            $quickExitScore * 0.15 +
            $errorClickScore * 0.10
        );

        $factors = [
            'rage_clicks' => $rageClicks,
            'rage_click_score' => round($rageClickScore, 2),
            'scroll_variance' => round($scrollVariance, 2),
            'erratic_scroll_score' => round($erraticScrollScore, 2),
            'hesitation_time' => $hesitationTime,
            'hesitation_score' => round($hesitationScore, 2),
            'has_quick_exit' => $hasQuickExit,
            'quick_exit_score' => round($quickExitScore, 2),
            'error_clicks' => $errorClicks,
            'error_click_score' => round($errorClickScore, 2)
        ];

        return [
            'score' => round($frustrationScore, 2),
            'factors' => $factors
        ];
    }

    /**
     * Vypočítá interest score pro session (0-100)
     *
     * Faktory:
     * - Reading time (30%)
     * - Content scroll quality (25%)
     * - Return visits (20%)
     * - Focus time (15%)
     * - Content engagement (10%)
     *
     * @param string $sessionId
     * @return array ['score' => float, 'factors' => array]
     */
    public function vypocitejInterestScore(string $sessionId): array
    {
        $sessionData = $this->nactiSessionData($sessionId);
        $eventsData = $this->nactiEventsData($sessionId);
        $fingerprintId = $sessionData['fingerprint_id'] ?? null;

        if (!$sessionData) {
            return ['score' => 0.00, 'factors' => []];
        }

        // Faktory
        $readingTime = $this->vypocitejReadingTime($sessionId);
        $scrollQuality = $this->vypocitejScrollQuality($sessionId);
        $returnVisits = $fingerprintId ? $this->zjistiReturnVisits($fingerprintId) : 0;
        $sessionDuration = $sessionData['session_duration'] ?? 0;
        $focusTime = $sessionDuration; // Simplified - assume all time is focus
        $contentEngagement = $eventsData['content_interaction_count'] ?? 0;

        // Výpočet sub-scores (0-100)
        $readingTimeScore = min(($readingTime / 180) * 100, 100); // Max 3min = 100
        $scrollQualityScore = $scrollQuality; // Already 0-100
        $returnVisitScore = min(($returnVisits / 5) * 100, 100); // Max 5 návratů = 100
        $focusTimeScore = $sessionDuration > 0 ? ($focusTime / $sessionDuration) * 100 : 0;
        $contentEngagementScore = min(($contentEngagement / 5) * 100, 100); // Max 5 interactions = 100

        // Vážený průměr
        $interestScore = (
            $readingTimeScore * 0.30 +
            $scrollQualityScore * 0.25 +
            $returnVisitScore * 0.20 +
            $focusTimeScore * 0.15 +
            $contentEngagementScore * 0.10
        );

        $factors = [
            'reading_time' => $readingTime,
            'reading_time_score' => round($readingTimeScore, 2),
            'scroll_quality' => round($scrollQuality, 2),
            'scroll_quality_score' => round($scrollQualityScore, 2),
            'return_visits' => $returnVisits,
            'return_visit_score' => round($returnVisitScore, 2),
            'focus_time' => $focusTime,
            'focus_time_score' => round($focusTimeScore, 2),
            'content_engagement' => $contentEngagement,
            'content_engagement_score' => round($contentEngagementScore, 2)
        ];

        return [
            'score' => round($interestScore, 2),
            'factors' => $factors
        ];
    }

    /**
     * Aktualizuje všechny scores pro danou session a uloží do DB
     *
     * @param string $sessionId
     * @return bool
     */
    public function aktualizujScores(string $sessionId): bool
    {
        try {
            $engagementResult = $this->vypocitejEngagementScore($sessionId);
            $frustrationResult = $this->vypocitejFrustrationScore($sessionId);
            $interestResult = $this->vypocitejInterestScore($sessionId);

            $sessionData = $this->nactiSessionData($sessionId);
            $eventsData = $this->nactiEventsData($sessionId);

            if (!$sessionData) {
                return false;
            }

            // Agregované metriky
            $totalClicks = $eventsData['click_count'] ?? 0;
            $totalScrollEvents = $eventsData['scroll_count'] ?? 0;
            $totalDuration = $sessionData['session_duration'] ?? 0;
            $totalPageviews = $sessionData['pageview_count'] ?? 1;
            $clickQuality = $this->vypocitejClickQuality($sessionId);
            $scrollQuality = $this->vypocitejScrollQuality($sessionId);
            $readingTime = $this->vypocitejReadingTime($sessionId);

            // UPSERT do wgs_analytics_user_scores
            $sql = "
            INSERT INTO wgs_analytics_user_scores (
                session_id,
                fingerprint_id,
                engagement_score,
                engagement_factors,
                frustration_score,
                frustration_factors,
                interest_score,
                interest_factors,
                total_clicks,
                total_scroll_events,
                total_duration,
                total_pageviews,
                click_quality,
                scroll_quality,
                reading_time
            ) VALUES (
                :session_id,
                :fingerprint_id,
                :engagement_score,
                :engagement_factors,
                :frustration_score,
                :frustration_factors,
                :interest_score,
                :interest_factors,
                :total_clicks,
                :total_scroll_events,
                :total_duration,
                :total_pageviews,
                :click_quality,
                :scroll_quality,
                :reading_time
            ) ON DUPLICATE KEY UPDATE
                engagement_score = VALUES(engagement_score),
                engagement_factors = VALUES(engagement_factors),
                frustration_score = VALUES(frustration_score),
                frustration_factors = VALUES(frustration_factors),
                interest_score = VALUES(interest_score),
                interest_factors = VALUES(interest_factors),
                total_clicks = VALUES(total_clicks),
                total_scroll_events = VALUES(total_scroll_events),
                total_duration = VALUES(total_duration),
                total_pageviews = VALUES(total_pageviews),
                click_quality = VALUES(click_quality),
                scroll_quality = VALUES(scroll_quality),
                reading_time = VALUES(reading_time),
                updated_at = CURRENT_TIMESTAMP
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'session_id' => $sessionId,
                'fingerprint_id' => $sessionData['fingerprint_id'] ?? '',
                'engagement_score' => $engagementResult['score'],
                'engagement_factors' => json_encode($engagementResult['factors']),
                'frustration_score' => $frustrationResult['score'],
                'frustration_factors' => json_encode($frustrationResult['factors']),
                'interest_score' => $interestResult['score'],
                'interest_factors' => json_encode($interestResult['factors']),
                'total_clicks' => $totalClicks,
                'total_scroll_events' => $totalScrollEvents,
                'total_duration' => $totalDuration,
                'total_pageviews' => $totalPageviews,
                'click_quality' => $clickQuality,
                'scroll_quality' => $scrollQuality,
                'reading_time' => $readingTime
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::aktualizujScores() - Chyba: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Načte scores pro danou session z DB
     *
     * @param string $sessionId
     * @return array|null
     */
    public function nactiScores(string $sessionId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM wgs_analytics_user_scores
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                // Dekódovat JSON sloupce
                $result['engagement_factors'] = json_decode($result['engagement_factors'], true);
                $result['frustration_factors'] = json_decode($result['frustration_factors'], true);
                $result['interest_factors'] = json_decode($result['interest_factors'], true);
            }

            return $result ?: null;

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::nactiScores() - Chyba: " . $e->getMessage());
            return null;
        }
    }

    // ========================================
    // HELPER METHODS (PRIVATE)
    // ========================================

    /**
     * Načte session data z wgs_analytics_sessions
     *
     * @param string $sessionId
     * @return array|null
     */
    private function nactiSessionData(string $sessionId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    session_id,
                    fingerprint_id,
                    pageview_count,
                    TIMESTAMPDIFF(SECOND, session_start, session_end) as session_duration,
                    engagement_score as old_engagement_score
                FROM wgs_analytics_sessions
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::nactiSessionData() - Chyba: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Načte events data z wgs_analytics_events
     *
     * @param string $sessionId
     * @return array
     */
    private function nactiEventsData(string $sessionId): array
    {
        try {
            // Základní statistiky
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as click_count,
                    SUM(CASE WHEN event_type = 'scroll' THEN 1 ELSE 0 END) as scroll_count,
                    SUM(CASE WHEN event_type = 'rage_click' THEN 1 ELSE 0 END) as rage_click_count,
                    COUNT(DISTINCT event_type) as unique_event_types
                FROM wgs_analytics_events
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Mouse entropy (simplified - použijeme hodnotu z bot_signals pokud existuje)
            $mouseEntropy = 0.5; // Default fallback

            // Scroll variance (variance scroll depth values)
            $scrollVariance = $this->vypocitejScrollVariance($sessionId);

            // Hesitation time (čas bez eventů)
            $hesitationTime = $this->vypocitejHesitationTime($sessionId);

            // Error clicks (kliknutí mimo interaktivní prvky - simplified)
            $errorClicks = 0; // Zatím neimplementováno

            // Content interactions (copy, paste events)
            $contentInteractions = 0; // Zatím neimplementováno

            return [
                'total_events' => (int) ($stats['total_events'] ?? 0),
                'click_count' => (int) ($stats['click_count'] ?? 0),
                'scroll_count' => (int) ($stats['scroll_count'] ?? 0),
                'rage_click_count' => (int) ($stats['rage_click_count'] ?? 0),
                'unique_event_types' => (int) ($stats['unique_event_types'] ?? 0),
                'mouse_entropy' => $mouseEntropy,
                'scroll_variance' => $scrollVariance,
                'hesitation_time' => $hesitationTime,
                'error_click_count' => $errorClicks,
                'content_interaction_count' => $contentInteractions
            ];

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::nactiEventsData() - Chyba: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vypočítá click quality (0-100)
     * Kvalita = rychlost kliknutí, přesnost targetování, rozmanitost
     *
     * @param string $sessionId
     * @return float
     */
    private function vypocitejClickQuality(string $sessionId): float
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as click_count
                FROM wgs_analytics_events
                WHERE session_id = :session_id AND event_type = 'click'
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $clickCount = (int) ($result['click_count'] ?? 0);

            // Simplified quality score based on click count
            // Více kliků = vyšší aktivita = vyšší kvalita (do určité míry)
            $quality = min(($clickCount / 15) * 100, 100);

            return round($quality, 2);

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::vypocitejClickQuality() - Chyba: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Vypočítá scroll quality (0-100)
     * Kvalita = smooth scrolling, hloubka scrollu, čas strávený scrollováním
     *
     * @param string $sessionId
     * @return float
     */
    private function vypocitejScrollQuality(string $sessionId): float
    {
        try {
            // Zjistit průměrnou scroll depth z wgs_pageviews
            $stmt = $this->pdo->prepare("
                SELECT AVG(scroll_depth) as avg_scroll_depth
                FROM wgs_pageviews
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $avgScrollDepth = (float) ($result['avg_scroll_depth'] ?? 0);

            // Quality je přímo úměrná scroll depth (0-100)
            return round(min($avgScrollDepth, 100), 2);

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::vypocitejScrollQuality() - Chyba: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Vypočítá reading time (sekundy)
     * Odhad času stráveného čtením obsahu
     *
     * @param string $sessionId
     * @return int
     */
    private function vypocitejReadingTime(string $sessionId): int
    {
        try {
            // Simplified - předpokládejme 60% session duration je reading time
            $sessionData = $this->nactiSessionData($sessionId);
            $sessionDuration = $sessionData['session_duration'] ?? 0;

            $readingTime = (int) ($sessionDuration * 0.6);

            return max($readingTime, 0);

        } catch (Exception $e) {
            error_log("UserScoreCalculator::vypocitejReadingTime() - Chyba: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Vypočítá scroll variance (0-1000+)
     * Vysoká variance = erratic scrolling
     *
     * @param string $sessionId
     * @return float
     */
    private function vypocitejScrollVariance(string $sessionId): float
    {
        try {
            // Zjistit scroll depth values z pageviews
            $stmt = $this->pdo->prepare("
                SELECT scroll_depth
                FROM wgs_pageviews
                WHERE session_id = :session_id
            ");
            $stmt->execute(['session_id' => $sessionId]);
            $scrollDepths = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($scrollDepths) < 2) {
                return 0;
            }

            // Vypočítat variance
            $mean = array_sum($scrollDepths) / count($scrollDepths);
            $variance = 0;
            foreach ($scrollDepths as $depth) {
                $variance += pow($depth - $mean, 2);
            }
            $variance = $variance / count($scrollDepths);

            return round($variance, 2);

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::vypocitejScrollVariance() - Chyba: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Vypočítá hesitation time (sekundy)
     * Čas bez jakékoliv interakce
     *
     * @param string $sessionId
     * @return int
     */
    private function vypocitejHesitationTime(string $sessionId): int
    {
        // Simplified - zatím vrátíme 0
        // V budoucnosti by se dalo vypočítat jako průměrný gap mezi eventy
        return 0;
    }

    /**
     * Zjistí počet return visits pro daný fingerprint
     *
     * @param string $fingerprintId
     * @return int
     */
    private function zjistiReturnVisits(string $fingerprintId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT DATE(session_start)) as return_visits
                FROM wgs_analytics_sessions
                WHERE fingerprint_id = :fingerprint_id
            ");
            $stmt->execute(['fingerprint_id' => $fingerprintId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $visits = (int) ($result['return_visits'] ?? 1);

            // Return visits = total visits - 1 (první návštěva se nepočítá)
            return max($visits - 1, 0);

        } catch (PDOException $e) {
            error_log("UserScoreCalculator::zjistiReturnVisits() - Chyba: " . $e->getMessage());
            return 0;
        }
    }
}
?>
