<?php
/**
 * GeolocationService - Služba pro získávání geolokačních dat z IP adres
 *
 * Tato třída poskytuje cache-first přístup k geolokačním datům:
 * 1. Kontrola cache (TTL 3 dny)
 * 2. Pokud není v cache, dotaz na externí API:
 *    - Primární: ipapi.co (1500 requests/day free)
 *    - Fallback: ip-api.com (45 requests/min free)
 * 3. Uložení do cache s expirací 3 dny
 *
 * Funkce:
 * - Cache-first strategie (očekávaný hit ratio 90-95%)
 * - Automatický fallback při selhání primárního API
 * - IP anonymizace pro GDPR compliance
 * - VPN/Datacenter detection
 * - Automatické čištění vypršené cache
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #4 - Geolocation Service
 */

class GeolocationService
{
    /**
     * @var PDO Database connection
     */
    private $pdo;

    /**
     * @var int Cache TTL v sekundách (3 dny)
     */
    private const CACHE_TTL_SECONDS = 259200; // 3 * 24 * 60 * 60

    /**
     * @var string Primární API endpoint
     */
    private const API_PRIMARY = 'https://ipapi.co';

    /**
     * @var string Fallback API endpoint
     */
    private const API_FALLBACK = 'http://ip-api.com/json';

    /**
     * Konstruktor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Získá geolokační data pro IP adresu
     *
     * Cache-first strategie:
     * 1. Zkontroluje cache
     * 2. Pokud není v cache nebo je expirovaná, zavolá API
     * 3. Uloží do cache
     *
     * @param string $ipAddress IP adresa (IPv4 nebo IPv6)
     * @return array Geolokační data
     */
    public function getLocationFromIP(string $ipAddress): array
    {
        // Validace IP adresy
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $this->getDefaultLocationData($ipAddress, 'invalid_ip');
        }

        // Anonymizace IP pro cache lookup (GDPR)
        $ipAnonymni = $this->anonymizujIP($ipAddress);

        // KROK 1: Kontrola cache
        $cachedData = $this->getCachedLocation($ipAnonymni);

        if ($cachedData !== null) {
            // Cache hit - aktualizovat last_accessed a vrátit data
            $this->aktualizujPosledniPristup($ipAnonymni);

            if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                error_log("[GeolocationService] Cache HIT pro IP: {$ipAnonymni}");
            }

