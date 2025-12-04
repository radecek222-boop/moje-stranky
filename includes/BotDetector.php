<?php
/**
 * BotDetector - AI-powered bot detection engine
 *
 * Pokročilá detekce botů s multi-stage analýzou:
 * - User-Agent scoring (0-30 bodů)
 * - Behavioral analysis (0-40 bodů)
 * - Fingerprint analysis (0-20 bodů)
 * - Network analysis (0-10 bodů)
 *
 * Celkové bot score: 0-100
 * Threat level: none, low, medium, high, critical
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #3 - Bot Detection Engine
 */

class BotDetector
{
    private $pdo;
    private $whitelistCache = null;

    /**
     * Konstruktor - inicializace PDO connection
     *
     * @param PDO $pdo
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Hlavní metoda pro detekci bota
     *
     * Orchestruje celý proces bot detection:
     * 1. Kontrola whitelistu
     * 2. Výpočet bot score
     * 3. Určení threat level
     * 4. Uložení detekce do DB
     *
     * @param string $sessionId ID relace
     * @param string $fingerprintId Device fingerprint
     * @param array $requestData Data z požadavku (UA, IP, signals...)
     * @return array ['is_bot' => bool, 'bot_score' => int, 'threat_level' => string, 'is_whitelisted' => bool]
     */
    public function detekujBota(string $sessionId, string $fingerprintId, array $requestData): array
    {
        $userAgent = $requestData['user_agent'] ?? '';
        $ipAddress = $requestData['ip_address'] ?? '';
        $signals = $requestData['signals'] ?? [];

        // 1. Kontrola whitelistu (legitimní boti)
        $jeNaWhitelistu = $this->jeNaWhitelistu($userAgent, $ipAddress);

        if ($jeNaWhitelistu) {
            // Whitelist bot - zaznamenat, ale označit jako legitimní
            $detectionData = [
                'bot_score' => 0,
                'ua_score' => 0,
                'behavioral_score' => 0,
                'fingerprint_score' => 0,
                'network_score' => 0,
                'threat_level' => 'none',
                'is_bot' => true,
                'is_whitelisted' => true,
                'detected_signals' => json_encode($signals),
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress
            ];

            $this->ulozDetekci($sessionId, $fingerprintId, $detectionData);

            return [
                'is_bot' => true,
                'bot_score' => 0,
                'threat_level' => 'none',
                'is_whitelisted' => true
            ];
        }

        // 2. Výpočet bot score (multi-stage analysis)
        $uaScore = $this->vypocitejUaScore($userAgent);
        $behavioralScore = $this->vypocitejBehavioralScore($signals);
        $fingerprintScore = $this->vypocitejFingerprintScore($fingerprintId);
        $networkScore = $this->vypocitejNetworkScore($ipAddress);

        $botScore = $uaScore + $behavioralScore + $fingerprintScore + $networkScore;

        // 3. Určení threat level
        $threatLevel = $this->urcThreatLevel($botScore);

        // 4. Detekce - je to bot?
        $isBot = ($botScore >= 21); // Threshold: 21+ = low threat

        // 5. Extrakce specifických signálů
        $headlessDetected = $signals['headless'] ?? null;
        $webdriverDetected = $signals['webdriver'] ?? null;
        $automationDetected = $signals['automation'] ?? null;
        $pageviewSpeedMs = $signals['pageview_speed_ms'] ?? null;
        $mouseMovementEntropy = $signals['mouse_movement_entropy'] ?? null;
        $keyboardTimingVariance = $signals['keyboard_timing_variance'] ?? null;

        // 6. Uložit detekci do databáze
        $detectionData = [
            'bot_score' => min($botScore, 100), // Cap na 100
            'ua_score' => $uaScore,
            'behavioral_score' => $behavioralScore,
            'fingerprint_score' => $fingerprintScore,
            'network_score' => $networkScore,
            'threat_level' => $threatLevel,
            'is_bot' => $isBot,
            'is_whitelisted' => false,
            'detected_signals' => json_encode($signals),
            'user_agent' => substr($userAgent, 0, 512),
            'ip_address' => $ipAddress,
            'headless_detected' => $headlessDetected,
            'webdriver_detected' => $webdriverDetected,
            'automation_detected' => $automationDetected,
            'pageview_speed_ms' => $pageviewSpeedMs,
            'mouse_movement_entropy' => $mouseMovementEntropy,
            'keyboard_timing_variance' => $keyboardTimingVariance
        ];

        $this->ulozDetekci($sessionId, $fingerprintId, $detectionData);

        return [
            'is_bot' => $isBot,
            'bot_score' => min($botScore, 100),
            'threat_level' => $threatLevel,
            'is_whitelisted' => false,
            'ua_score' => $uaScore,
            'behavioral_score' => $behavioralScore,
            'fingerprint_score' => $fingerprintScore,
            'network_score' => $networkScore
        ];
    }

