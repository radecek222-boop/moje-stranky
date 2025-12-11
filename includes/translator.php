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

    // Maximalni delka textu pro jeden API pozadavek (MyMemory limit je 500 znaku pro free, 10000 s emailem)
    // Pouzijeme 4500 pro jistotu
    private const MAX_CHUNK_SIZE = 4500;

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
     * Pouziva alfanumericke placeholdery ktere API nemodifikuje
     */
    private function ochraniMarkdown(string $text): array
    {
        $placeholdery = [];
        $index = 0;

        // Ochranit obrazky ![alt](url) - NEPŘEKLÁDAT, zachovat celé
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function($match) use (&$placeholdery, &$index) {
            // Alfanumericky placeholder - API ho nezmeni
            $placeholder = "WGSIMAGE" . str_pad($index, 4, '0', STR_PAD_LEFT) . "END";
            $placeholdery[$placeholder] = $match[0];
            $index++;
            return $placeholder;
        }, $text);

        // Ochranit odkazy [text](url) - text prelozit, URL zachovat
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($match) use (&$placeholdery, &$index) {
            $placeholder = "WGSLINK" . str_pad($index, 4, '0', STR_PAD_LEFT) . "END";
            $placeholdery[$placeholder] = ['type' => 'link', 'text' => $match[1], 'url' => $match[2]];
            $index++;
            return $placeholder;
        }, $text);

        // Ochranit nadpisy ## a ### - text prelozit, znacky zachovat
        $text = preg_replace_callback('/^(#{1,3})\s+(.+)$/m', function($match) use (&$placeholdery, &$index) {
            $placeholder = "WGSHEAD" . str_pad($index, 4, '0', STR_PAD_LEFT) . "END";
            $placeholdery[$placeholder] = ['type' => 'heading', 'level' => $match[1], 'text' => $match[2]];
            $index++;
            return $placeholder;
        }, $text);

        return ['text' => $text, 'placeholdery' => $placeholdery];
    }

    /**
     * Obnovi markdown elementy po prekladu
     * Hleda alfanumericke placeholdery a nahradi je puvodnim obsahem
     */
    private function obnovMarkdown(string $text, array $placeholdery, string $cilovyJazyk): string
    {
        foreach ($placeholdery as $placeholder => $hodnota) {
            // Zkusit najit placeholder (muze byt s mezerami kolem od API)
            $pattern = '/' . preg_quote($placeholder, '/') . '/i';

            // Taky zkusit variantu s mezerami (nektera API pridavaji mezery)
            $placeholderSMezerami = preg_replace('/([A-Z]+)(\d+)([A-Z]+)/', '$1 $2 $3', $placeholder);

            if (is_string($hodnota)) {
                // Obrazek - vratit beze zmeny
                $text = preg_replace($pattern, $hodnota, $text);
                // Zkusit i verzi s mezerami
                $text = str_ireplace($placeholderSMezerami, $hodnota, $text);
            } elseif (is_array($hodnota)) {
                if ($hodnota['type'] === 'link') {
                    // Odkaz - prelozit text, zachovat URL
                    $prelozenyText = $this->zavolatGoogleTranslateSimple($hodnota['text'], $cilovyJazyk);
                    $nahrada = "[{$prelozenyText}]({$hodnota['url']})";
                    $text = preg_replace($pattern, $nahrada, $text);
                    $text = str_ireplace($placeholderSMezerami, $nahrada, $text);
                } elseif ($hodnota['type'] === 'heading') {
                    // Nadpis - prelozit text
                    $prelozenyText = $this->zavolatGoogleTranslateSimple($hodnota['text'], $cilovyJazyk);
                    $nahrada = "{$hodnota['level']} {$prelozenyText}";
                    $text = preg_replace($pattern, $nahrada, $text);
                    $text = str_ireplace($placeholderSMezerami, $nahrada, $text);
                }
            }
        }

        // Vycistit pripadne zbyle placeholdery (logovat jako warning)
        if (preg_match('/WGS(IMAGE|LINK|HEAD)\d{4}END/i', $text)) {
            error_log("WGSTranslator WARNING: Nektere placeholdery nebyly nahrazeny v textu");
        }

        return $text;
    }

    /**
     * Jednoduchy preklad kratkeho textu (pro nadpisy a odkazy)
     */
    private function zavolatGoogleTranslateSimple(string $text, string $cilovyJazyk): string
    {
        $result = $this->zavolatJedenChunk($text, $cilovyJazyk);
        return $result ?: $text;
    }

    /**
     * Zavola MyMemory Translate API s podporou dlouhych textu (chunking)
     * Dlouhe texty rozdeli na casti a prelozi postupne
     */
    private function zavolatGoogleTranslate(string $text, string $cilovyJazyk): ?string
    {
        // Pokud je text kratky, prelozit primo
        if (mb_strlen($text) <= self::MAX_CHUNK_SIZE) {
            return $this->zavolatJedenChunk($text, $cilovyJazyk);
        }

        // Rozdelit text na casti
        $chunky = $this->rozdelNaChunky($text);
        error_log("WGSTranslator: Text rozdelen na " . count($chunky) . " casti");

        $prelozeneChunky = [];
        foreach ($chunky as $i => $chunk) {
            error_log("WGSTranslator: Prekladam cast " . ($i + 1) . "/" . count($chunky) . " (" . mb_strlen($chunk) . " znaku)");

            $prelozeny = $this->zavolatJedenChunk($chunk, $cilovyJazyk);
            if ($prelozeny === null) {
                error_log("WGSTranslator: Cast " . ($i + 1) . " selhala - vracim original");
                return null; // Pokud selze jedna cast, vratit null
            }
            $prelozeneChunky[] = $prelozeny;

            // Pauza mezi pozadavky (rate limiting)
            if ($i < count($chunky) - 1) {
                usleep(500000); // 0.5 sekundy mezi pozadavky
            }
        }

        // Spojit prelozene casti
        return implode("\n\n", $prelozeneChunky);
    }

    /**
     * Rozdeli dlouhy text na mensi casti (chunky) na hranicich odstavcu
     */
    private function rozdelNaChunky(string $text): array
    {
        $chunky = [];
        $aktualniChunk = '';

        // Rozdelit na odstavce (dvojity newline nebo ## nadpisy)
        $odstavce = preg_split('/(\n\n+|(?=^## ))/m', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($odstavce as $odstavec) {
            $odstavec = trim($odstavec);
            if (empty($odstavec)) continue;

            // Pokud by pridani odstavce prekrocilo limit
            if (mb_strlen($aktualniChunk) + mb_strlen($odstavec) + 2 > self::MAX_CHUNK_SIZE) {
                // Ulozit aktualni chunk (pokud neni prazdny)
                if (!empty(trim($aktualniChunk))) {
                    $chunky[] = trim($aktualniChunk);
                }

                // Pokud je samotny odstavec moc dlouhy, rozdelit na vety
                if (mb_strlen($odstavec) > self::MAX_CHUNK_SIZE) {
                    $vetyChunky = $this->rozdelOdstavecNaVety($odstavec);
                    foreach ($vetyChunky as $vetaChunk) {
                        $chunky[] = $vetaChunk;
                    }
                    $aktualniChunk = '';
                } else {
                    $aktualniChunk = $odstavec;
                }
            } else {
                // Pridat odstavec k aktualnimu chunku
                $aktualniChunk .= ($aktualniChunk ? "\n\n" : '') . $odstavec;
            }
        }

        // Nezapomenout na posledni chunk
        if (!empty(trim($aktualniChunk))) {
            $chunky[] = trim($aktualniChunk);
        }

        return $chunky;
    }

    /**
     * Rozdeli prilis dlouhy odstavec na vety
     */
    private function rozdelOdstavecNaVety(string $odstavec): array
    {
        $chunky = [];
        $aktualniChunk = '';

        // Rozdelit na vety (tecka, vykricnik, otaznik + mezera nebo konec)
        $vety = preg_split('/(?<=[.!?])\s+/', $odstavec);

        foreach ($vety as $veta) {
            $veta = trim($veta);
            if (empty($veta)) continue;

            if (mb_strlen($aktualniChunk) + mb_strlen($veta) + 1 > self::MAX_CHUNK_SIZE) {
                if (!empty(trim($aktualniChunk))) {
                    $chunky[] = trim($aktualniChunk);
                }
                $aktualniChunk = $veta;
            } else {
                $aktualniChunk .= ($aktualniChunk ? ' ' : '') . $veta;
            }
        }

        if (!empty(trim($aktualniChunk))) {
            $chunky[] = trim($aktualniChunk);
        }

        return $chunky;
    }

    /**
     * Zavola MyMemory API pro jeden chunk textu
     * Dokumentace: https://mymemory.translated.net/doc/spec.php
     */
    private function zavolatJedenChunk(string $text, string $cilovyJazyk): ?string
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
                    'timeout' => 30,
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