            return $cachedData;
        }

        // KROK 2: Cache miss - zavolat externí API
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            error_log("[GeolocationService] Cache MISS pro IP: {$ipAnonymni}, volám API...");
        }

        // Pokus o primární API (ipapi.co)
        $apiData = $this->fetchFromIpApi($ipAddress);

        // Fallback na sekundární API (ip-api.com)
        if ($apiData === null) {
            error_log("[GeolocationService] Primární API selhalo, zkouším fallback API...");
            $apiData = $this->fetchFromIpApiCom($ipAddress);
        }

        // Pokud obě API selhala, vrátit default data
        if ($apiData === null) {
            error_log("[GeolocationService] Všechna API selhala pro IP: {$ipAnonymni}");
            return $this->getDefaultLocationData($ipAnonymni, 'api_failure');
        }

        // KROK 3: Uložit do cache
        $this->storeInCache($ipAnonymni, $apiData, $apiData['api_source']);

        return $apiData;
    }

    /**
     * Získá geolokační data z cache (pokud existují a nejsou expirovaná)
     *
     * @param string $ipAddress Anonymizovaná IP adresa
     * @return array|null Data z cache nebo NULL pokud není v cache
     */
    private function getCachedLocation(string $ipAddress): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    ip_address,
                    country_code,
                    country_name,
                    city,
                    region,
                    latitude,
                    longitude,
                    timezone,
                    isp,
                    is_vpn,
                    is_datacenter,
                    api_source,
                    cached_at,
                    expires_at
                FROM wgs_analytics_geolocation_cache
                WHERE ip_address = :ip
                  AND expires_at > NOW()
                LIMIT 1
            ");

            $stmt->execute(['ip' => $ipAddress]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null; // Není v cache nebo je expirovaná
            }

            // Převést na očekávaný formát
            return [
                'country_code' => $row['country_code'],
                'country_name' => $row['country_name'],
                'city' => $row['city'],
                'region' => $row['region'],
                'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
                'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
                'timezone' => $row['timezone'],
                'isp' => $row['isp'],
                'is_vpn' => (bool)$row['is_vpn'],
                'is_datacenter' => (bool)$row['is_datacenter'],
                'api_source' => $row['api_source'],
                'from_cache' => true
            ];

        } catch (PDOException $e) {
            error_log("[GeolocationService] Cache read error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Zavolá primární API (ipapi.co) pro získání geolokačních dat
     *
     * API dokumentace: https://ipapi.co/api/
     * Rate limit: 1500 requests/day (free tier)
     *
     * @param string $ipAddress IP adresa
     * @return array|null Data z API nebo NULL při chybě
     */
    private function fetchFromIpApi(string $ipAddress): ?array
    {
        try {
            $url = self::API_PRIMARY . "/{$ipAddress}/json/";

            // HTTP request s timeoutem 5 sekund
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'user_agent' => 'WGS-Analytics/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log("[GeolocationService] ipapi.co API request failed");
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || isset($data['error'])) {
                error_log("[GeolocationService] ipapi.co API error: " . ($data['reason'] ?? 'unknown'));
                return null;
            }

            // Mapování odpovědi API na náš formát
            return [
                'country_code' => $data['country_code'] ?? null,
                'country_name' => $data['country_name'] ?? null,
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'latitude' => isset($data['latitude']) ? (float)$data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float)$data['longitude'] : null,
                'timezone' => $data['timezone'] ?? null,
                'isp' => $data['org'] ?? null, // ipapi.co používá 'org' pro ISP
                'is_vpn' => false, // ipapi.co free tier neposkytuje VPN detection
                'is_datacenter' => false,
                'api_source' => 'ipapi',
                'from_cache' => false
            ];

        } catch (Exception $e) {
            error_log("[GeolocationService] ipapi.co exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Zavolá fallback API (ip-api.com) pro získání geolokačních dat
     *
     * API dokumentace: https://ip-api.com/docs/api:json
     * Rate limit: 45 requests/minute (free tier)
     *
     * @param string $ipAddress IP adresa
     * @return array|null Data z API nebo NULL při chybě
     */
    private function fetchFromIpApiCom(string $ipAddress): ?array
    {
        try {
            // ip-api.com umožňuje specifikovat pole v query parametru
            $fields = 'status,country,countryCode,region,city,lat,lon,timezone,isp,proxy,hosting';
            $url = self::API_FALLBACK . "/{$ipAddress}?fields={$fields}";

            // HTTP request s timeoutem 5 sekund
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'user_agent' => 'WGS-Analytics/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log("[GeolocationService] ip-api.com API request failed");
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || $data['status'] !== 'success') {
                error_log("[GeolocationService] ip-api.com API error: " . ($data['message'] ?? 'unknown'));
                return null;
            }

            // Mapování odpovědi API na náš formát
            return [
                'country_code' => $data['countryCode'] ?? null,
                'country_name' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'latitude' => isset($data['lat']) ? (float)$data['lat'] : null,
                'longitude' => isset($data['lon']) ? (float)$data['lon'] : null,
                'timezone' => $data['timezone'] ?? null,
                'isp' => $data['isp'] ?? null,
                'is_vpn' => isset($data['proxy']) ? (bool)$data['proxy'] : false,
                'is_datacenter' => isset($data['hosting']) ? (bool)$data['hosting'] : false,
                'api_source' => 'ip-api',
                'from_cache' => false
            ];

        } catch (Exception $e) {
            error_log("[GeolocationService] ip-api.com exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Uloží geolokační data do cache
     *
     * @param string $ipAddress Anonymizovaná IP adresa
     * @param array $data Geolokační data
     * @param string $apiSource Zdroj dat ('ipapi', 'ip-api', 'default')
     * @return bool TRUE při úspěchu, FALSE při chybě
     */
    private function storeInCache(string $ipAddress, array $data, string $apiSource): bool
    {
        try {
            // Vypočítat expires_at (now + 3 dny)
            $expiresAt = date('Y-m-d H:i:s', time() + self::CACHE_TTL_SECONDS);

            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_analytics_geolocation_cache (
                    ip_address,
                    country_code,
                    country_name,
                    city,
                    region,
                    latitude,
                    longitude,
                    timezone,
                    isp,
                    is_vpn,
                    is_datacenter,
                    api_source,
                    cached_at,
                    expires_at,
                    last_accessed
                ) VALUES (
                    :ip,
                    :country_code,
                    :country_name,
                    :city,
                    :region,
                    :latitude,
                    :longitude,
                    :timezone,
                    :isp,
                    :is_vpn,
                    :is_datacenter,
                    :api_source,
                    NOW(),
                    :expires_at,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    country_code = VALUES(country_code),
                    country_name = VALUES(country_name),
                    city = VALUES(city),
                    region = VALUES(region),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    timezone = VALUES(timezone),
                    isp = VALUES(isp),
                    is_vpn = VALUES(is_vpn),
                    is_datacenter = VALUES(is_datacenter),
                    api_source = VALUES(api_source),
                    cached_at = NOW(),
                    expires_at = VALUES(expires_at),
                    last_accessed = NOW()
            ");

            $stmt->execute([
                'ip' => $ipAddress,
                'country_code' => $data['country_code'],
                'country_name' => $data['country_name'],
                'city' => $data['city'],
                'region' => $data['region'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'timezone' => $data['timezone'],
                'isp' => $data['isp'],
                'is_vpn' => $data['is_vpn'] ? 1 : 0,
                'is_datacenter' => $data['is_datacenter'] ? 1 : 0,
                'api_source' => $apiSource,
                'expires_at' => $expiresAt
            ]);

            if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
                error_log("[GeolocationService] Uloženo do cache: IP={$ipAddress}, Source={$apiSource}, Expires={$expiresAt}");
            }

            return true;

        } catch (PDOException $e) {
            error_log("[GeolocationService] Cache store error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aktualizuje last_accessed timestamp pro cache záznam
     *
     * @param string $ipAddress Anonymizovaná IP adresa
     * @return bool TRUE při úspěchu, FALSE při chybě
     */
    private function aktualizujPosledniPristup(string $ipAddress): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE wgs_analytics_geolocation_cache
                SET last_accessed = NOW()
                WHERE ip_address = :ip
                LIMIT 1
            ");

            $stmt->execute(['ip' => $ipAddress]);
            return true;

        } catch (PDOException $e) {
            error_log("[GeolocationService] Last access update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Anonymizuje IP adresu pro GDPR compliance
     *
     * IPv4: Maskuje poslední oktet (192.168.1.100 → 192.168.1.0)
     * IPv6: Maskuje posledních 80 bitů (2001:db8::1234 → 2001:db8::)
     *
     * @param string $ipAddress IP adresa
     * @return string Anonymizovaná IP adresa
     */
    private function anonymizujIP(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4 - maskovat poslední oktet
            $parts = explode('.', $ipAddress);
            $parts[3] = '0';
            return implode('.', $parts);

        } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 - maskovat posledních 80 bitů
            // Jednoduchá implementace: ořezat za prvním dvojtečkovým blokem
            $colonPos = strpos($ipAddress, ':');
            if ($colonPos !== false) {
                return substr($ipAddress, 0, min(19, strlen($ipAddress))) . '::';
            }
            return $ipAddress . '::';

        } else {
            // Neplatná IP - vrátit nezměněnou
            return $ipAddress;
        }
    }

    /**
     * Vrací výchozí geolokační data (při chybě nebo neplatné IP)
     *
     * @param string $ipAddress IP adresa
     * @param string $reason Důvod použití default dat
     * @return array Výchozí geolokační data
     */
    private function getDefaultLocationData(string $ipAddress, string $reason): array
    {
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            error_log("[GeolocationService] Používám default data pro IP={$ipAddress}, Reason={$reason}");
        }

        return [
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'region' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
            'isp' => null,
            'is_vpn' => false,
            'is_datacenter' => false,
            'api_source' => 'default',
            'from_cache' => false,
            'error_reason' => $reason
        ];
    }

    /**
     * Vyčistí vypršenou cache (pro použití v cron jobu)
     *
     * @return int Počet smazaných záznamů
     */
    public function cleanExpiredCache(): int
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM wgs_analytics_geolocation_cache
                WHERE expires_at < NOW()
            ");

            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            if ($deletedCount > 0) {
                error_log("[GeolocationService] Vyčištěno {$deletedCount} vypršených cache záznamů");
            }

            return $deletedCount;

        } catch (PDOException $e) {
            error_log("[GeolocationService] Cache cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Vrací statistiky cache (pro admin dashboard)
     *
     * @return array Statistiky cache
     */
    public function getCacheStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as total_cached,
                    SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active_cache,
                    SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_cache,
                    SUM(CASE WHEN api_source = 'ipapi' THEN 1 ELSE 0 END) as from_ipapi,
                    SUM(CASE WHEN api_source = 'ip-api' THEN 1 ELSE 0 END) as from_ipapi_com,
                    SUM(CASE WHEN api_source = 'default' THEN 1 ELSE 0 END) as from_default,
                    SUM(CASE WHEN is_vpn = 1 THEN 1 ELSE 0 END) as vpn_count,
                    SUM(CASE WHEN is_datacenter = 1 THEN 1 ELSE 0 END) as datacenter_count
                FROM wgs_analytics_geolocation_cache
            ");

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'celkem_cache' => (int)$stats['total_cached'],
                'aktivni_cache' => (int)$stats['active_cache'],
                'expirovana_cache' => (int)$stats['expired_cache'],
                'zdroj_ipapi' => (int)$stats['from_ipapi'],
                'zdroj_ipapi_com' => (int)$stats['from_ipapi_com'],
                'zdroj_default' => (int)$stats['from_default'],
                'vpn_detekce' => (int)$stats['vpn_count'],
                'datacenter_detekce' => (int)$stats['datacenter_count']
            ];

        } catch (PDOException $e) {
            error_log("[GeolocationService] Stats error: " . $e->getMessage());
            return [];
        }
    }
}
?>
