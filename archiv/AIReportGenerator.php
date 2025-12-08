<?php
/**
 * AI Report Generator
 *
 * AI-powered report generation engine s insights, anomaly detection a recommendations.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #12 - AI Reports Engine
 */

class AIReportGenerator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================
    // HLAVNÍ METODA - Generování reportu
    // ========================================

    /**
     * Generuje kompletní report s AI insights
     *
     * @param string $reportType - daily, weekly, monthly
     * @param string $dateFrom - YYYY-MM-DD
     * @param string $dateTo - YYYY-MM-DD
     * @return array Report data
     */
    public function generujReport(string $reportType, string $dateFrom, string $dateTo): array
    {
        // 1. Sbírat data
        $data = $this->sbirData($dateFrom, $dateTo);

        // 2. Vypočítat summary metriky
        $summary = $this->vypocitejSummary($data);

        // 3. Analyzovat trendy (porovnání s předchozí periodou)
        $trends = $this->analyzujTrendy($reportType, $dateFrom, $dateTo, $summary);

        // 4. Generovat AI insights
        $insights = $this->generujInsights($data, $summary, $trends);

        // 5. Detekovat anomálie
        $anomalies = $this->detekujAnomalie($data);

        // 6. Generovat doporučení
        $recommendations = $this->generujDoporuceni($summary, $trends, $insights, $anomalies);

        return [
            'report_type' => $reportType,
            'period' => [
                'start' => $dateFrom,
                'end' => $dateTo
            ],
            'summary' => $summary,
            'trends' => $trends,
            'insights' => $insights,
            'anomalies' => $anomalies,
            'recommendations' => $recommendations,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    // ========================================
    // DATA COLLECTION
    // ========================================

    /**
     * Sbírá data ze všech relevantních tabulek
     */
    private function sbirData(string $dateFrom, string $dateTo): array
    {
        $data = [];

        // Sessions
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(session_start) as date,
                COUNT(*) as total_sessions,
                COUNT(DISTINCT fingerprint_id) as unique_visitors,
                AVG(session_duration) as avg_duration,
                SUM(pageview_count) as total_pageviews,
                AVG(pageview_count) as avg_pageviews,
                SUM(CASE WHEN pageview_count = 1 THEN 1 ELSE 0 END) as bounced_sessions
            FROM wgs_analytics_sessions
            WHERE DATE(session_start) BETWEEN :date_from AND :date_to
            GROUP BY DATE(session_start)
            ORDER BY date ASC
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $data['sessions_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Conversions (pokud tabulka existuje)
        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'wgs_analytics_conversions'");
        if ($tableCheck->rowCount() > 0) {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE(conversion_timestamp) as date,
                    COUNT(*) as total_conversions,
                    SUM(conversion_value) as total_value
                FROM wgs_analytics_conversions
                WHERE DATE(conversion_timestamp) BETWEEN :date_from AND :date_to
                GROUP BY DATE(conversion_timestamp)
            ");
            $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
            $data['conversions_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $data['conversions_by_date'] = [];
        }

        // Top pages
        $stmt = $this->pdo->prepare("
            SELECT
                s.landing_page,
                COUNT(*) as sessions,
                AVG(s.session_duration) as avg_duration
            FROM wgs_analytics_sessions s
            WHERE DATE(s.session_start) BETWEEN :date_from AND :date_to
            AND s.landing_page IS NOT NULL
            GROUP BY s.landing_page
            ORDER BY sessions DESC
            LIMIT 10
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $data['top_pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Traffic sources
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(utm_source, 'direct') as source,
                COUNT(*) as sessions
            FROM wgs_analytics_sessions
            WHERE DATE(session_start) BETWEEN :date_from AND :date_to
            GROUP BY source
            ORDER BY sessions DESC
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $data['traffic_sources'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Device types
        $stmt = $this->pdo->prepare("
            SELECT
                device_type,
                COUNT(*) as sessions
            FROM wgs_analytics_sessions
            WHERE DATE(session_start) BETWEEN :date_from AND :date_to
            AND device_type IS NOT NULL
            GROUP BY device_type
        ");
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $data['device_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    // ========================================
    // SUMMARY CALCULATION
    // ========================================

    /**
     * Vypočítá summary metriky
     */
    private function vypocitejSummary(array $data): array
    {
        $totalSessions = 0;
        $totalPageviews = 0;
        $uniqueVisitors = 0;
        $totalDuration = 0;
        $bouncedSessions = 0;

        foreach ($data['sessions_by_date'] as $day) {
            $totalSessions += (int) $day['total_sessions'];
            $totalPageviews += (int) $day['total_pageviews'];
            $uniqueVisitors += (int) $day['unique_visitors'];
            $totalDuration += (float) $day['avg_duration'] * (int) $day['total_sessions'];
            $bouncedSessions += (int) $day['bounced_sessions'];
        }

        $totalConversions = 0;
        $totalConversionValue = 0;

        foreach ($data['conversions_by_date'] as $day) {
            $totalConversions += (int) $day['total_conversions'];
            $totalConversionValue += (float) $day['total_value'];
        }

        $avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions, 2) : 0;
        $bounceRate = $totalSessions > 0 ? round(($bouncedSessions / $totalSessions) * 100, 2) : 0;
        $conversionRate = $totalSessions > 0 ? round(($totalConversions / $totalSessions) * 100, 2) : 0;

        return [
            'total_sessions' => $totalSessions,
            'total_pageviews' => $totalPageviews,
            'unique_visitors' => $uniqueVisitors,
            'avg_session_duration' => $avgDuration,
            'bounce_rate' => $bounceRate,
            'total_conversions' => $totalConversions,
            'conversion_rate' => $conversionRate,
            'total_conversion_value' => $totalConversionValue
        ];
    }

    // ========================================
    // TREND ANALYSIS
    // ========================================

    /**
     * Analyzuje trendy porovnáním s předchozí periodou
     */
    private function analyzujTrendy(string $reportType, string $dateFrom, string $dateTo, array $currentSummary): array
    {
        // Určit předchozí periodu
        $daysInPeriod = (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1;
        $prevDateTo = date('Y-m-d', strtotime($dateFrom) - 86400);
        $prevDateFrom = date('Y-m-d', strtotime($prevDateTo) - ($daysInPeriod - 1) * 86400);

        // Sbírat data z předchozí periody
        $prevData = $this->sbirData($prevDateFrom, $prevDateTo);
        $prevSummary = $this->vypocitejSummary($prevData);

        $trends = [];
        $metriky = [
            'total_sessions' => 'Celkem sessions',
            'unique_visitors' => 'Unikátní návštěvníci',
            'total_pageviews' => 'Celkem pageviews',
            'avg_session_duration' => 'Průměrná doba trvání',
            'bounce_rate' => 'Bounce rate',
            'conversion_rate' => 'Conversion rate'
        ];

        foreach ($metriky as $key => $label) {
            $current = $currentSummary[$key];
            $previous = $prevSummary[$key];

            if ($previous > 0) {
                $changePercent = (($current - $previous) / $previous) * 100;
            } else {
                $changePercent = $current > 0 ? 100 : 0;
            }

            $trends[] = [
                'metric' => $key,
                'label' => $label,
                'current' => $current,
                'previous' => $previous,
                'change_percent' => round($changePercent, 2),
                'trend' => $changePercent > 0 ? 'up' : ($changePercent < 0 ? 'down' : 'stable'),
                'is_significant' => abs($changePercent) > 10
            ];
        }

        return $trends;
    }

    // ========================================
    // AI INSIGHTS
    // ========================================

    /**
     * Generuje AI insights
     */
    private function generujInsights(array $data, array $summary, array $trends): array
    {
        $insights = [];

        // Insight 1: Peak traffic day
        $maxSessions = 0;
        $peakDay = null;

        foreach ($data['sessions_by_date'] as $day) {
            if ((int) $day['total_sessions'] > $maxSessions) {
                $maxSessions = (int) $day['total_sessions'];
                $peakDay = $day['date'];
            }
        }

        if ($peakDay) {
            $dayName = date('l', strtotime($peakDay));
            $insights[] = [
                'type' => 'peak_traffic',
                'title' => 'Nejvíce návštěv dne ' . date('j.n.Y', strtotime($peakDay)),
                'description' => "Peak traffic byl {$maxSessions} sessions ({$dayName})",
                'confidence' => 0.95
            ];
        }

        // Insight 2: Best performing page
        if (!empty($data['top_pages'])) {
            $topPage = $data['top_pages'][0];
            $insights[] = [
                'type' => 'top_page',
                'title' => 'Nejnavštěvovanější stránka',
                'description' => "{$topPage['landing_page']} s {$topPage['sessions']} sessions",
                'confidence' => 0.90
            ];
        }

        // Insight 3: Main traffic source
        if (!empty($data['traffic_sources'])) {
            $topSource = $data['traffic_sources'][0];
            $totalSessions = $summary['total_sessions'];
            $sourcePercent = $totalSessions > 0 ? round(($topSource['sessions'] / $totalSessions) * 100, 1) : 0;

            $insights[] = [
                'type' => 'traffic_source',
                'title' => 'Hlavní zdroj návštěvnosti',
                'description' => "{$topSource['source']} ({$sourcePercent}% návštěv)",
                'confidence' => 0.88
            ];
        }

        // Insight 4: Device preference
        if (!empty($data['device_types'])) {
            usort($data['device_types'], function($a, $b) {
                return $b['sessions'] - $a['sessions'];
            });

            $topDevice = $data['device_types'][0];
            $devicePercent = $summary['total_sessions'] > 0 ? round(($topDevice['sessions'] / $summary['total_sessions']) * 100, 1) : 0;

            $insights[] = [
                'type' => 'device_preference',
                'title' => 'Preferované zařízení',
                'description' => "{$topDevice['device_type']} ({$devicePercent}% návštěv)",
                'confidence' => 0.85
            ];
        }

        return $insights;
    }

    // ========================================
    // ANOMALY DETECTION
    // ========================================

    /**
     * Detekuje anomálie pomocí Z-score
     */
    private function detekujAnomalie(array $data): array
    {
        if (empty($data['sessions_by_date'])) {
            return [];
        }

        $values = array_map(function($day) {
            return (int) $day['total_sessions'];
        }, $data['sessions_by_date']);

        if (count($values) < 3) {
            return []; // Nedostatek dat pro statistickou analýzu
        }

        $mean = array_sum($values) / count($values);
        $stdDev = $this->calculateStdDev($values, $mean);

        if ($stdDev == 0) {
            return []; // Žádná variabilita
        }

        $anomalies = [];

        foreach ($data['sessions_by_date'] as $day) {
            $value = (int) $day['total_sessions'];
            $zScore = ($value - $mean) / $stdDev;

            if (abs($zScore) > 2) { // 2 standard deviations
                $anomalies[] = [
                    'date' => $day['date'],
                    'metric' => 'sessions',
                    'value' => $value,
                    'expected' => round($mean, 0),
                    'z_score' => round($zScore, 2),
                    'severity' => abs($zScore) > 3 ? 'high' : 'medium',
                    'direction' => $zScore > 0 ? 'spike' : 'drop'
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Vypočítá směrodatnou odchylku
     */
    private function calculateStdDev(array $values, float $mean): float
    {
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        $variance /= count($values);

        return sqrt($variance);
    }

    // ========================================
    // RECOMMENDATIONS
    // ========================================

    /**
     * Generuje AI doporučení
     */
    private function generujDoporuceni(array $summary, array $trends, array $insights, array $anomalies): array
    {
        $recommendations = [];

        // Doporučení based on trends
        foreach ($trends as $trend) {
            // High bounce rate
            if ($trend['metric'] === 'bounce_rate' && $trend['trend'] === 'up' && $trend['change_percent'] > 20) {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'user_experience',
                    'title' => 'Rostoucí bounce rate',
                    'description' => "Bounce rate vzrostl o {$trend['change_percent']}%",
                    'action' => 'Zkontrolujte UX landing pages, loading speed a relevanci obsahu'
                ];
            }

            // Declining conversions
            if ($trend['metric'] === 'conversion_rate' && $trend['trend'] === 'down' && abs($trend['change_percent']) > 15) {
                $recommendations[] = [
                    'priority' => 'critical',
                    'category' => 'conversions',
                    'title' => 'Klesající conversion rate',
                    'description' => "Conversion rate klesl o {$trend['change_percent']}%",
                    'action' => 'Analyzujte conversion funnels a checkout proces'
                ];
            }

            // Growing traffic
            if ($trend['metric'] === 'total_sessions' && $trend['trend'] === 'up' && $trend['change_percent'] > 30) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'growth',
                    'title' => 'Růst návštěvnosti',
                    'description' => "Návštěvnost vzrostla o {$trend['change_percent']}%",
                    'action' => 'Zajistěte dostatečnou serverovou kapacitu a optimalizujte conversion funnels'
                ];
            }
        }

        // Doporučení based on anomalies
        foreach ($anomalies as $anomaly) {
            if ($anomaly['severity'] === 'high') {
                $recommendations[] = [
                    'priority' => 'high',
                    'category' => 'anomaly',
                    'title' => 'Detekována anomálie',
                    'description' => "Neobvyklá hodnota ({$anomaly['value']}) dne {$anomaly['date']}",
                    'action' => 'Prozkoumejte marketing campaigns, technické problémy nebo external events'
                ];
            }
        }

        // General recommendations
        if ($summary['bounce_rate'] > 60) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'content',
                'title' => 'Vysoký bounce rate',
                'description' => "Bounce rate je {$summary['bounce_rate']}%",
                'action' => 'Zlepšete relevanci obsahu a call-to-action elementy'
            ];
        }

        if ($summary['avg_session_duration'] < 30) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'engagement',
                'title' => 'Nízký engagement',
                'description' => "Průměrná doba trvání je {$summary['avg_session_duration']}s",
                'action' => 'Přidejte interaktivní elementy a zlepšete čitelnost obsahu'
            ];
        }

        return $recommendations;
    }

    // ========================================
    // SAVE REPORT
    // ========================================

    /**
     * Uloží report do databáze
     */
    public function ulozReport(array $reportData): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_analytics_reports (
                report_type,
                report_period_start,
                report_period_end,
                report_data,
                insights,
                recommendations,
                anomalies,
                status,
                generated_at,
                generated_by
            ) VALUES (
                :report_type,
                :period_start,
                :period_end,
                :report_data,
                :insights,
                :recommendations,
                :anomalies,
                'completed',
                NOW(),
                :generated_by
            )
        ");

        $stmt->execute([
            'report_type' => $reportData['report_type'],
            'period_start' => $reportData['period']['start'],
            'period_end' => $reportData['period']['end'],
            'report_data' => json_encode($reportData),
            'insights' => json_encode($reportData['insights']),
            'recommendations' => json_encode($reportData['recommendations']),
            'anomalies' => json_encode($reportData['anomalies']),
            'generated_by' => $_SESSION['user_id'] ?? 'auto'
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    // ========================================
    // EXPORT HTML
    // ========================================

    /**
     * Exportuje report do HTML
     */
    public function exportHTML(int $reportId): string
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_reports WHERE report_id = :id");
        $stmt->execute(['id' => $reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new Exception('Report not found');
        }

        $reportData = json_decode($report['report_data'], true);

        // Generate HTML (simplified version)
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Report</title></head><body>";
        $html .= "<h1>Analytics Report</h1>";
        $html .= "<p>Period: {$reportData['period']['start']} - {$reportData['period']['end']}</p>";
        $html .= "<h2>Summary</h2>";
        $html .= "<pre>" . print_r($reportData['summary'], true) . "</pre>";
        $html .= "</body></html>";

        return $html;
    }
}
?>
