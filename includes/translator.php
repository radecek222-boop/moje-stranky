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

        // Ochranit markdown (obrazky, odkazy) pred prekladem
        $chranene = $this->ochraniMarkdown($text);
        $textBezMarkdown = $chranene['text'];
        $placeholdery = $chranene['placeholdery'];

        // Prelozit text bez markdown
        $prelozenyText = $this->zavolatGoogleTranslate($textBezMarkdown, $cilovyJazyk);

        if ($prelozenyText !== null) {
            // Obnovit markdown elementy
            $prelozenyText = $this->obnovMarkdown($prelozenyText, $placeholdery, $cilovyJazyk);

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
     * Ochrani markdown elementy pred prekladem (obrazky, odkazy, nadpisy)
     */
    private function ochraniMarkdown(string $text): array
    {
        $placeholdery = [];
        $index = 0;

        // Ochranit obrazky ![alt](url)
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($match) use (&$placeholdery, &$index) {
            $placeholder = "{{IMG_{$index}}}";
            $placeholdery[$placeholder] = $match[0];
            $index++;
            return $placeholder;
        }, $text);

        // Ochranit odkazy [text](url) - zachovat text pro preklad, ochranit URL
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($match) use (&$placeholdery, &$index) {
            $placeholder = "{{LINK_{$index}}}";
            // Ulozit URL a text zvlast
            $placeholdery[$placeholder] = ['type' => 'link', 'text' => $match[1], 'url' => $match[2]];
            $index++;
            return $placeholder;
        }, $text);

        // Ochranit nadpisy ## a ###
        $text = preg_replace_callback('/^(#{1,3})\s+(.+)$/m', function($match) use (&$placeholdery, &$index) {
            $placeholder = "{{HEADING_{$index}}}";
            $placeholdery[$placeholder] = ['type' => 'heading', 'level' => $match[1], 'text' => $match[2]];
            $index++;
            return $placeholder;
        }, $text);

        return ['text' => $text, 'placeholdery' => $placeholdery];
    }

    /**
     * Obnovi markdown elementy po prekladu
     */
    private function obnovMarkdown(string $text, array $placeholdery, string $cilovyJazyk): string
    {
        foreach ($placeholdery as $placeholder => $hodnota) {
            if (is_string($hodnota)) {
                // Obrazek - vratit beze zmeny
                $text = str_replace($placeholder, $hodnota, $text);
            } elseif (is_array($hodnota)) {
                if ($hodnota['type'] === 'link') {
                    // Odkaz - prelozit text, zachovat URL
                    $prelozenyText = $this->zavolatGoogleTranslateSimple($hodnota['text'], $cilovyJazyk);
                    $text = str_replace($placeholder, "[{$prelozenyText}]({$hodnota['url']})", $text);
                } elseif ($hodnota['type'] === 'heading') {
                    // Nadpis - prelozit text
                    $prelozenyText = $this->zavolatGoogleTranslateSimple($hodnota['text'], $cilovyJazyk);
                    $text = str_replace($placeholder, "{$hodnota['level']} {$prelozenyText}", $text);
                }
            }
        }
        return $text;
    }

    /**
     * Jednoduchy preklad kratkeho textu (pro nadpisy a odkazy)
     */
    private function zavolatGoogleTranslateSimple(string $text, string $cilovyJazyk): string
    {
        $result = $this->zavolatGoogleTranslate($text, $cilovyJazyk);
        return $result ?: $text;
    }

    /**
     * Zavola MyMemory Translate API (bezplatna verze - 10000 slov/den)
     * Dokumentace: https://mymemory.translated.net/doc/spec.php
     */
    private function zavolatGoogleTranslate(string $text, string $cilovyJazyk): ?string
    {
        // MyMemory API - bezplatne, limit 10000 slov/den
        $url = 'https://api.mymemory.translated.net/get';

        $params = [
            'q' => $text,
            'langpair' => $this->jazykoveKody[$this->zdrojovyJazyk] . '|' . $this->jazykoveKody[$cilovyJazyk],
            'de' => 'info@wgs-service.cz' // Email pro vyssi limit (volitelne)
        ];

        $fullUrl = $url . '?' . http_build_query($params);

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: WGS-Service/1.0',
                        'Accept: application/json'
                    ],
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($fullUrl, false, $context);

            if ($response === false) {
                error_log("WGSTranslator: MyMemory request failed");
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['responseData']['translatedText'])) {
                error_log("WGSTranslator: Invalid response from MyMemory: " . substr($response, 0, 500));
                return null;
            }

            // Zkontrolovat status
            if (isset($data['responseStatus']) && $data['responseStatus'] != 200) {
                error_log("WGSTranslator: MyMemory error status: " . $data['responseStatus']);
                return null;
            }

            $prelozenyText = $data['responseData']['translatedText'];

            // MyMemory vraci HTML entity - dekodovat
            $prelozenyText = html_entity_decode($prelozenyText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
