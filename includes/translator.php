<?php
/**
 * WGS Translator - Automaticky preklad s cache
 *
 * Pouziva bezplatne Google Translate API (neoficialni)
 * Preklady se cachuji v databazi podle MD5 hashe zdrojoveho textu
 *
 * Pouziti:
 *   $translator = new WGSTranslator($pdo);
 *   $prelozenoEN = $translator->preloz($ceskyText, 'en');
 *   $prelozenoIT = $translator->preloz($ceskyText, 'it');
 */

class WGSTranslator
{
    private PDO $pdo;
    private string $zdrojovyJazyk = 'cs';

    // Mapovani jazyku pro Google Translate
    private array $jazykoveKody = [
        'cs' => 'cs',
        'cz' => 'cs',
        'en' => 'en',
        'it' => 'it'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->zajistiTabulku();
    }

    /**
     * Prelozi text do ciloveho jazyka
     * Pouziva cache - pokud preklad existuje a hash sedi, vrati z cache
     *
     * @param string $text Zdrojovy text v cestine
     * @param string $cilovyJazyk 'en' nebo 'it'
     * @param string|null $entityType Typ entity pro organizaci cache
     * @param int|null $entityId ID entity
     * @return string Prelozeny text
     */
    public function preloz(string $text, string $cilovyJazyk, ?string $entityType = null, ?int $entityId = null): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        // Normalizovat cilovy jazyk
        $cilovyJazyk = strtolower($cilovyJazyk);
        if (!isset($this->jazykoveKody[$cilovyJazyk])) {
            error_log("WGSTranslator: Neznamy cilovy jazyk: {$cilovyJazyk}");
            return $text;
        }

        // Vypocitat hash zdrojoveho textu
        $hash = $this->vypoctiHash($text);

        // Zkusit najit v cache
        $cachovanyPreklad = $this->najdiVCache($hash, $cilovyJazyk);
        if ($cachovanyPreklad !== null) {
            return $cachovanyPreklad;
        }

        // Neni v cache - zavolat Google Translate
        $prelozenyText = $this->zavolatGoogleTranslate($text, $cilovyJazyk);

        if ($prelozenyText !== null && $prelozenyText !== $text) {
            // Ulozit do cache
            $this->ulozDoCache($hash, $text, $prelozenyText, $cilovyJazyk, $entityType, $entityId);
            return $prelozenyText;
        }

