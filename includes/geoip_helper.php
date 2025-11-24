<?php
/**
 * GeoIP Helper - Geolokace z IP adresy přes Geoapify API
 *
 * Používá Geoapify IP Geolocation API pro získání:
 * - country_code (CZ, SK, IT, ...)
 * - city (Praha, Brno, ...)
 * - lat, lng (GPS souřadnice)
 *
 * @version 1.0.0
 * @date 2025-11-24
 */

class GeoIPHelper
{
    private static $cache = [];
    private static $cacheFile = null;
    private static $cacheLoaded = false;

    /**
     * Získat geolokaci z IP adresy
     *
     * @param string $ip IP adresa
     * @return array|null ['country_code', 'city', 'lat', 'lng'] nebo null při chybě
     */
    public static function ziskejLokaci(string $ip): ?array
    {
        // Validace IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Ignorovat localhost a privátní IP
        if (self::jePrivatniIP($ip)) {
            return null;
        }

        // Zkusit cache
        $cached = self::zCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Zavolat Geoapify API
        $result = self::zavolejGeoapify($ip);

        if ($result !== null) {
            // Uložit do cache
            self::doCache($ip, $result);
        }

        return $result;
    }

    /**
     * Zavolat Geoapify IP Geolocation API
     */
    private static function zavolejGeoapify(string $ip): ?array
    {
        $apiKey = defined('GEOAPIFY_KEY') ? GEOAPIFY_KEY : null;

        if (empty($apiKey) || $apiKey === 'change-this-in-production') {
            error_log('[GeoIP] Geoapify API klíč není nastaven');
            return null;
        }

        $url = sprintf(
            'https://api.geoapify.com/v1/ipinfo?ip=%s&apiKey=%s',
            urlencode($ip),
            urlencode($apiKey)
        );

        // Nastavit timeout pro API call
        $context = stream_context_create([
            'http' => [
                'timeout' => 3, // 3 sekundy timeout
                'ignore_errors' => true
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log('[GeoIP] Geoapify API nedostupné pro IP: ' . $ip);
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['country'])) {
                error_log('[GeoIP] Neplatná odpověď z Geoapify pro IP: ' . $ip);
                return null;
            }

            return [
                'country_code' => $data['country']['iso_code'] ?? null,
                'country_name' => $data['country']['name'] ?? null,
                'city' => $data['city']['name'] ?? null,
                'lat' => $data['location']['latitude'] ?? null,
                'lng' => $data['location']['longitude'] ?? null,
                'region' => $data['state']['name'] ?? null,
                'isp' => $data['datasource'][0]['name'] ?? null
            ];

        } catch (Exception $e) {
            error_log('[GeoIP] Chyba při volání Geoapify: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Zkontrolovat, jestli je IP privátní (localhost, LAN)
     */
    private static function jePrivatniIP(string $ip): bool
    {
        // Localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Privátní rozsahy
        $privatniRozsahy = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '169.254.0.0/16', // Link-local
            'fc00::/7',       // IPv6 private
            'fe80::/10'       // IPv6 link-local
        ];

        foreach ($privatniRozsahy as $rozsah) {
            if (self::ipVRozsahu($ip, $rozsah)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Zkontrolovat, jestli IP je v CIDR rozsahu
     */
    private static function ipVRozsahu(string $ip, string $cidr): bool
    {
        // IPv6 kontrola (zjednodušená)
        if (strpos($cidr, ':') !== false) {
            return strpos($ip, substr($cidr, 0, strpos($cidr, '/'))) === 0;
        }

        list($rozsah, $maska) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $rozsahLong = ip2long($rozsah);

        if ($ipLong === false || $rozsahLong === false) {
            return false;
        }

        $maskaLong = ~((1 << (32 - (int)$maska)) - 1);

        return ($ipLong & $maskaLong) === ($rozsahLong & $maskaLong);
    }

    /**
     * Získat z file cache
     */
    private static function zCache(string $ip): ?array
    {
        self::nactiCache();

        if (isset(self::$cache[$ip])) {
            $entry = self::$cache[$ip];

            // Cache platnost 24 hodin
            if (time() - $entry['timestamp'] < 86400) {
                return $entry['data'];
            }
        }

        return null;
    }

    /**
     * Uložit do file cache
     */
    private static function doCache(string $ip, array $data): void
    {
        self::nactiCache();

        self::$cache[$ip] = [
            'timestamp' => time(),
            'data' => $data
        ];

        // Vyčistit staré záznamy (starší než 24h)
        $now = time();
        self::$cache = array_filter(self::$cache, function ($entry) use ($now) {
            return ($now - $entry['timestamp']) < 86400;
        });

        // Omezit velikost cache na 10000 záznamů
        if (count(self::$cache) > 10000) {
            // Odstranit nejstarší záznamy
            uasort(self::$cache, function ($a, $b) {
                return $a['timestamp'] <=> $b['timestamp'];
            });
            self::$cache = array_slice(self::$cache, -10000, null, true);
        }

        self::ulozCache();
    }

    /**
     * Načíst cache ze souboru
     */
    private static function nactiCache(): void
    {
        if (self::$cacheLoaded) {
            return;
        }

        self::$cacheFile = (defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir()) . '/geoip_cache.json';
        self::$cacheLoaded = true;

        if (file_exists(self::$cacheFile)) {
            $content = @file_get_contents(self::$cacheFile);
            if ($content) {
                self::$cache = json_decode($content, true) ?: [];
            }
        }
    }

    /**
     * Uložit cache do souboru
     */
    private static function ulozCache(): void
    {
        if (self::$cacheFile) {
            @file_put_contents(
                self::$cacheFile,
                json_encode(self::$cache, JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        }
    }

    /**
     * Získat IP adresu klienta (s podporou proxy)
     */
    public static function ziskejKlientIP(): string
    {
        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // X-Forwarded-For (proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // X-Real-IP (nginx proxy)
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        // Standardní REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/**
 * Pomocná funkce pro rychlé získání geolokace
 */
if (!function_exists('ziskejGeoIP')) {
    function ziskejGeoIP(?string $ip = null): ?array
    {
        $ip = $ip ?? GeoIPHelper::ziskejKlientIP();
        return GeoIPHelper::ziskejLokaci($ip);
    }
}