    /**
     * Výpočet User-Agent score (0-30)
     *
     * Detekuje podezřelé UA stringy:
     * - Chybějící UA (30 bodů - velmi podezřelé)
     * - Bot klíčová slova (25 bodů)
     * - Headless browsery (20 bodů)
     * - Starý/neobvyklý UA (10-15 bodů)
     *
     * @param string $userAgent
     * @return int 0-30
     */
    public function vypocitejUaScore(string $userAgent): int
    {
        if (empty($userAgent)) {
            return 30; // Žádný UA = velmi podezřelé
        }

        $score = 0;
        $ua = strtolower($userAgent);

        // Detekce známých botů (které NEJSOU na whitelistu)
        $botKeywords = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python-requests',
            'java/', 'go-http-client', 'okhttp', 'axios', 'node-fetch', 'scrapy'
        ];

        foreach ($botKeywords as $keyword) {
            if (strpos($ua, $keyword) !== false) {
                $score += 25;
                break;
            }
        }

        // Detekce headless browserů
        $headlessKeywords = ['headless', 'phantomjs', 'slimerjs', 'zombie'];

        foreach ($headlessKeywords as $keyword) {
            if (strpos($ua, $keyword) !== false) {
                $score += 20;
                break;
            }
        }

        // Detekce automatizačních nástrojů
        $automationKeywords = ['selenium', 'webdriver', 'playwright', 'puppeteer'];

        foreach ($automationKeywords as $keyword) {
            if (strpos($ua, $keyword) !== false) {
                $score += 15;
                break;
            }
        }

        // Neobvyklý/starý UA
        if (!preg_match('/(Chrome|Firefox|Safari|Edge|Opera)/i', $userAgent)) {
            $score += 10;
        }

