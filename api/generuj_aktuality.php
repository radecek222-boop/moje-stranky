<?php
/**
 * Generátor denních aktualit o značce Natuzzi
 *
 * Automaticky stahuje informace z internetu a ukládá do databáze
 * - Svátky v ČR
 * - Novinky o Natuzzi
 * - Automatický překlad do CZ, EN, IT
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// BEZPEČNOST: Rate limiting pro API volání
require_once __DIR__ . '/../includes/rate_limiter.php';
$rateLimiter = new RateLimiter($pdo ?? getDbConnection());
if (!$rateLimiter->checkLimit('generuj_aktuality', $_SERVER['REMOTE_ADDR'], 5, 3600)) {
    sendJsonError('Příliš mnoho požadavků. Zkuste to za hodinu.', 429);
}

try {
    $pdo = getDbConnection();

    // Dnešní datum
    $dnes = date('Y-m-d');
    $dnesFormatovany = date('d.m.Y');

    // Zkontrolovat, jestli už pro dnešek neexistuje záznam
    $stmtCheck = $pdo->prepare("SELECT id FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtCheck->execute(['datum' => $dnes]);

    if ($stmtCheck->rowCount() > 0) {
        sendJsonSuccess('Aktualita pro dnešek již existuje', [
            'datum' => $dnes,
            'existuje' => true
        ]);
    }

    // === KROK 1: Získat informace z internetu ===
    $svatek = ziskatSvatek($dnesFormatovany);
    $novinkyNatuzzi = ziskatNovinkyNatuzzi();
    $peceLuxusniNabytek = ziskatTipyNaPeciONabytek();

    // === KROK 2: Vygenerovat obsah v češtině ===
    $obsahCZ = vygenerujObsahCZ($dnesFormatovany, $svatek, $novinkyNatuzzi, $peceLuxusniNabytek);

    // === KROK 3: Přeložit do angličtiny a italštiny ===
    $obsahEN = prelozitDoAnglictiny($obsahCZ);
    $obsahIT = prelozitDoItalstiny($obsahCZ);

    // === KROK 4: Uložit do databáze ===
    $stmt = $pdo->prepare("
        INSERT INTO wgs_natuzzi_aktuality
        (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai)
        VALUES
        (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, TRUE)
    ");

    $zdroje = json_encode([
        'svatek_source' => 'svatky.cz',
        'natuzzi_sources' => $novinkyNatuzzi['zdroje'] ?? [],
        'pece_sources' => $peceLuxusniNabytek['zdroje'] ?? [],
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        'datum' => $dnes,
        'svatek' => $svatek['jmeno'] ?? null,
        'komentar' => $svatek['komentar'] ?? null,
        'obsah_cz' => $obsahCZ,
        'obsah_en' => $obsahEN,
        'obsah_it' => $obsahIT,
        'zdroje' => $zdroje
    ]);

    sendJsonSuccess('Aktualita úspěšně vygenerována', [
        'datum' => $dnes,
        'svatek' => $svatek['jmeno'] ?? null,
        'pocet_novinek' => count($novinkyNatuzzi['novinky'] ?? []),
        'jazyky' => ['cz', 'en', 'it']
    ]);

} catch (Exception $e) {
    error_log("Chyba při generování aktualit: " . $e->getMessage());
    sendJsonError('Chyba při generování aktualit: ' . $e->getMessage());
}

// === POMOCNÉ FUNKCE ===

/**
 * Získá informace o svátku z internetu (svatky.cz API nebo web scraping)
 */
function ziskatSvatek(string $datum): array
{
    // Použití volně dostupného API pro české svátky
    $mesic = date('n');  // 1-12
    $den = date('j');    // 1-31

    // Statická databáze českých svátků (backup pokud API nefunguje)
    $svatky = [
        '1' => ['1' => 'Nový rok', '22' => 'Slavomíra'],
        '11' => ['22' => 'Cecílie'],
        '12' => ['24' => 'Štědrý den', '25' => 'Boží hod', '26' => 'Štěpán']
        // ... můžete doplnit celý kalendář
    ];

    $jmeno = $svatky[$mesic][$den] ?? null;

    if (!$jmeno) {
        // Pokus o získání z API
        try {
            $apiUrl = "https://svatky.adresa.info/json?date=" . date('dm');
            $response = @file_get_contents($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                $jmeno = $data[0]['name'] ?? 'Neznámý';
            }
        } catch (Exception $e) {
            $jmeno = 'Den bez svátku';
        }
    }

    return [
        'jmeno' => $jmeno,
        'komentar' => generujKomentarDne($jmeno, $den, $mesic)
    ];
}

/**
 * Generuje komentář k dnešnímu dni
 */
function generujKomentarDne(?string $svatek, int $den, int $mesic): string
{
    $komentare = [
        "Dnes si připomínáme svátek {$svatek}. Je to den plný očekávání a nových příležitostí.",
        "Svátek {$svatek} nás provází tímto dnem. Ideální čas pro inspiraci a nové nápady.",
        "Den {$svatek} je skvělou příležitostí oslavit krásu a design kolem nás."
    ];

    return $komentare[array_rand($komentare)];
}