        // Preklad selhal - vratit original
        return $text;
    }

    /**
     * Prelozi text do vice jazyku najednou
     *
     * @param string $text Zdrojovy text
     * @param array $jazyky Pole cilovych jazyku ['en', 'it']
     * @return array ['en' => '...', 'it' => '...']
     */
    public function prelozDoViceJazyku(string $text, array $jazyky = ['en', 'it']): array
    {
        $vysledky = [];
        foreach ($jazyky as $jazyk) {
            $vysledky[$jazyk] = $this->preloz($text, $jazyk);
        }
        return $vysledky;
    }

    /**
     * Zkontroluje zda se text zmenil (jiny hash)
     */
    public function textSeZmenil(string $text, string $cilovyJazyk): bool
    {
        $hash = $this->vypoctiHash($text);
        return $this->najdiVCache($hash, $cilovyJazyk) === null;
    }

    /**
     * Smaze cache pro danou entitu
     */
    public function smazCacheProEntitu(string $entityType, int $entityId): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM wgs_translation_cache
            WHERE entity_type = :type AND entity_id = :id
        ");
        $stmt->execute([':type' => $entityType, ':id' => $entityId]);
    }

    /**
     * Vypocita MD5 hash textu (normalizovany)
     */
    private function vypoctiHash(string $text): string
    {
        // Normalizovat text pro konzistentni hash
        $normalized = preg_replace('/\s+/', ' ', trim($text));
        return md5($normalized);
    }

    /**
     * Najde preklad v cache podle hashe
     */
    private function najdiVCache(string $hash, string $cilovyJazyk): ?string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT translated_text FROM wgs_translation_cache
                WHERE source_hash = :hash AND target_lang = :lang
                LIMIT 1
            ");
            $stmt->execute([':hash' => $hash, ':lang' => $cilovyJazyk]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? $row['translated_text'] : null;
        } catch (PDOException $e) {
            error_log("WGSTranslator cache lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Ulozi preklad do cache
     */
    private function ulozDoCache(
        string $hash,
        string $zdrojovyText,
        string $prelozenyText,
        string $cilovyJazyk,
        ?string $entityType,
        ?int $entityId
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_translation_cache
                (source_hash, source_lang, target_lang, source_text, translated_text, entity_type, entity_id)
                VALUES (:hash, :src_lang, :tgt_lang, :src_text, :tgt_text, :entity_type, :entity_id)
                ON DUPLICATE KEY UPDATE
                    source_text = VALUES(source_text),
                    translated_text = VALUES(translated_text),
                    entity_type = VALUES(entity_type),
                    entity_id = VALUES(entity_id),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                ':hash' => $hash,
                ':src_lang' => $this->zdrojovyJazyk,
                ':tgt_lang' => $cilovyJazyk,
                ':src_text' => $zdrojovyText,
                ':tgt_text' => $prelozenyText,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId
            ]);
        } catch (PDOException $e) {
            error_log("WGSTranslator cache save error: " . $e->getMessage());
        }
    }

    /**
     * Zavola Google Translate API (neoficialni bezplatna verze)
     */
    private function zavolatGoogleTranslate(string $text, string $cilovyJazyk): ?string
    {
        // Google Translate neoficialni API
        // Limit: neni oficialnejeden, ale pri velkem pouziti muze blokovat
        $url = 'https://translate.googleapis.com/translate_a/single';

        $params = [
            'client' => 'gtx',
            'sl' => $this->jazykoveKody[$this->zdrojovyJazyk],
            'tl' => $this->jazykoveKody[$cilovyJazyk],
            'dt' => 't',
            'q' => $text
        ];

        $fullUrl = $url . '?' . http_build_query($params);

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept: application/json'
                    ],
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($fullUrl, false, $context);

            if ($response === false) {
                error_log("WGSTranslator: Google Translate request failed");
                return null;
            }

            // Parsovat odpoved (format je nested array)
            $data = json_decode($response, true);

            if (!$data || !isset($data[0])) {
                error_log("WGSTranslator: Invalid response from Google Translate");
                return null;
            }

            // Sestavit prelozeny text z jednotlivych segmentu
            $prelozenyText = '';
            foreach ($data[0] as $segment) {
                if (isset($segment[0])) {
                    $prelozenyText .= $segment[0];
                }
            }

            return $prelozenyText ?: null;

        } catch (Throwable $e) {
            error_log("WGSTranslator error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Zajisti existenci tabulky pro cache
     */
    private function zajistiTabulku(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `wgs_translation_cache` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `source_hash` VARCHAR(32) NOT NULL,
                    `source_lang` VARCHAR(5) NOT NULL DEFAULT 'cs',
                    `target_lang` VARCHAR(5) NOT NULL,
                    `source_text` LONGTEXT NOT NULL,
                    `translated_text` LONGTEXT NOT NULL,
                    `entity_type` VARCHAR(50) DEFAULT 'aktualita',
                    `entity_id` INT UNSIGNED NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uk_hash_lang` (`source_hash`, `target_lang`),
                    INDEX `idx_entity` (`entity_type`, `entity_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Tabulka uz existuje - OK
        }
    }
}

/**
 * Helper funkce pro rychly preklad
 */
function wgsTranslate(PDO $pdo, string $text, string $cilovyJazyk): string
{
    static $translator = null;
    if ($translator === null) {
        $translator = new WGSTranslator($pdo);
    }
    return $translator->preloz($text, $cilovyJazyk);
}
