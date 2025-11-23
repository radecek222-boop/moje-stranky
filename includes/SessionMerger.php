<?php
/**
 * SessionMerger - Správa pokročilého sledování relací
 *
 * Tato třída řídí životní cyklus relací, propojuje relace s fingerprints,
 * počítá engagement score a umožňuje cross-session stitching.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #2 - Advanced Session Tracking
 */

class SessionMerger
{
    /**
     * @var PDO Instance PDO připojení k databázi
     */
    private $pdo;

    /**
     * Konstruktor
     *
     * @param PDO $pdo Instance PDO připojení
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vytvoří nebo aktualizuje záznam relace
     *
     * @param string $sessionId - Session ID z localStorage
     * @param string $fingerprintId - Device fingerprint ID
     * @param array $sessionData - Data relace (entry_page, utm_*, device_type, atd.)
     * @return array - ['session_id', 'is_new', 'pageview_count']
     * @throws PDOException
     */
    public function vytvorNeboAktualizujRelaci(string $sessionId, string $fingerprintId, array $sessionData): array
    {
        // Validace vstupních dat
        if (empty($sessionId) || strlen($sessionId) > 64) {
            throw new InvalidArgumentException('Neplatný session_id');
        }

        if (empty($fingerprintId) || strlen($fingerprintId) > 64) {
            throw new InvalidArgumentException('Neplatný fingerprint_id');
        }

        // Kontrola existence relace
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                session_id,
                pageview_count,
                is_active
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");
        $stmt->execute(['session_id' => $sessionId]);
        $existujiciRelace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existujiciRelace) {
            // Aktualizace existující relace
            $novyPocetPageviews = $existujiciRelace['pageview_count'] + 1;

            $stmt = $this->pdo->prepare("
                UPDATE wgs_analytics_sessions
                SET
                    session_end = NOW(),
                    pageview_count = :pageview_count,
                    exit_page = :exit_page,
                    is_active = 1,
                    updated_at = NOW()
                WHERE session_id = :session_id
            ");

            $stmt->execute([
                'pageview_count' => $novyPocetPageviews,
                'exit_page' => $sessionData['page_url'] ?? null,
                'session_id' => $sessionId
            ]);

            return [
                'session_id' => $sessionId,
                'is_new' => false,
                'pageview_count' => $novyPocetPageviews
            ];

        } else {
            // Vytvoření nové relace
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_analytics_sessions (
                    session_id,
                    fingerprint_id,
                    session_start,
                    session_end,
                    is_active,
                    entry_page,
                    exit_page,
                    pageview_count,
                    utm_source,
                    utm_medium,
                    utm_campaign,
                    utm_term,
                    utm_content,
                    referrer,
                    referrer_domain,
                    device_type,
                    browser,
                    os,
                    created_at,
                    updated_at
                ) VALUES (
                    :session_id,
                    :fingerprint_id,
                    NOW(),
                    NOW(),
                    1,
                    :entry_page,
                    :exit_page,
                    1,
                    :utm_source,
                    :utm_medium,
                    :utm_campaign,
                    :utm_term,
                    :utm_content,
                    :referrer,
                    :referrer_domain,
                    :device_type,
                    :browser,
                    :os,
                    NOW(),
                    NOW()
                )
            ");

            // Extrakce domény z referreru
            $referrerDomain = null;
            if (!empty($sessionData['referrer'])) {
                $parsedUrl = parse_url($sessionData['referrer']);
                $referrerDomain = $parsedUrl['host'] ?? null;
            }

            $stmt->execute([
                'session_id' => $sessionId,
                'fingerprint_id' => $fingerprintId,
                'entry_page' => $sessionData['page_url'] ?? '',
                'exit_page' => $sessionData['page_url'] ?? null,
                'utm_source' => $sessionData['utm_source'] ?? null,
                'utm_medium' => $sessionData['utm_medium'] ?? null,
                'utm_campaign' => $sessionData['utm_campaign'] ?? null,
                'utm_term' => $sessionData['utm_term'] ?? null,
                'utm_content' => $sessionData['utm_content'] ?? null,
                'referrer' => $sessionData['referrer'] ?? null,
                'referrer_domain' => $referrerDomain,
                'device_type' => $sessionData['device_type'] ?? null,
                'browser' => $sessionData['browser'] ?? null,
                'os' => $sessionData['os'] ?? null
            ]);

            return [
                'session_id' => $sessionId,
                'is_new' => true,
                'pageview_count' => 1
            ];
        }
    }

    /**
     * Aktualizuje aktivitu relace (voláno při každém pageview)
     *
     * @param string $sessionId
     * @param string $aktualniStranka - URL aktuální stránky
     * @return bool
     */
    public function aktualizujAktivituRelace(string $sessionId, string $aktualniStranka): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET
                session_end = NOW(),
                exit_page = :exit_page,
                pageview_count = pageview_count + 1,
                is_active = 1,
                updated_at = NOW()
            WHERE session_id = :session_id
        ");

