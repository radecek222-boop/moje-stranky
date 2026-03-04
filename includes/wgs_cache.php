<?php
/**
 * WGS Cache - Lehký cachovací systém pro snížení zátěže databáze
 *
 * Prioritní pořadí backendů:
 *   1. APCu (PHP sdílená paměť - nejrychlejší, pokud dostupné)
 *   2. Souborový cache (temp/cache/ - vždy dostupné)
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
    public const TTL_KRATKY  = 60;       // 1 minuta
    public const TTL_STREDNI = 300;      // 5 minut
    public const TTL_DLOUHY  = 3600;     // 1 hodina
    public const TTL_VELMI_DLOUHY = 86400; // 24 hodin

    private static string $adresarCache = '';
    private static bool   $inicializovano = false;
    private static bool   $apcuDostupne = false;

    /**
     * Inicializace cache systému (volá se automaticky)
     */
    private static function inicializovat(): void
    {
        if (self::$inicializovano) {
            return;
        }

        // Adresář pro souborový cache
        $zaklad = defined('TEMP_PATH') ? TEMP_PATH : dirname(__DIR__) . '/temp';
        self::$adresarCache = $zaklad . '/cache';

        // Vytvořit adresář pokud neexistuje
        if (!is_dir(self::$adresarCache)) {
            mkdir(self::$adresarCache, 0750, true);
        }

        // Zkontrolovat dostupnost APCu
        self::$apcuDostupne = extension_loaded('apcu') && apcu_enabled();

        self::$inicializovano = true;
    }

    /**
     * Načte hodnotu z cache. Vrací null pokud cache miss nebo vypršela platnost.
     */
    public static function nacti(string $klic): mixed
    {
        self::inicializovat();

        if (self::$apcuDostupne) {
            $hodnota = apcu_fetch('wgs_' . $klic, $uspech);
            if ($uspech) {
                return $hodnota;
            }
            // APCu miss → zkusit souborový cache
        }

        return self::nactizeSouboru($klic);
    }

    /**
     * Uloží hodnotu do cache s volitelným TTL (v sekundách).
     */
    public static function uloz(string $klic, mixed $hodnota, int $ttl = self::TTL_STREDNI): void
    {
        self::inicializovat();

        if (self::$apcuDostupne) {
            apcu_store('wgs_' . $klic, $hodnota, $ttl);
        }

        self::ulozDoSouboru($klic, $hodnota, $ttl);
    }

    /**
     * Smaže konkrétní klíč z cache.
     */
    public static function smaz(string $klic): void
    {
        self::inicializovat();

        if (self::$apcuDostupne) {
            apcu_delete('wgs_' . $klic);
        }

        $soubor = self::cestkaSouboru($klic);
        if (file_exists($soubor)) {
            unlink($soubor);
        }
    }

    /**
     * Smaže všechny klíče odpovídající vzoru (podporuje wildcard * na konci).
     * Příklad: WgsCache::smazVzorem('cenik_')
     */
    public static function smazVzorem(string $vzor): void
    {
        self::inicializovat();

        if (self::$apcuDostupne) {
            // APCu: procházet a mazat odpovídající klíče
            $iterator = new APCUIterator('/^wgs_' . preg_quote($vzor, '/') . '/');
            foreach ($iterator as $polozka) {
                apcu_delete($polozka['key']);
            }
        }

        // Souborový cache: smazat soubory s odpovídajícím prefixem
        $prefix = md5($vzor);
        $soubory = glob(self::$adresarCache . '/*.cache');
        if (!$soubory) {
            return;
        }

        foreach ($soubory as $soubor) {
            $obsah = @file_get_contents($soubor);
            if ($obsah === false) {
                continue;
            }

            $data = @unserialize($obsah);
            if (!$data || !isset($data['klic'])) {
                continue;
            }

            // Zkontrolovat zda klíč začíná vzorem
            if (str_starts_with($data['klic'], $vzor)) {
                unlink($soubor);
            }
        }
    }

    /**
     * Vyčistí celý cache (všechny klíče).
     */
    public static function vycistit(): void
    {
        self::inicializovat();

        if (self::$apcuDostupne) {
            apcu_clear_cache();
        }

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

        $info = [
            'backend'      => self::$apcuDostupne ? 'APCu + soubor' : 'soubor',
            'apcu_dostupne' => self::$apcuDostupne,
            'adresar_cache' => self::$adresarCache,
            'pocet_souboru' => $pocetSouboru,
        ];

        if (self::$apcuDostupne) {
            $apcuInfo = apcu_cache_info(true);
            $info['apcu_hits']   = $apcuInfo['num_hits'] ?? 0;
            $info['apcu_misses'] = $apcuInfo['num_misses'] ?? 0;
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

        $data = @unserialize($obsah);
        if (!$data || !isset($data['expirace'], $data['hodnota'])) {
            return null;
        }

        // Zkontrolovat expiraci
        if ($data['expirace'] !== 0 && $data['expirace'] < time()) {
            unlink($soubor);
            return null;
        }

        return $data['hodnota'];
    }

    private static function ulozDoSouboru(string $klic, mixed $hodnota, int $ttl): void
    {
        $soubor  = self::cestkaSouboru($klic);
        $expirace = $ttl > 0 ? (time() + $ttl) : 0; // 0 = nikdy nevyprší

        $data = serialize([
            'klic'     => $klic,
            'expirace' => $expirace,
            'hodnota'  => $hodnota,
        ]);

        @file_put_contents($soubor, $data, LOCK_EX);
    }
}