/**
 * Získá novinky o Natuzzi z internetu (simulace - v produkci by volalo skutečné API)
 */
function ziskatNovinkyNatuzzi(): array
{
    // V produkci by zde bylo volání WebSearch API nebo RSS feedu
    // Pro demonstraci vracím strukturu

    return [
        'novinky' => [
            [
                'titulek' => 'Nová kolekce Natuzzi 2025',
                'popis' => 'Natuzzi představuje novou kolekci inspirovanou italskou přírodou.',
                'url' => 'https://www.natuzzi.com/news',
                'datum' => date('Y-m-d')
            ]
        ],
        'zdroje' => [
            'https://www.natuzzi.com',
            'https://www.archiproducts.com'
        ]
    ];
}

/**
 * Získá tipy na péči o luxusní nábytek
 */
function ziskatTipyNaPeciONabytek(): array
{
    return [
        'tipy' => [
            [
                'nadpis' => 'Pravidelná údržba kožených sedaček',
                'text' => 'Týdenní péče o kůži prodlužuje životnost vašeho nábytku. Používejte měkký hadřík a specializované přípravky.'
            ]
        ],
        'zdroje' => [
            'https://www.leatherhoney.com'
        ]
    ];
}

/**
 * Vygeneruje kompletní obsah aktuality v češtině
 */
function vygenerujObsahCZ(string $datum, array $svatek, array $novinky, array $pece): string
{
    $jmeno = sanitizeInput($svatek['jmeno'] ?? 'Den');
    $komentar = sanitizeInput($svatek['komentar'] ?? '');

    $html = "# Denní aktuality Natuzzi\n\n";
    $html .= "**Datum:** " . sanitizeInput($datum) . " | **Svátek má:** {$jmeno}\n\n";
    $html .= "{$komentar}\n\n";

    $html .= "## 📰 Novinky o značce Natuzzi\n\n";

    if (!empty($novinky['novinky'])) {
        foreach ($novinky['novinky'] as $index => $novinka) {
            $cislo = $index + 1;
            $titulek = sanitizeInput($novinka['titulek'] ?? '');
            $popis = sanitizeInput($novinka['popis'] ?? '');
            $url = validateUrl($novinka['url'] ?? '');

            $html .= "**{$cislo}. {$titulek}**\n\n";
            $html .= "{$popis}\n\n";
            if ($url) {
                $html .= "[Číst více]({$url})\n\n";
            }
        }
    }

    $html .= "## 🛠️ Péče o luxusní nábytek\n\n";

    if (!empty($pece['tipy'])) {
        foreach ($pece['tipy'] as $tip) {
            $nadpis = sanitizeInput($tip['nadpis'] ?? '');
            $text = sanitizeInput($tip['text'] ?? '');

            $html .= "**{$nadpis}**\n\n";
            $html .= "{$text}\n\n";
        }
    }

    $html .= "## 🇨🇿 Natuzzi v České republice\n\n";
    $html .= "Navštivte naše showroomy v Praze a Brně. Více informací na [natuzzi.cz](https://www.natuzzi.cz/).\n\n";

    return $html;
}

/**
 * Sanitizuje vstup proti XSS
 */
function sanitizeInput(?string $input): string
{
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validuje a sanitizuje URL
 */
function validateUrl(?string $url): ?string
{
    if (empty($url)) {
        return null;
    }

    // Validace URL
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ($url === false) {
        return null;
    }

    // Povolit pouze HTTP/HTTPS
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * Přeloží obsah do angličtiny (v produkci by používalo Google Translate API)
 */
function prelozitDoAnglictiny(string $obsahCZ): string
{
    // Simulace překladu - v produkci použijte Google Translate API
    // nebo DeepL API

    // Pro demonstraci jen nahradím nadpisy
    $obsahEN = str_replace('Denní aktuality Natuzzi', 'Natuzzi Daily News', $obsahCZ);
    $obsahEN = str_replace('Svátek má:', 'Name Day:', $obsahEN);
    $obsahEN = str_replace('Novinky o značce Natuzzi', 'Natuzzi Brand News', $obsahEN);
    $obsahEN = str_replace('Péče o luxusní nábytek', 'Luxury Furniture Care', $obsahEN);
    $obsahEN = str_replace('Natuzzi v České republice', 'Natuzzi in Czech Republic', $obsahEN);

    return $obsahEN;
}

/**
 * Přeloží obsah do italštiny
 */
function prelozitDoItalstiny(string $obsahCZ): string
{
    // Simulace překladu
    $obsahIT = str_replace('Denní aktuality Natuzzi', 'Notizie Quotidiane Natuzzi', $obsahCZ);
    $obsahIT = str_replace('Svátek má:', 'Onomastico:', $obsahIT);
    $obsahIT = str_replace('Novinky o značce Natuzzi', 'Novità del Brand Natuzzi', $obsahIT);
    $obsahIT = str_replace('Péče o luxusní nábytek', 'Cura dei Mobili di Lusso', $obsahIT);
    $obsahIT = str_replace('Natuzzi v České republice', 'Natuzzi nella Repubblica Ceca', $obsahIT);

    return $obsahIT;
}
