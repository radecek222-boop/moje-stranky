<?php
/**
 * WGS Cache - Lehký cachovací systém pro snížení zátěže databáze
 *
 * Prioritní pořadí backendů:
 *   1. Redis (pokud REDIS_HOST nastaven v .env a extension dostupná)
 *   2. APCu (PHP sdílená paměť - rychlý, pokud dostupné)
 *   3. Souborový cache (temp/cache/ - vždy dostupné)
 *
 * Konfigurace Redis v .env:
 *   REDIS_HOST=127.0.0.1
 *   REDIS_PORT=6379
 *   REDIS_PASSWORD=  (volitelné)
 *   REDIS_DB=0       (volitelné, výchozí 0)
 *
 * Použití:
 *   $data = WgsCache::nacti('klic');
 *   if ($data === null) {
 *       $data = $pdo->query(...)-> fetchAll();
 *       WgsCache::uloz('klic', $data, 300); // 5 minut TTL
 *   }
 *
 *   // Invalidace (např. po uložení):
 *   WgsCache::smaz('klic');
 *   WgsCache::smazVzorem('cenik_*');
 */

class WgsCache
{
    // TTL výchozí hodnoty v sekundách
    public const TTL_KRATKY       = 60;       // 1 minuta
    public const TTL_STREDNI      = 300;      // 5 minut
    public const TTL_DLOUHY       = 3600;     // 1 hodina
    public const TTL_VELMI_DLOUHY = 86400;    // 24 hodin

    private static string  $adresarCache    = '';
    private static bool    $inicializovano  = false;
    private static bool    $apcuDostupne    = false;
    private static ?Redis  $redis           = null;

    // =============================================
    // Inicializace
    // =============================================

    private static function inicializovat(): void
    {
        if (self::$inicializovano) {
            return;
        }

        // Adresář pro souborový cache
        $zaklad = defined('TEMP_PATH') ? TEMP_PATH : dirname(__DIR__) . '/temp';
        self::$adresarCache = $zaklad . '/cache';

        if (!is_dir(self::$adresarCache)) {
            mkdir(self::$adresarCache, 0750, true);
        }

        // Zkontrolovat dostupnost APCu
        self::$apcuDostupne = extension_loaded('apcu') && apcu_enabled();

        // Zkontrolovat dostupnost Redis
        self::$redis = self::pripojitRedis();

        self::$inicializovano = true;
    }

    private static function pripojitRedis(): ?Redis
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        $host = $_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: null;
        if (!$host) {
            return null;
        }

        $port     = (int)($_ENV['REDIS_PORT']     ?? getenv('REDIS_PORT')     ?: 6379);
        $heslo    = $_ENV['REDIS_PASSWORD']        ?? getenv('REDIS_PASSWORD') ?: null;
        $databaze = (int)($_ENV['REDIS_DB']        ?? getenv('REDIS_DB')       ?: 0);