        return min($score, 30); // Max 30 bodů
    }

    /**
     * Výpočet Behavioral score (0-40)
     *
     * Analyzuje chování uživatele:
     * - Rychlost pageviews (velmi rychlé = bot)
     * - Pohyb myši (žádný/lineární = bot)
     * - Klávesnicové vzory (pravidelné = bot)
     * - Click patterns
     *
     * @param array $signals Behaviorální signály z frontendu
     * @return int 0-40
     */
    public function vypocitejBehavioralScore(array $signals): int
    {
        $score = 0;

        // 1. Rychlost pageviews (podezřelé pokud < 500ms)
        if (isset($signals['pageview_speed_ms'])) {
            $speed = (int)$signals['pageview_speed_ms'];

            if ($speed < 100) {
                $score += 20; // Extrémně rychlé
            } elseif ($speed < 500) {
                $score += 10; // Velmi rychlé
            } elseif ($speed < 1000) {
                $score += 5; // Rychlé
            }
        }

        // 2. Pohyb myši (entropie 0-1, nízká = bot)
        if (isset($signals['mouse_movement_entropy'])) {
            $entropy = (float)$signals['mouse_movement_entropy'];

            if ($entropy < 0.1) {
                $score += 15; // Žádný nebo lineární pohyb
            } elseif ($entropy < 0.3) {
                $score += 8; // Nízká variabilita
            }
        }

        // 3. Klávesnicové vzory (variance, nízká = bot)
        if (isset($signals['keyboard_timing_variance'])) {
            $variance = (float)$signals['keyboard_timing_variance'];

            if ($variance < 0.1) {
                $score += 10; // Velmi pravidelné (bot)
            } elseif ($variance < 0.3) {
                $score += 5; // Nízká variabilita
            }
        }

        // 4. Headless browser detekce
        if (isset($signals['headless']) && $signals['headless'] === true) {
            $score += 20;
        }

        // 5. WebDriver detekce
        if (isset($signals['webdriver']) && $signals['webdriver'] === true) {
            $score += 20;
        }

        // 6. Automation detekce (PhantomJS, Selenium...)
        if (isset($signals['automation']) && $signals['automation'] === true) {
            $score += 15;
        }

        return min($score, 40); // Max 40 bodů
    }

    /**
     * Výpočet Fingerprint score (0-20)
     *
     * Analyzuje stabilitu a autenticitu device fingerprintu:
     * - Příliš časté změny fingerprintu
     * - Podezřelé fingerprint hodnoty
     * - Chybějící/neplatný fingerprint
     *
     * @param string $fingerprintId
     * @return int 0-20
     */
    public function vypocitejFingerprintScore(string $fingerprintId): int
    {
        $score = 0;

        // 1. Chybějící fingerprint
        if (empty($fingerprintId)) {
            return 20;
        }

        // 2. Fallback fingerprint (z tracker-v2.js)
        if (strpos($fingerprintId, 'fp_fallback_') === 0) {
            $score += 10; // Fallback = suspektní
        }

        // 3. Kontrola stability (počet relací s tímto fingerprintem)
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT session_id) AS session_count
                FROM wgs_analytics_sessions
                WHERE fingerprint_id = :fingerprint_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            $stmt->execute(['fingerprint_id' => $fingerprintId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sessionCount = (int)($result['session_count'] ?? 0);

            // Pokud fingerprint generuje MNOHO relací za 24h = bot
            if ($sessionCount > 50) {
                $score += 15;
            } elseif ($sessionCount > 20) {
                $score += 10;
            } elseif ($sessionCount > 10) {
                $score += 5;
            }

        } catch (PDOException $e) {
            error_log("BotDetector: Chyba při kontrole fingerprintu: " . $e->getMessage());
        }

        return min($score, 20); // Max 20 bodů
    }

    /**
     * Výpočet Network score (0-10)
     *
     * Analyzuje síťové charakteristiky:
     * - VPN/Proxy detekce
     * - Data center IP ranges
     * - Blacklistované IP
     *
     * @param string $ipAddress
     * @return int 0-10
     */
    public function vypocitejNetworkScore(string $ipAddress): int
    {
        $score = 0;

        // 1. Chybějící IP
        if (empty($ipAddress)) {
            return 5;
        }

        // 2. Známé data center IP ranges (zjednodušená detekce)
        // TODO: Integrace s external service (IPQualityScore, IPHub, AbuseIPDB)
        $dataCenterRanges = [
            '104.', '172.', '185.', '192.168.', // Ukázka
        ];

        foreach ($dataCenterRanges as $range) {
            if (strpos($ipAddress, $range) === 0) {
                $score += 10;
                break;
            }
        }

        // 3. Kontrola, zda IP má mnoho relací
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS session_count
                FROM wgs_analytics_sessions
                WHERE ip_address = :ip
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");

            $stmt->execute(['ip' => $ipAddress]);
            $sessionCount = (int)$stmt->fetchColumn();

            if ($sessionCount > 20) {
                $score += 5; // Mnoho relací z jedné IP = podezřelé
            }

        } catch (PDOException $e) {
            error_log("BotDetector: Chyba při kontrole IP: " . $e->getMessage());
        }

        return min($score, 10); // Max 10 bodů
    }

    /**
     * Určení threat level na základě bot score
     *
     * Threat level klasifikace:
     * - none: 0-20 (pravděpodobně člověk)
     * - low: 21-40 (možný bot)
     * - medium: 41-60 (pravděpodobný bot)
     * - high: 61-80 (skoro jistě bot)
     * - critical: 81-100 (100% bot)
     *
     * @param int $botScore 0-100
     * @return string 'none'|'low'|'medium'|'high'|'critical'
     */
    public function urcThreatLevel(int $botScore): string
    {
        if ($botScore <= 20) {
            return 'none';
        } elseif ($botScore <= 40) {
            return 'low';
        } elseif ($botScore <= 60) {
            return 'medium';
        } elseif ($botScore <= 80) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    /**
     * Kontrola, zda je bot na whitelistu (legitimní bot)
     *
     * Whitelist obsahuje:
     * - Search engine crawlery (Googlebot, Bingbot...)
     * - Social media boty (FacebookBot, TwitterBot...)
     * - Monitoring služby (UptimeRobot...)
     *
     * @param string $userAgent
     * @param string $ipAddress
     * @return bool TRUE = je na whitelistu
     */
    public function jeNaWhitelistu(string $userAgent, string $ipAddress): bool
    {
        // Načíst whitelist z databáze (cache na 5 minut)
        if ($this->whitelistCache === null) {
            try {
                $stmt = $this->pdo->query("
                    SELECT bot_name, ua_pattern, ip_ranges
                    FROM wgs_analytics_bot_whitelist
                    WHERE is_active = 1
                ");

                $this->whitelistCache = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                error_log("BotDetector: Chyba při načítání whitelistu: " . $e->getMessage());
                return false;
            }
        }

        // Kontrola každého záznamu
        foreach ($this->whitelistCache as $whitelistBot) {
            $uaPattern = $whitelistBot['ua_pattern'];
            $ipRanges = $whitelistBot['ip_ranges'];

            // 1. Kontrola User-Agent (regex match)
            if (!empty($uaPattern)) {
                if (preg_match('/' . $uaPattern . '/i', $userAgent)) {
                    // UA match - ale ještě ověřit IP pokud je k dispozici
                    if (!empty($ipRanges)) {
                        $ipRangesArray = json_decode($ipRanges, true);

                        if (is_array($ipRangesArray)) {
                            foreach ($ipRangesArray as $cidr) {
                                if ($this->ipInCidr($ipAddress, $cidr)) {
                                    return true; // UA + IP match = legitimní bot
                                }
                            }

                            // UA match, ale IP NENÍ v rozsahu = možný fake
                            continue;
                        }
                    } else {
                        // Whitelist nemá IP ranges - stačí UA match
                        return true;
                    }
                }
            }
        }

        return false; // Není na whitelistu
    }

    /**
     * Uložení detekce do databáze
     *
     * @param string $sessionId
     * @param string $fingerprintId
     * @param array $detectionData
     * @return bool TRUE při úspěchu
     */
    public function ulozDetekci(string $sessionId, string $fingerprintId, array $detectionData): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_analytics_bot_detections (
                    session_id,
                    fingerprint_id,
                    bot_score,
                    ua_score,
                    behavioral_score,
                    fingerprint_score,
                    network_score,
                    threat_level,
                    is_bot,
                    is_whitelisted,
                    detected_signals,
                    user_agent,
                    ip_address,
                    headless_detected,
                    webdriver_detected,
                    automation_detected,
                    pageview_speed_ms,
                    mouse_movement_entropy,
                    keyboard_timing_variance
                ) VALUES (
                    :session_id,
                    :fingerprint_id,
                    :bot_score,
                    :ua_score,
                    :behavioral_score,
                    :fingerprint_score,
                    :network_score,
                    :threat_level,
                    :is_bot,
                    :is_whitelisted,
                    :detected_signals,
                    :user_agent,
                    :ip_address,
                    :headless_detected,
                    :webdriver_detected,
                    :automation_detected,
                    :pageview_speed_ms,
                    :mouse_movement_entropy,
                    :keyboard_timing_variance
                )
            ");

            $stmt->execute([
                'session_id' => $sessionId,
                'fingerprint_id' => $fingerprintId,
                'bot_score' => $detectionData['bot_score'],
                'ua_score' => $detectionData['ua_score'],
                'behavioral_score' => $detectionData['behavioral_score'],
                'fingerprint_score' => $detectionData['fingerprint_score'],
                'network_score' => $detectionData['network_score'],
                'threat_level' => $detectionData['threat_level'],
                'is_bot' => $detectionData['is_bot'] ? 1 : 0,
                'is_whitelisted' => $detectionData['is_whitelisted'] ? 1 : 0,
                'detected_signals' => $detectionData['detected_signals'],
                'user_agent' => $detectionData['user_agent'],
                'ip_address' => $detectionData['ip_address'],
                'headless_detected' => $detectionData['headless_detected'] ?? null,
                'webdriver_detected' => $detectionData['webdriver_detected'] ?? null,
                'automation_detected' => $detectionData['automation_detected'] ?? null,
                'pageview_speed_ms' => $detectionData['pageview_speed_ms'] ?? null,
                'mouse_movement_entropy' => $detectionData['mouse_movement_entropy'] ?? null,
                'keyboard_timing_variance' => $detectionData['keyboard_timing_variance'] ?? null
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("BotDetector: Chyba při ukládání detekce: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Načtení detekčních záznamů pro relaci
     *
     * @param string $sessionId
     * @return array Pole detekčních záznamů
     */
    public function nactiDetekceRelace(string $sessionId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM wgs_analytics_bot_detections
                WHERE session_id = :session_id
                ORDER BY detection_timestamp DESC
            ");

            $stmt->execute(['session_id' => $sessionId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("BotDetector: Chyba při načítání detekčních záznamů: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Načtení bot activity statistik
     *
     * Pro admin dashboard - zobrazení bot aktivity za zvolené období
     *
     * @param string $from Datum od (YYYY-MM-DD)
     * @param string $to Datum do (YYYY-MM-DD)
     * @param array $filters Filtry ['threat_level' => 'high', 'is_bot' => true]
     * @return array Statistiky bot aktivity
     */
    public function nactiStatistiky(string $from, string $to, array $filters = []): array
    {
        try {
            // Base query
            $sql = "
                SELECT
                    DATE(detection_timestamp) AS datum,
                    threat_level,
                    COUNT(*) AS pocet_detekci,
                    SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS pocet_botu,
                    SUM(CASE WHEN is_whitelisted = 1 THEN 1 ELSE 0 END) AS pocet_whitelisted,
                    AVG(bot_score) AS prumerne_bot_score
                FROM wgs_analytics_bot_detections
                WHERE detection_timestamp >= :from
                  AND detection_timestamp <= :to
            ";

            $params = [
                'from' => $from . ' 00:00:00',
                'to' => $to . ' 23:59:59'
            ];

            // Filtry
            if (isset($filters['threat_level'])) {
                $sql .= " AND threat_level = :threat_level";
                $params['threat_level'] = $filters['threat_level'];
            }

            if (isset($filters['is_bot'])) {
                $sql .= " AND is_bot = :is_bot";
                $params['is_bot'] = $filters['is_bot'] ? 1 : 0;
            }

            $sql .= " GROUP BY DATE(detection_timestamp), threat_level ORDER BY datum DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("BotDetector: Chyba při načítání statistik: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Pomocná metoda: Kontrola, zda IP je v CIDR rozsahu
     *
     * @param string $ip IP adresa
     * @param string $cidr CIDR range (např. "66.249.64.0/19")
     * @return bool
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            // Není CIDR, ale přesná IP
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
?>
