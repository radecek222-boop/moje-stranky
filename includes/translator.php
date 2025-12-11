<?php
/**
 * WGS Translator - Automaticky preklad s cache
 *
 * Pouziva MyMemory Translate API (bezplatne, 10000 slov/den)
 * Preklady se cachuji v databazi podle MD5 hashe zdrojoveho textu
 *
 * DULEZITE: Preklad probiha po sekcich (## nadpisy), obrazky a odkazy
 * jsou extrahovany pred prekladem a vlozeny zpet po prekladu.
 * Tim se zabrani ztrate obrazku a poskozeni odkazu.
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

    // Maximalni delka textu pro jeden API pozadavek
    private const MAX_CHUNK_SIZE = 4500;

    // Mapovani jazyku
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
     */
    public function preloz(string $text, string $cilovyJazyk, ?string $entityType = null, ?int $entityId = null): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $cilovyJazyk = strtolower($cilovyJazyk);
        if (!isset($this->jazykoveKody[$cilovyJazyk])) {
            error_log("WGSTranslator: Neznamy cilovy jazyk: {$cilovyJazyk}");
            return $text;
        }

        $hash = $this->vypoctiHash($text);

        // Cache lookup
        $cachovanyPreklad = $this->najdiVCache($hash, $cilovyJazyk);
        if ($cachovanyPreklad !== null) {
            return $cachovanyPreklad;
        }

        // Prelozit po sekcich - zachova obrazky a odkazy
        $prelozenyText = $this->prelozPoSekcich($text, $cilovyJazyk);

        if ($prelozenyText !== null && $prelozenyText !== $text) {
            $this->ulozDoCache($hash, $text, $prelozenyText, $cilovyJazyk, $entityType, $entityId);
            return $prelozenyText;
        }

        return $text;
    }

    /**
     * Prelozi text po sekcich - extrahuje obrazky/odkazy, prelozi text, slozi zpet
     */
    private function prelozPoSekcich(string $text, string $cilovyJazyk): ?string
    {
        // Rozdelit na sekce podle ## nadpisu
        $sekce = preg_split('/(?=^## )/m', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sekce)) {
            return $this->prelozCistyText($text, $cilovyJazyk);
        }

        error_log("WGSTranslator: Prekladam " . count($sekce) . " sekci do {$cilovyJazyk}");
        $prelozeneSekce = [];

        foreach ($sekce as $i => $sekceText) {
            $sekceText = trim($sekceText);
            if (empty($sekceText)) continue;

            $prelozenaSekce = $this->prelozSekci($sekceText, $cilovyJazyk);
            $prelozeneSekce[] = $prelozenaSekce;

            // Pauza mezi sekcemi
            if ($i < count($sekce) - 1) {
                usleep(400000); // 0.4s
            }
        }

        return implode("\n\n", $prelozeneSekce);
    }

    /**
     * Prelozi jednu sekci - extrahuje obrazky a odkazy, prelozi zbytek
     */
    private function prelozSekci(string $sekceText, string $cilovyJazyk): string
    {
        // 1. Extrahovat obrazky (ulozit, odstranit z textu)
        $obrazky = [];
        preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $sekceText, $matchesImg, PREG_SET_ORDER);
        foreach ($matchesImg as $m) {
            $obrazky[] = $m[0];
        }
        $textBezObrazku = preg_replace('/\n*!\[([^\]]*)\]\(([^)]+)\)\n*/', "\n", $sekceText);

        // 2. Extrahovat a prelozit odkazy
        $odkazy = [];
        $textBezOdkazu = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($match) use (&$odkazy, $cilovyJazyk) {
            $odkazText = trim($match[1]);
            $odkazUrl = $match[2];

            // Prelozit text odkazu (kratky text)
            $prelozenyOdkaz = $this->prelozKratkyText($odkazText, $cilovyJazyk);

            $odkazy[] = "[{$prelozenyOdkaz}]({$odkazUrl})";
            return ""; // Odstranit z textu
        }, $textBezObrazku);

        // 3. Extrahovat a prelozit nadpis
        $nadpis = '';
        $textBezNadpisu = preg_replace_callback('/^(#{1,3})\s+(.+)$/m', function($match) use (&$nadpis, $cilovyJazyk) {
            $uroven = $match[1];
            $nadpisText = trim($match[2]);

            $prelozenyNadpis = $this->prelozKratkyText($nadpisText, $cilovyJazyk);
            $nadpis = "{$uroven} {$prelozenyNadpis}";
            return "";
        }, $textBezOdkazu);

        // 4. Vycistit zbyvajici text
        $cistyText = trim(preg_replace('/\n{3,}/', "\n\n", $textBezNadpisu));

        // 5. Prelozit hlavni text (pokud neni prazdny)
        $prelozenyText = '';
        if (!empty($cistyText)) {
            $prelozenyText = $this->prelozCistyText($cistyText, $cilovyJazyk);
        }

        // 6. Sestavit vysledek: nadpis + text + odkazy + obrazky
        $vysledek = '';

        if (!empty($nadpis)) {
            $vysledek .= $nadpis . "\n\n";
        }

        if (!empty($prelozenyText)) {
            $vysledek .= $prelozenyText . "\n\n";
        }

        if (!empty($odkazy)) {
            $vysledek .= implode(' | ', $odkazy) . "\n\n";
        }

        if (!empty($obrazky)) {
            $vysledek .= implode("\n\n", $obrazky);
        }

        return trim($vysledek);
    }

    /**
     * Prelozi kratky text (nadpisy, odkazy) - primo bez chunking
     */
    private function prelozKratkyText(string $text, string $cilovyJazyk): string
    {
        $text = trim($text);
        if (empty($text)) return $text;

        $preklad = $this->zavolatMyMemoryAPI($text, $cilovyJazyk);
        return $preklad ?: $text;
    }

    /**
     * Prelozi delsi cisty text - s podporou chunking
     */
    private function prelozCistyText(string $text, string $cilovyJazyk): string
    {
        $text = trim($text);
        if (empty($text)) return $text;

        // Kratky text - prelozit primo
        if (mb_strlen($text) <= self::MAX_CHUNK_SIZE) {
            $preklad = $this->zavolatMyMemoryAPI($text, $cilovyJazyk);
            return $preklad ?: $text;
        }

        // Dlouhy text - rozdelit na chunky
        $chunky = $this->rozdelNaChunky($text);
        $prelozeneChunky = [];

        foreach ($chunky as $i => $chunk) {
            $prelozeny = $this->zavolatMyMemoryAPI($chunk, $cilovyJazyk);
            $prelozeneChunky[] = $prelozeny ?: $chunk;

            if ($i < count($chunky) - 1) {
                usleep(500000); // 0.5s mezi chunky
            }
        }

        return implode("\n\n", $prelozeneChunky);
    }

    /**
     * Rozdeli dlouhy text na mensi casti
     */
    private function rozdelNaChunky(string $text): array
    {
        $chunky = [];
        $aktualniChunk = '';

        // Rozdelit na odstavce
        $odstavce = preg_split('/\n\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($odstavce as $odstavec) {
            $odstavec = trim($odstavec);
            if (empty($odstavec)) continue;

            if (mb_strlen($aktualniChunk) + mb_strlen($odstavec) + 2 > self::MAX_CHUNK_SIZE) {
                if (!empty(trim($aktualniChunk))) {
                    $chunky[] = trim($aktualniChunk);
                }
                $aktualniChunk = $odstavec;
            } else {
                $aktualniChunk .= ($aktualniChunk ? "\n\n" : '') . $odstavec;
            }
        }

        if (!empty(trim($aktualniChunk))) {
            $chunky[] = trim($aktualniChunk);
        }

        return $chunky ?: [$text];
    }

    /**
     * Zavola MyMemory Translate API (POST pro delsi texty)
     */
    private function zavolatMyMemoryAPI(string $text, string $cilovyJazyk): ?string
    {
        $url = 'https://api.mymemory.translated.net/get';

        $postData = http_build_query([
            'q' => $text,
            'langpair' => $this->jazykoveKody[$this->zdrojovyJazyk] . '|' . $this->jazykoveKody[$cilovyJazyk],
            'de' => 'info@wgs-service.cz'
        ]);

        try {
            // Pouzit POST pro delsi texty (GET ma limit URL)
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'method' => 'POST',
                    'header' => [
                        'User-Agent: WGS-Service/1.0',
                        'Accept: application/json',
                        'Content-Type: application/x-www-form-urlencoded',
                        'Content-Length: ' . strlen($postData)
                    ],
                    'content' => $postData,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log("WGSTranslator: MyMemory request failed");
                return null;
            }

            $data = json_decode($response, true);

            if (!$data || !isset($data['responseData']['translatedText'])) {
                error_log("WGSTranslator: Invalid response: " . substr($response, 0, 200));
                return null;
            }

            if (isset($data['responseStatus']) && $data['responseStatus'] != 200) {
                error_log("WGSTranslator: API error: " . $data['responseStatus']);
                return null;
            }

            $prelozenyText = $data['responseData']['translatedText'];
            $prelozenyText = html_entity_decode($prelozenyText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $prelozenyText ?: null;

        } catch (Throwable $e) {
            error_log("WGSTranslator error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Prelozi do vice jazyku
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
     * Hash pro cache
     */
    private function vypoctiHash(string $text): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text));
        return md5($normalized);
    }

    /**
     * Najde v cache
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
            return null;
        }
    }

    /**
     * Ulozi do cache
     */
    private function ulozDoCache(string $hash, string $src, string $tgt, string $lang, ?string $type, ?int $id): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_translation_cache
                (source_hash, source_lang, target_lang, source_text, translated_text, entity_type, entity_id)
                VALUES (:hash, :src_lang, :tgt_lang, :src_text, :tgt_text, :type, :id)
                ON DUPLICATE KEY UPDATE
                    translated_text = VALUES(translated_text),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':hash' => $hash, ':src_lang' => $this->zdrojovyJazyk, ':tgt_lang' => $lang,
                ':src_text' => $src, ':tgt_text' => $tgt, ':type' => $type, ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("WGSTranslator cache error: " . $e->getMessage());
        }
    }

    public function smazCacheProEntitu(string $entityType, int $entityId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM wgs_translation_cache WHERE entity_type = :type AND entity_id = :id");
        $stmt->execute([':type' => $entityType, ':id' => $entityId]);
    }

    public function textSeZmenil(string $text, string $cilovyJazyk): bool
    {
        return $this->najdiVCache($this->vypoctiHash($text), $cilovyJazyk) === null;
    }

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
        } catch (PDOException $e) {}
    }
}

function wgsTranslate(PDO $pdo, string $text, string $cilovyJazyk): string
{
    static $translator = null;
    if ($translator === null) {
        $translator = new WGSTranslator($pdo);
    }
    return $translator->preloz($text, $cilovyJazyk);
}
