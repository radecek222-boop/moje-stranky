<?php
/**
 * ConversionFunnel - Conversion tracking a funnel analýza
 *
 * Třída pro zaznamenávání konverzí a analýzu conversion funnelů.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #9 - Conversion Funnels
 */

class ConversionFunnel
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Zaznamenat konverzi
     *
     * @param string $sessionId
     * @param string $conversionType
     * @param string|null $conversionLabel
     * @param float $conversionValue
     * @param array|null $metadata
     * @return int ID vytvořené konverze
     */
    public function zaznamenatKonverzi(
        $sessionId,
        $conversionType,
        $conversionLabel = null,
        $conversionValue = 0,
        $metadata = null
    ) {
        // Získat session data
        $sessionData = $this->nactiSessionData($sessionId);

        if (!$sessionData) {
            throw new Exception("Session ID {$sessionId} neexistuje");
        }

        // Vypočítat conversion path
        $conversionPath = $this->vypocitetConversionPath($sessionId);

        // Vypočítat time to conversion
        $timeToConversion = $this->vypocitetTimeToConversion($sessionId);

        // Počet kroků
        $stepsToConversion = count($conversionPath);

        // Insert conversion
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_analytics_conversions (
                session_id,
                fingerprint_id,
                conversion_type,
                conversion_label,
                conversion_value,
                conversion_path,
                time_to_conversion,
                steps_to_conversion,
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                page_url,
                device_type,
                country,
                metadata,
                created_at
            ) VALUES (
                :session_id,
                :fingerprint_id,
                :conversion_type,
                :conversion_label,
                :conversion_value,
                :conversion_path,
                :time_to_conversion,
                :steps_to_conversion,
                :utm_source,
                :utm_medium,
                :utm_campaign,
                :utm_content,
                :utm_term,
                :page_url,
                :device_type,
                :country,
                :metadata,
                NOW()
            )
        ");

        $stmt->execute([
            'session_id' => $sessionId,
            'fingerprint_id' => $sessionData['fingerprint_id'],
            'conversion_type' => $conversionType,
            'conversion_label' => $conversionLabel,
            'conversion_value' => $conversionValue,
            'conversion_path' => json_encode($conversionPath),
            'time_to_conversion' => $timeToConversion,
            'steps_to_conversion' => $stepsToConversion,
            'utm_source' => $sessionData['utm_source'],
            'utm_medium' => $sessionData['utm_medium'],
            'utm_campaign' => $sessionData['utm_campaign'],
            'utm_content' => $sessionData['utm_content'],
            'utm_term' => $sessionData['utm_term'],
            'page_url' => $_SERVER['REQUEST_URI'] ?? null,
            'device_type' => $sessionData['device_type'],
            'country' => $sessionData['country'],
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Načíst session data
     *
     * @param string $sessionId
     * @return array|null
     */
    private function nactiSessionData($sessionId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                fingerprint_id,
                utm_source,
                utm_medium,
                utm_campaign,
                utm_content,
                utm_term,
                device_type,
                country,
                session_start
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Vypočítat conversion path (pole URL stránek před konverzí)
     *
     * @param string $sessionId
     * @return array
     */
    public function vypocitetConversionPath($sessionId)
    {
        $stmt = $this->pdo->prepare("
            SELECT
                page_url,
                page_title,
                created_at
            FROM wgs_pageviews
            WHERE session_id = :session_id
            ORDER BY created_at ASC
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $pageviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $path = [];
        foreach ($pageviews as $pv) {
            $path[] = [
                'url' => $pv['page_url'],
                'title' => $pv['page_title'],
                'timestamp' => $pv['created_at']
            ];
        }

        return $path;
    }

    /**
     * Vypočítat time to conversion (sekundy od začátku session)
     *
     * @param string $sessionId
     * @return int|null Sekundy nebo null pokud nelze vypočítat
     */
    public function vypocitetTimeToConversion($sessionId)
    {
        $stmt = $this->pdo->prepare("
            SELECT session_start
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        $sessionStart = strtotime($session['session_start']);
        $now = time();

        return $now - $sessionStart;
    }

    /**
     * Načíst konverze s filtry
     *
     * @param array $filters
     * @return array
     */
    public function nactiKonverze(array $filters = [])
    {
        $whereClauses = [];
        $params = [];

        // Date range
        if (!empty($filters['date_from'])) {
            $whereClauses[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClauses[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        // Conversion type
        if (!empty($filters['conversion_type'])) {
            $whereClauses[] = 'conversion_type = :conversion_type';
            $params['conversion_type'] = $filters['conversion_type'];
        }

        // UTM campaign
        if (!empty($filters['utm_campaign'])) {
            $whereClauses[] = 'utm_campaign = :utm_campaign';
            $params['utm_campaign'] = $filters['utm_campaign'];
        }

        // Device type
        if (!empty($filters['device_type'])) {
            $whereClauses[] = 'device_type = :device_type';
            $params['device_type'] = $filters['device_type'];
        }

        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $limit = isset($filters['limit']) ? min((int)$filters['limit'], 1000) : 100;

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                session_id,
                fingerprint_id,
                conversion_type,
                conversion_label,
                conversion_value,
                conversion_path,
                time_to_conversion,
                steps_to_conversion,
                utm_source,
                utm_medium,
                utm_campaign,
                page_url,
                device_type,
                country,
                metadata,
                created_at
            FROM wgs_analytics_conversions
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analyzovat funnel
     *
     * @param int $funnelId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array Funnel data s drop-off analýzou
     */
    public function analyzFunnel($funnelId, $dateFrom = null, $dateTo = null)
    {
        // Načíst funnel definici
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_analytics_funnels WHERE id = :id");
        $stmt->execute(['id' => $funnelId]);
        $funnel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$funnel) {
            throw new Exception("Funnel ID {$funnelId} neexistuje");
        }

        $steps = json_decode($funnel['funnel_steps'], true);

        // Analyzovat každý krok
        $analysis = [];
        $previousStepCount = null;

        foreach ($steps as $step) {
            $stepData = $this->analyzFunnelStep(
                $step['url_pattern'],
                $dateFrom,
                $dateTo,
                $previousStepCount
            );

            $analysis[] = [
                'step' => $step['step'],
                'label' => $step['label'],
                'url_pattern' => $step['url_pattern'],
                'users_count' => $stepData['users_count'],
                'sessions_count' => $stepData['sessions_count'],
                'drop_off_rate' => $stepData['drop_off_rate'],
                'conversion_rate' => $stepData['conversion_rate']
            ];

            $previousStepCount = $stepData['users_count'];
        }

        return [
            'funnel_id' => $funnelId,
            'funnel_name' => $funnel['funnel_name'],
            'funnel_description' => $funnel['funnel_description'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'steps' => $analysis,
            'overall_conversion_rate' => $this->vypocitetOverallConversionRate($analysis)
        ];
    }

    /**
     * Analyzovat jeden krok funnelu
     *
     * @param string $urlPattern
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int|null $previousStepCount
     * @return array
     */
    private function analyzFunnelStep($urlPattern, $dateFrom, $dateTo, $previousStepCount)
    {
        // Převést URL pattern na SQL LIKE pattern
        $likePattern = str_replace('*', '%', $urlPattern);

        $whereClauses = ["page_url LIKE :url_pattern"];
        $params = ['url_pattern' => $likePattern];

        if ($dateFrom) {
            $whereClauses[] = 'created_at >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $whereClauses[] = 'created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Počet unikátních sessions
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT session_id) as sessions_count
            FROM wgs_pageviews
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sessionsCount = (int)$result['sessions_count'];

        // Drop-off rate (pokud máme previous step)
        $dropOffRate = 0;
        if ($previousStepCount !== null && $previousStepCount > 0) {
            $dropOffRate = round((($previousStepCount - $sessionsCount) / $previousStepCount) * 100, 2);
        }

        // Conversion rate (od začátku funnelu)
        $conversionRate = 0;
        // Toto bude vypočítáno na úrovni celého funnelu

        return [
            'users_count' => $sessionsCount,
            'sessions_count' => $sessionsCount,
            'drop_off_rate' => $dropOffRate,
            'conversion_rate' => $conversionRate
        ];
    }

    /**
     * Vypočítat overall conversion rate funnelu
     *
     * @param array $steps
     * @return float
     */
    private function vypocitetOverallConversionRate(array $steps)
    {
        if (empty($steps)) {
            return 0;
        }

        $firstStep = $steps[0];
        $lastStep = $steps[count($steps) - 1];

        if ($firstStep['users_count'] == 0) {
            return 0;
        }

        return round(($lastStep['users_count'] / $firstStep['users_count']) * 100, 2);
    }

    /**
     * Vytvořit nový funnel
     *
     * @param string $name
     * @param string $description
     * @param array $steps
     * @param string|null $goalConversionType
     * @return int ID vytvořeného funnelu
     */
    public function vytvorFunnel($name, $description, array $steps, $goalConversionType = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wgs_analytics_funnels (
                funnel_name,
                funnel_description,
                funnel_steps,
                goal_conversion_type
            ) VALUES (
                :funnel_name,
                :funnel_description,
                :funnel_steps,
                :goal_conversion_type
            )
        ");

        $stmt->execute([
            'funnel_name' => $name,
            'funnel_description' => $description,
            'funnel_steps' => json_encode($steps),
            'goal_conversion_type' => $goalConversionType
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Aktualizovat existující funnel
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function aktualizovatFunnel($id, array $data)
    {
        $updates = [];
        $params = ['id' => $id];

        if (isset($data['funnel_name'])) {
            $updates[] = 'funnel_name = :funnel_name';
            $params['funnel_name'] = $data['funnel_name'];
        }

        if (isset($data['funnel_description'])) {
            $updates[] = 'funnel_description = :funnel_description';
            $params['funnel_description'] = $data['funnel_description'];
        }

        if (isset($data['funnel_steps'])) {
            $updates[] = 'funnel_steps = :funnel_steps';
            $params['funnel_steps'] = json_encode($data['funnel_steps']);
        }

        if (isset($data['is_active'])) {
            $updates[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (isset($data['goal_conversion_type'])) {
            $updates[] = 'goal_conversion_type = :goal_conversion_type';
            $params['goal_conversion_type'] = $data['goal_conversion_type'];
        }

        if (empty($updates)) {
            return false;
        }

        $updateClause = implode(', ', $updates);

        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_funnels
            SET {$updateClause}
            WHERE id = :id
        ");

        return $stmt->execute($params);
    }

    /**
     * Načíst všechny funnely
     *
     * @param bool $activeOnly
     * @return array
     */
    public function nactiFunnely($activeOnly = false)
    {
        $whereClause = $activeOnly ? 'WHERE is_active = 1' : '';

        $stmt = $this->pdo->query("
            SELECT * FROM wgs_analytics_funnels
            {$whereClause}
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Získat conversion statistiky
     *
     * @param array $filters
     * @return array
     */
    public function nactiConversionStatistiky(array $filters = [])
    {
        $whereClauses = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $whereClauses[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClauses[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['conversion_type'])) {
            $whereClauses[] = 'conversion_type = :conversion_type';
            $params['conversion_type'] = $filters['conversion_type'];
        }

        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_conversions,
                SUM(conversion_value) as total_value,
                AVG(conversion_value) as avg_value,
                AVG(time_to_conversion) as avg_time_to_conversion,
                AVG(steps_to_conversion) as avg_steps_to_conversion
            FROM wgs_analytics_conversions
            {$whereClause}
        ");

        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
