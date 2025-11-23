<?php
/**
 * CampaignAttribution - Attribution modelování a agregace UTM kampaní
 *
 * Třída pro výpočet attribution modelů a agregaci campaign metrik.
 *
 * Attribution modely:
 * - **First-Click Attribution:** 100% kreditu první kampani
 * - **Last-Click Attribution:** 100% kreditu poslední kampani před konverzí
 * - **Linear Attribution:** Rovnoměrné rozdělení mezi všechny kampaně
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #8 - UTM Campaign Tracking
 */

class CampaignAttribution
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Agregace denních statistik kampaní
     *
     * Agreguje data z wgs_analytics_sessions do wgs_analytics_utm_campaigns.
     * UPSERT pattern: INSERT ON DUPLICATE KEY UPDATE
     *
     * @param string $date Datum ve formátu 'Y-m-d'
     * @return array Statistiky agregace
     */
    public function agregujDenniStatistiky($date)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                s.utm_source,
                s.utm_medium,
                s.utm_campaign,
                s.utm_content,
                s.utm_term,
                s.device_type,
                DATE(s.first_seen) as session_date,

                -- Traffic metriky
                COUNT(DISTINCT s.session_id) as sessions_count,
                SUM(s.pageviews_count) as pageviews_count,
                COUNT(DISTINCT s.fingerprint_id) as unique_visitors,

                -- Engagement metriky
                AVG(TIMESTAMPDIFF(SECOND, s.first_seen, s.last_seen)) as avg_session_duration,
                AVG(s.pageviews_count) as avg_pages_per_session,
                SUM(CASE WHEN s.pageviews_count = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as bounce_rate

            FROM wgs_analytics_sessions s
            WHERE DATE(s.first_seen) = :date
              AND (s.utm_source IS NOT NULL OR s.utm_medium IS NOT NULL OR s.utm_campaign IS NOT NULL)
              AND s.is_bot = 0
            GROUP BY
                s.utm_source,
                s.utm_medium,
                s.utm_campaign,
                s.utm_content,
                s.utm_term,
                s.device_type,
                DATE(s.first_seen)
        ");

        $stmt->execute(['date' => $date]);
        $kampaně = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pocetAgregovanychRadku = 0;

        foreach ($kampaně as $kampan) {
            $this->aktualizujCampaignMetriky(
                $kampan['utm_source'],
                $kampan['utm_medium'],
                $kampan['utm_campaign'],
                $kampan['utm_content'],
                $kampan['utm_term'],
                $kampan['device_type'],
                $date,
                [
                    'sessions_count' => (int)$kampan['sessions_count'],
                    'pageviews_count' => (int)$kampan['pageviews_count'],
                    'unique_visitors' => (int)$kampan['unique_visitors'],
                    'avg_session_duration' => round($kampan['avg_session_duration'], 2),
                    'avg_pages_per_session' => round($kampan['avg_pages_per_session'], 2),
                    'bounce_rate' => round($kampan['bounce_rate'], 2)
                ]
            );

            $pocetAgregovanychRadku++;
        }

        return [
            'date' => $date,
            'agregated_rows' => $pocetAgregovanychRadku,
            'campaigns_processed' => count($kampaně)
        ];
    }

    /**
     * Aktualizace campaign metrik (UPSERT pattern)
     *
     * @param string|null $utmSource
     * @param string|null $utmMedium
     * @param string|null $utmCampaign
     * @param string|null $utmContent
     * @param string|null $utmTerm
     * @param string|null $deviceType
     * @param string $date
     * @param array $metriky
     * @return bool
     */
    public function aktualizujCampaignMetriky(
        $utmSource,
        $utmMedium,
        $utmCampaign,
        $utmContent,
        $utmTerm,
        $deviceType,
        $date,
        array $metriky
    ) {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_analytics_utm_campaigns (
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                device_type,
                date,
                sessions_count,
                pageviews_count,
                unique_visitors,
                avg_session_duration,
                avg_pages_per_session,
                bounce_rate
            ) VALUES (
                :utm_source,
                :utm_medium,
                :utm_campaign,
                :utm_content,
                :utm_term,
                :device_type,
                :date,
                :sessions_count,
                :pageviews_count,
                :unique_visitors,
                :avg_session_duration,
                :avg_pages_per_session,
                :bounce_rate
            )
            ON DUPLICATE KEY UPDATE
                sessions_count = VALUES(sessions_count),
                pageviews_count = VALUES(pageviews_count),
                unique_visitors = VALUES(unique_visitors),
                avg_session_duration = VALUES(avg_session_duration),
                avg_pages_per_session = VALUES(avg_pages_per_session),
                bounce_rate = VALUES(bounce_rate),
                last_updated = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'device_type' => $deviceType,
            'date' => $date,
            'sessions_count' => $metriky['sessions_count'] ?? 0,
            'pageviews_count' => $metriky['pageviews_count'] ?? 0,
            'unique_visitors' => $metriky['unique_visitors'] ?? 0,
            'avg_session_duration' => $metriky['avg_session_duration'] ?? 0,
            'avg_pages_per_session' => $metriky['avg_pages_per_session'] ?? 0,
            'bounce_rate' => $metriky['bounce_rate'] ?? 0
        ]);
    }

    /**
     * Vypočítat attribution model pro konverzi
     *
     * Zavolá všechny 3 attribution modely a vrátí výsledky.
     *
     * @param string $sessionId Session ID, kde proběhla konverze
     * @param string $fingerprintId Fingerprint ID uživatele
     * @param float $conversionValue Hodnota konverze
     * @return array Attribution data
     */
    public function vypocitejAttributionModel($sessionId, $fingerprintId, $conversionValue)
    {
        return [
            'first_click' => $this->zjistiPrvniKampan($fingerprintId),
            'last_click' => $this->zjistiPosledniKampan($sessionId),
            'linear' => $this->vypocitejLinearniAttributi($fingerprintId, $conversionValue)
        ];
    }

    /**
     * First-Click Attribution
     *
     * Najde první kampaň, která uživatele přivedla (podle fingerprint_id).
     *
     * @param string $fingerprintId
     * @return array|null Campaign data nebo null
     */
    public function zjistiPrvniKampan($fingerprintId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                first_seen as campaign_first_seen
            FROM wgs_analytics_sessions
            WHERE fingerprint_id = :fingerprint_id
              AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
            ORDER BY first_seen ASC
            LIMIT 1
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Last-Click Attribution
     *
     * Najde poslední kampaň před konverzí (v rámci session nebo fingerprintu).
     *
     * @param string $sessionId
     * @return array|null Campaign data nebo null
     */
    public function zjistiPosledniKampan($sessionId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                first_seen as campaign_last_seen
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
              AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
            ORDER BY first_seen DESC
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Linear Attribution
     *
     * Rozdělí kredit rovnoměrně mezi všechny kampaně v conversion path.
     *
     * @param string $fingerprintId
     * @param float $conversionValue
     * @return array Seznam kampaní s jejich podílem kreditu
     */
    public function vypocitejLinearniAttributi($fingerprintId, $conversionValue)
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                first_seen
            FROM wgs_analytics_sessions
            WHERE fingerprint_id = :fingerprint_id
              AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
            ORDER BY first_seen ASC
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $kampaně = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($kampaně)) {
            return [];
        }

        $pocetKampani = count($kampaně);
        $kreditNaKampan = $conversionValue / $pocetKampani;

        $attributionData = [];

        foreach ($kampaně as $kampan) {
            $attributionData[] = [
                'utm_source' => $kampan['utm_source'],
                'utm_medium' => $kampan['utm_medium'],
                'utm_campaign' => $kampan['utm_campaign'],
                'utm_content' => $kampan['utm_content'],
                'utm_term' => $kampan['utm_term'],
                'credit' => round($kreditNaKampan, 2),
                'percentage' => round(100 / $pocetKampani, 2)
            ];
        }

        return $attributionData;
    }

    /**
     * Aktualizovat conversion metriky pro kampaň
     *
     * Zavolá se při konverzi, aby se aktualizovaly conversion_count, conversion_value, atd.
     *
     * @param array $kampan UTM parametry (source, medium, campaign, content, term)
     * @param string $date Datum konverze
     * @param string $deviceType
     * @param float $conversionValue Hodnota konverze
     * @param string $attributionModel 'first_click', 'last_click', nebo 'linear'
     * @return bool
     */
    public function aktualizujConversionMetriky(
        array $kampan,
        $date,
        $deviceType,
        $conversionValue,
        $attributionModel = 'last_click'
    ) {
        // Najít nebo vytvořit záznam pro tuto kampaň a datum
        $stmt = $this->pdo->prepare("
            SELECT id, sessions_count, conversions_count
            FROM wgs_analytics_utm_campaigns
            WHERE utm_source <=> :utm_source
              AND utm_medium <=> :utm_medium
              AND utm_campaign <=> :utm_campaign
              AND utm_content <=> :utm_content
              AND utm_term <=> :utm_term
              AND date = :date
              AND device_type <=> :device_type
            LIMIT 1
        ");

        $stmt->execute([
            'utm_source' => $kampan['utm_source'] ?? null,
            'utm_medium' => $kampan['utm_medium'] ?? null,
            'utm_campaign' => $kampan['utm_campaign'] ?? null,
            'utm_content' => $kampan['utm_content'] ?? null,
            'utm_term' => $kampan['utm_term'] ?? null,
            'date' => $date,
            'device_type' => $deviceType
        ]);

        $existujiciZaznam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existujiciZaznam) {
            // Vytvořit nový záznam s konverzí
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_analytics_utm_campaigns (
                    utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                    date, device_type,
                    conversions_count, conversion_value,
                    " . ($attributionModel === 'first_click' ? 'first_click_conversions' : '') . "
                    " . ($attributionModel === 'last_click' ? 'last_click_conversions' : '') . "
                    " . ($attributionModel === 'linear' ? 'linear_attribution_value' : '') . "
                ) VALUES (
                    :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term,
                    :date, :device_type,
                    1, :conversion_value,
                    " . ($attributionModel === 'first_click' ? '1' : '0') . ",
                    " . ($attributionModel === 'last_click' ? '1' : '0') . ",
                    " . ($attributionModel === 'linear' ? ':conversion_value' : '0') . "
                )
            ");

            return $stmt->execute([
                'utm_source' => $kampan['utm_source'] ?? null,
                'utm_medium' => $kampan['utm_medium'] ?? null,
                'utm_campaign' => $kampan['utm_campaign'] ?? null,
                'utm_content' => $kampan['utm_content'] ?? null,
                'utm_term' => $kampan['utm_term'] ?? null,
                'date' => $date,
                'device_type' => $deviceType,
                'conversion_value' => $conversionValue
            ]);
        }

        // Aktualizovat existující záznam
        $novyPocetConverzi = $existujiciZaznam['conversions_count'] + 1;
        $conversionRate = $existujiciZaznam['sessions_count'] > 0
            ? round(($novyPocetConverzi / $existujiciZaznam['sessions_count']) * 100, 2)
            : 0;

        $updateSql = "
            UPDATE wgs_analytics_utm_campaigns
            SET conversions_count = conversions_count + 1,
                conversion_value = conversion_value + :conversion_value,
                conversion_rate = :conversion_rate,
        ";

        if ($attributionModel === 'first_click') {
            $updateSql .= " first_click_conversions = first_click_conversions + 1, ";
        } elseif ($attributionModel === 'last_click') {
            $updateSql .= " last_click_conversions = last_click_conversions + 1, ";
        } elseif ($attributionModel === 'linear') {
            $updateSql .= " linear_attribution_value = linear_attribution_value + :conversion_value, ";
        }

        $updateSql .= "
                last_updated = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($updateSql);

        return $stmt->execute([
            'conversion_value' => $conversionValue,
            'conversion_rate' => $conversionRate,
            'id' => $existujiciZaznam['id']
        ]);
    }

    /**
     * Získat top kampaně podle metrik
     *
     * @param string $orderBy 'sessions', 'conversions', 'conversion_rate', 'revenue'
     * @param int $limit
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function nactiTopKampaně($orderBy = 'sessions', $limit = 10, $dateFrom = null, $dateTo = null)
    {
        $orderByMapping = [
            'sessions' => 'sessions_count DESC',
            'conversions' => 'conversions_count DESC',
            'conversion_rate' => 'conversion_rate DESC',
            'revenue' => 'conversion_value DESC'
        ];

        $orderByClause = $orderByMapping[$orderBy] ?? 'sessions_count DESC';

        $whereClauses = [];
        $params = [];

        if ($dateFrom) {
            $whereClauses[] = 'date >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClauses[] = 'date <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $stmt = $this->pdo->prepare("
            SELECT
                utm_source,
                utm_medium,
                utm_campaign,
                SUM(sessions_count) as total_sessions,
                SUM(pageviews_count) as total_pageviews,
                SUM(unique_visitors) as total_unique_visitors,
                AVG(avg_session_duration) as avg_duration,
                AVG(bounce_rate) as avg_bounce_rate,
                SUM(conversions_count) as total_conversions,
                SUM(conversion_value) as total_revenue,
                AVG(conversion_rate) as avg_conversion_rate
            FROM wgs_analytics_utm_campaigns
            {$whereClause}
            GROUP BY utm_source, utm_medium, utm_campaign
            ORDER BY {$orderByClause}
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