        try {
            $redis = new Redis();
            $redis->connect($host, $port, 2.0); // 2s timeout
            if ($heslo) {
                $redis->auth($heslo);
            }
            if ($databaze !== 0) {
                $redis->select($databaze);
            }
            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
            return $redis;
        } catch (Exception $e) {
            error_log('WGS Cache: Redis připojení selhalo: ' . $e->getMessage());
            return null;
        }
    }

    // =============================================
    // Veřejné metody
    // =============================================

    /**
     * Načte hodnotu z cache. Vrací null pokud cache miss nebo vypršela platnost.
     */
    public static function nacti(string $klic): mixed
    {
        self::inicializovat();

        // 1. Redis
        if (self::$redis !== null) {
            try {
                $hodnota = self::$redis->get('wgs:' . $klic);
                if ($hodnota !== false) {
                    return $hodnota;
                }
            } catch (Exception $e) {
                error_log('WGS Cache Redis chyba (nacti): ' . $e->getMessage());
                self::$redis = null; // fallback při výpadku
            }
        }

        // 2. APCu
        if (self::$apcuDostupne) {
            $hodnota = apcu_fetch('wgs_' . $klic, $uspech);
            if ($uspech) {
                return $hodnota;
            }
        }

        // 3. Soubor
        return self::nactizeSouboru($klic);
    }

    /**
     * Uloží hodnotu do cache s volitelným TTL (v sekundách).
     */
    public static function uloz(string $klic, mixed $hodnota, int $ttl = self::TTL_STREDNI): void
    {
        self::inicializovat();

        // 1. Redis
        if (self::$redis !== null) {
            try {
                if ($ttl > 0) {
                    self::$redis->setex('wgs:' . $klic, $ttl, $hodnota);
                } else {
                    self::$redis->set('wgs:' . $klic, $hodnota);
                }
            } catch (Exception $e) {
                error_log('WGS Cache Redis chyba (uloz): ' . $e->getMessage());
                self::$redis = null;
            }
        }

        // 2. APCu
        if (self::$apcuDostupne) {
            apcu_store('wgs_' . $klic, $hodnota, $ttl);
        }

        // 3. Soubor
        self::ulozDoSouboru($klic, $hodnota, $ttl);
    }

    /**
     * Smaže konkrétní klíč z cache.
     */
    public static function smaz(string $klic): void
    {
        self::inicializovat();

        // Redis
        if (self::$redis !== null) {
            try {
                self::$redis->del('wgs:' . $klic);
            } catch (Exception $e) {
                error_log('WGS Cache Redis chyba (smaz): ' . $e->getMessage());
                self::$redis = null;
            }
        }

        // APCu
        if (self::$apcuDostupne) {
            apcu_delete('wgs_' . $klic);
        }

        // Soubor
        $soubor = self::cestkaSouboru($klic);
        if (file_exists($soubor)) {
            unlink($soubor);
        }
    }

    /**
     * Smaže všechny klíče odpovídající vzoru (podporuje prefix).
     * Příklad: WgsCache::smazVzorem('cenik_')
     */
    public static function smazVzorem(string $vzor): void
    {
        self::inicializovat();

        // Redis - scan + delete
        if (self::$redis !== null) {
            try {
                $kurzor = null;
                do {
                    $klice = self::$redis->scan($kurzor, 'wgs:' . $vzor . '*', 100);
                    if ($klice) {
                        self::$redis->del($klice);
                    }
                } while ($kurzor !== 0);
            } catch (Exception $e) {
                error_log('WGS Cache Redis chyba (smazVzorem): ' . $e->getMessage());
                self::$redis = null;
            }
        }

        // APCu
        if (self::$apcuDostupne) {
            $iterator = new APCUIterator('/^wgs_' . preg_quote($vzor, '/') . '/');
            foreach ($iterator as $polozka) {
                apcu_delete($polozka['key']);
            }
        }

        // Soubor
        $soubory = glob(self::$adresarCache . '/*.cache');
        if (!$soubory) {
            return;
        }

        foreach ($soubory as $soubor) {
            $obsah = @file_get_contents($soubor);
            if ($obsah === false) {
                continue;
            }

            $data = json_decode($obsah, true);
            if (!$data || !isset($data['klic'])) {
                continue;
            }

            if (str_starts_with($data['klic'], $vzor)) {
                unlink($soubor);
            }
        }
    }

    /**
     * Vyčistí celý WGS cache (všechny klíče s prefixem wgs:).
     */
    public static function vycistit(): void
    {
        self::inicializovat();

        // Redis - smaž jen wgs: klíče
        if (self::$redis !== null) {
            try {
                $kurzor = null;
                do {
                    $klice = self::$redis->scan($kurzor, 'wgs:*', 100);
                    if ($klice) {
                        self::$redis->del($klice);
                    }
                } while ($kurzor !== 0);
            } catch (Exception $e) {
                error_log('WGS Cache Redis chyba (vycistit): ' . $e->getMessage());
                self::$redis = null;
            }
        }

        // APCu
        if (self::$apcuDostupne) {
            apcu_clear_cache();
        }

        // Soubor
        $soubory = glob(self::$adresarCache . '/*.cache');
        if ($soubory) {
            foreach ($soubory as $soubor) {
                unlink($soubor);
            }
        }
    }

    /**
     * Vrátí statistiky cache (pro diagnostiku)
     */
    public static function statistiky(): array
    {
        self::inicializovat();

        $pocetSouboru = count(glob(self::$adresarCache . '/*.cache') ?: []);

        $backend = 'soubor';
        if (self::$redis !== null) {
            $backend = 'Redis + soubor';
            if (self::$apcuDostupne) {
                $backend = 'Redis + APCu + soubor';
            }
        } elseif (self::$apcuDostupne) {
            $backend = 'APCu + soubor';
        }

        $info = [
            'backend'        => $backend,
            'redis_dostupny' => self::$redis !== null,
            'apcu_dostupne'  => self::$apcuDostupne,
            'adresar_cache'  => self::$adresarCache,
            'pocet_souboru'  => $pocetSouboru,
        ];

        if (self::$apcuDostupne) {
            $apcuInfo = apcu_cache_info(true);
            $info['apcu_hits']   = $apcuInfo['num_hits'] ?? 0;
            $info['apcu_misses'] = $apcuInfo['num_misses'] ?? 0;
        }

        if (self::$redis !== null) {
            try {
                $redisInfo = self::$redis->info('stats');
                $info['redis_hits']   = $redisInfo['keyspace_hits']   ?? 0;
                $info['redis_misses'] = $redisInfo['keyspace_misses'] ?? 0;
            } catch (Exception $e) {
                // ignorovat
            }
        }

        return $info;
    }

    // =============================================
    // Privátní pomocné metody (souborový cache)
    // =============================================

    private static function cestkaSouboru(string $klic): string
    {
        return self::$adresarCache . '/' . md5($klic) . '.cache';
    }

    private static function nactizeSouboru(string $klic): mixed
    {
        $soubor = self::cestkaSouboru($klic);

        if (!file_exists($soubor)) {
            return null;
        }

        $obsah = @file_get_contents($soubor);
        if ($obsah === false) {
            return null;
        }

        $data = json_decode($obsah, true);
        if (!$data || !isset($data['expirace'], $data['hodnota'])) {
            return null;
        }

        if ($data['expirace'] !== 0 && $data['expirace'] < time()) {
            unlink($soubor);
            return null;
        }

        return $data['hodnota'];
    }

    private static function ulozDoSouboru(string $klic, mixed $hodnota, int $ttl): void
    {
        $soubor   = self::cestkaSouboru($klic);
        $expirace = $ttl > 0 ? (time() + $ttl) : 0;

        $data = json_encode([
            'klic'     => $klic,
            'expirace' => $expirace,
            'hodnota'  => $hodnota,
        ], JSON_UNESCAPED_UNICODE);

        @file_put_contents($soubor, $data, LOCK_EX);
    }
}