        return $stmt->execute([
            'exit_page' => $aktualniStranka,
            'session_id' => $sessionId
        ]);
    }

    /**
     * Vypočítá engagement score na základě metrik relace
     *
     * Vzorec: vážený součet:
     * - Doba trvání (0-30 bodů)
     * - Počet pageviews (0-25 bodů)
     * - Scroll depth (0-20 bodů)
     * - Počet kliknutí (0-15 bodů)
     * - Čas na webu (0-10 bodů)
     *
     * @param string $sessionId
     * @return float - Skóre 0-100
     */
    public function vypocitejEngagementScore(string $sessionId): float
    {
        // Načtení dat relace
        $stmt = $this->pdo->prepare("
            SELECT
                TIMESTAMPDIFF(SECOND, session_start, session_end) as session_duration_sec,
                pageview_count,
                total_scroll_depth,
                total_click_count,
                total_time_on_site
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return 0.0;
        }

        // Výpočet dílčích bodů
        $durationPoints = 0;
        $pageviewPoints = 0;
        $scrollPoints = 0;
        $clickPoints = 0;
        $timeOnSitePoints = 0;

        // 1. Body za dobu trvání (max 30 bodů při 600s = 10min)
        if ($data['session_duration_sec'] !== null) {
            $durationPoints = min(30, $data['session_duration_sec'] / 20);
        }

        // 2. Body za pageviews (max 25 bodů při 5 pageviews)
        if ($data['pageview_count']) {
            $pageviewPoints = min(25, $data['pageview_count'] * 5);
        }

        // 3. Body za scroll depth (max 20 bodů při 100% scroll)
        if ($data['total_scroll_depth'] !== null && $data['pageview_count'] > 0) {
            $avgScrollDepth = $data['total_scroll_depth'] / $data['pageview_count'];
            $scrollPoints = min(20, $avgScrollDepth / 5);
        }

        // 4. Body za kliky (max 15 bodů při 5 kliknutích)
        if ($data['total_click_count']) {
            $clickPoints = min(15, $data['total_click_count'] * 3);
        }

        // 5. Body za čas na webu (max 10 bodů při 600s)
        if ($data['total_time_on_site'] !== null) {
            $timeOnSitePoints = min(10, $data['total_time_on_site'] / 60);
        }

        // Celkové skóre (max 100)
        $totalScore = $durationPoints + $pageviewPoints + $scrollPoints + $clickPoints + $timeOnSitePoints;
        $finalScore = min(100, round($totalScore, 2));

        // Uložení skóre do databáze
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET engagement_score = :score
            WHERE session_id = :session_id
        ");

        $stmt->execute([
            'score' => $finalScore,
            'session_id' => $sessionId
        ]);

        return $finalScore;
    }

    /**
     * Označí relaci jako neaktivní (voláno cronem nebo po timeoutu)
     *
     * @param string $sessionId
     * @return bool
     */
    public function uzavriRelaci(string $sessionId): bool
    {
        // Vypočítat dobu trvání
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET
                is_active = 0,
                session_duration = TIMESTAMPDIFF(SECOND, session_start, session_end),
                updated_at = NOW()
            WHERE session_id = :session_id
        ");

        $result = $stmt->execute(['session_id' => $sessionId]);

        // Po uzavření vypočítat engagement score
        if ($result) {
            $this->vypocitejEngagementScore($sessionId);
        }

        return $result;
    }

    /**
     * Získá všechny relace pro daný fingerprint (cross-session stitching)
     *
     * @param string $fingerprintId
     * @param int $limit - Max počet relací k vrácení
     * @return array - Pole záznamů relací
     */
    public function nactiRelacePodleFingerprintu(string $fingerprintId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                session_id,
                fingerprint_id,
                session_start,
                session_end,
                session_duration,
                entry_page,
                exit_page,
                pageview_count,
                engagement_score,
                utm_source,
                utm_medium,
                utm_campaign,
                device_type,
                country,
                city,
                is_bot,
                has_conversion,
                created_at
            FROM wgs_analytics_sessions
            WHERE fingerprint_id = :fingerprint_id
            ORDER BY session_start DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':fingerprint_id', $fingerprintId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Získá detaily relace podle session_id
     *
     * @param string $sessionId
     * @return array|null
     */
    public function nactiRelaci(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                session_id,
                fingerprint_id,
                session_start,
                session_end,
                session_duration,
                is_active,
                entry_page,
                exit_page,
                pageview_count,
                engagement_score,
                total_scroll_depth,
                total_click_count,
                total_time_on_site,
                utm_source,
                utm_medium,
                utm_campaign,
                utm_term,
                utm_content,
                referrer,
                referrer_domain,
                device_type,
                browser,
                os,
                country,
                city,
                is_bot,
                bot_score,
                has_conversion,
                conversion_type,
                conversion_value,
                created_at,
                updated_at
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Sloučí UTM parametry (first-touch attribution)
     *
     * Aktualizuje pouze pokud relace ještě nemá UTM parametry.
     *
     * @param string $sessionId
     * @param array $utmParams
     * @return bool
     */
    public function slucUtmParametry(string $sessionId, array $utmParams): bool
    {
        // Načíst aktuální UTM parametry
        $stmt = $this->pdo->prepare("
            SELECT utm_source
            FROM wgs_analytics_sessions
            WHERE session_id = :session_id
            LIMIT 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $existujici = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pokud už má UTM parametry, neměnit (first-touch attribution)
        if ($existujici && !empty($existujici['utm_source'])) {
            return false;
        }

        // Aktualizovat UTM parametry
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET
                utm_source = :utm_source,
                utm_medium = :utm_medium,
                utm_campaign = :utm_campaign,
                utm_term = :utm_term,
                utm_content = :utm_content,
                updated_at = NOW()
            WHERE session_id = :session_id
        ");

        return $stmt->execute([
            'utm_source' => $utmParams['utm_source'] ?? null,
            'utm_medium' => $utmParams['utm_medium'] ?? null,
            'utm_campaign' => $utmParams['utm_campaign'] ?? null,
            'utm_term' => $utmParams['utm_term'] ?? null,
            'utm_content' => $utmParams['utm_content'] ?? null,
            'session_id' => $sessionId
        ]);
    }

    /**
     * Automaticky uzavře neaktivní relace (pomocná funkce pro cron)
     *
     * Relace neaktivní 30+ minut se označí jako uzavřené.
     *
     * @return int - Počet uzavřených relací
     */
    public function autoUzavreniNeaktivnichRelaci(): int
    {
        // Najít aktivní relace starší než 30 minut
        $stmt = $this->pdo->prepare("
            SELECT session_id
            FROM wgs_analytics_sessions
            WHERE is_active = 1
              AND session_end < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");

        $stmt->execute();
        $neaktivniRelace = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pocetUzavrenych = 0;

        foreach ($neaktivniRelace as $sessionId) {
            if ($this->uzavriRelaci($sessionId)) {
                $pocetUzavrenych++;
            }
        }

        return $pocetUzavrenych;
    }

    /**
     * Aktualizuje metriky relace (scroll depth, click count, time on site)
     *
     * Volá se z event trackingu (Modul #5)
     *
     * @param string $sessionId
     * @param array $metriky - ['scroll_depth' => int, 'click_count' => int, 'time_on_site' => int]
     * @return bool
     */
    public function aktualizujMetrikyRelace(string $sessionId, array $metriky): bool
    {
        $updateParts = [];
        $params = ['session_id' => $sessionId];

        if (isset($metriky['scroll_depth'])) {
            $updateParts[] = "total_scroll_depth = COALESCE(total_scroll_depth, 0) + :scroll_depth";
            $params['scroll_depth'] = $metriky['scroll_depth'];
        }

        if (isset($metriky['click_count'])) {
            $updateParts[] = "total_click_count = COALESCE(total_click_count, 0) + :click_count";
            $params['click_count'] = $metriky['click_count'];
        }

        if (isset($metriky['time_on_site'])) {
            $updateParts[] = "total_time_on_site = :time_on_site";
            $params['time_on_site'] = $metriky['time_on_site'];
        }

        if (empty($updateParts)) {
            return false;
        }

        $sql = "UPDATE wgs_analytics_sessions SET " . implode(', ', $updateParts) . " WHERE session_id = :session_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Získá statistiky relací pro daný fingerprint
     *
     * @param string $fingerprintId
     * @return array - ['total_sessions', 'total_pageviews', 'avg_engagement', 'last_visit']
     */
    public function statistikyFingerprintu(string $fingerprintId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_sessions,
                SUM(pageview_count) as total_pageviews,
                AVG(engagement_score) as avg_engagement,
                MAX(session_start) as last_visit
            FROM wgs_analytics_sessions
            WHERE fingerprint_id = :fingerprint_id
        ");

        $stmt->execute(['fingerprint_id' => $fingerprintId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_sessions' => (int)($result['total_sessions'] ?? 0),
            'total_pageviews' => (int)($result['total_pageviews'] ?? 0),
            'avg_engagement' => round((float)($result['avg_engagement'] ?? 0), 2),
            'last_visit' => $result['last_visit'] ?? null
        ];
    }

    /**
     * Aktualizuje geolokaci relace (voláno z Modulu #4)
     *
     * @param string $sessionId
     * @param string $country
     * @param string|null $city
     * @return bool
     */
    public function aktualizujGeolokaci(string $sessionId, string $country, ?string $city = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET
                country = :country,
                city = :city,
                updated_at = NOW()
            WHERE session_id = :session_id
        ");

        return $stmt->execute([
            'country' => $country,
            'city' => $city,
            'session_id' => $sessionId
        ]);
    }

    /**
     * Označí relaci jako bot (voláno z Modulu #3)
     *
     * @param string $sessionId
     * @param float $botScore - Skóre 0-100
     * @return bool
     */
    public function oznacJakoBot(string $sessionId, float $botScore): bool
    {
        $isBot = $botScore >= 70 ? 1 : 0;

        $stmt = $this->pdo->prepare("
            UPDATE wgs_analytics_sessions
            SET
                is_bot = :is_bot,
                bot_score = :bot_score,
                updated_at = NOW()
            WHERE session_id = :session_id
        ");

        return $stmt->execute([
            'is_bot' => $isBot,
            'bot_score' => $botScore,
            'session_id' => $sessionId
        ]);
    }
}
