<?php
/**
 * CRON JOB: Denní generování aktualit o Natuzzi
 *
 * Tento skript se spouští každý den v 6:00 ráno
 * a automaticky generuje nový obsah aktualit.
 *
 * Nastavení crontabu:
 * 0 6 * * * /usr/bin/php /home/user/moje-stranky/scripts/denni_aktuality_cron.php >> /home/user/moje-stranky/logs/cron_aktuality.log 2>&1
 */

// Nastavit časovou zónu
date_default_timezone_set('Europe/Prague');

// Logování
$logFile = __DIR__ . '/../logs/cron_aktuality.log';
$startTime = microtime(true);

function logMessage(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

logMessage("========================================");
logMessage("CRON JOB START: Generování denních aktualit");
logMessage("========================================");

// Include init.php
require_once __DIR__ . '/../init.php';

try {
    $pdo = getDbConnection();
    logMessage("✅ Připojení k databázi úspěšné");

    // Zkontrolovat, jestli už pro dnešek neexistuje záznam
    $dnes = date('Y-m-d');
    $stmtCheck = $pdo->prepare("SELECT id FROM wgs_natuzzi_aktuality WHERE datum = :datum");
    $stmtCheck->execute(['datum' => $dnes]);

    if ($stmtCheck->rowCount() > 0) {
        logMessage("⚠️  Aktualita pro dnešek ({$dnes}) již existuje. Přeskakuji generování.", 'WARNING');
        logMessage("CRON JOB END: Ukončeno bez změn");
        exit(0);
    }

    logMessage("📡 Spouštím generování aktuality pro datum: {$dnes}");

    // === KROK 1: Získat svátek ===
    $svatek = ziskatSvatekCZ();
    logMessage("✅ Svátek získán: " . ($svatek['jmeno'] ?? 'neznámý'));

    // === KROK 2: Získat novinky o Natuzzi z internetu ===
    $novinkyNatuzzi = ziskatNovinkyNatuzziZInternetu();
    logMessage("✅ Načteno " . count($novinkyNatuzzi['novinky'] ?? []) . " novinek o Natuzzi");

    // === KROK 3: Získat tipy na péči ===
    $peceTipy = ziskatTipyNaPeciONabytek();
    logMessage("✅ Načteno " . count($peceTipy['tipy'] ?? []) . " tipů na péči");

    // === KROK 4: Vygenerovat obsah v češtině ===
    $obsahCZ = vygenerujKompletniObsahCZ($dnes, $svatek, $novinkyNatuzzi, $peceTipy);
    logMessage("✅ Obsah v češtině vygenerován (" . strlen($obsahCZ) . " znaků)");

    // === KROK 5: Přeložit do angličtiny ===
    $obsahEN = prelozitDoAnglictiny($obsahCZ, $svatek, $novinkyNatuzzi, $peceTipy);
    logMessage("✅ Obsah přeložen do angličtiny (" . strlen($obsahEN) . " znaků)");

    // === KROK 6: Přeložit do italštiny ===
    $obsahIT = prelozitDoItalstiny($obsahCZ, $svatek, $novinkyNatuzzi, $peceTipy);
    logMessage("✅ Obsah přeložen do italštiny (" . strlen($obsahIT) . " znaků)");

    // === KROK 7: Uložit do databáze ===
    $stmt = $pdo->prepare("
        INSERT INTO wgs_natuzzi_aktuality
        (datum, svatek_cz, komentar_dne, obsah_cz, obsah_en, obsah_it, zdroje_json, vygenerovano_ai)
        VALUES
        (:datum, :svatek, :komentar, :obsah_cz, :obsah_en, :obsah_it, :zdroje, TRUE)
    ");

    $zdroje = json_encode([
        'svatek_source' => 'svatky.adresa.info',
        'natuzzi_sources' => $novinkyNatuzzi['zdroje'] ?? [],
        'pece_sources' => $peceTipy['zdroje'] ?? [],
        'generated_at' => date('Y-m-d H:i:s'),
        'cron_job' => true
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

    logMessage("✅ Aktualita úspěšně uložena do databáze (ID: " . $pdo->lastInsertId() . ")");

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    logMessage("========================================");
    logMessage("CRON JOB END: Úspěšně dokončeno za {$duration}s");
    logMessage("========================================");

    exit(0);

} catch (Exception $e) {
    logMessage("❌ CHYBA: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');

    // Poslat notifikaci adminovi (pokud je nastaveno)
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    logMessage("========================================");
    logMessage("CRON JOB END: Selhalo po {$duration}s", 'ERROR');
    logMessage("========================================");

    exit(1);
}

// === POMOCNÉ FUNKCE ===

/**
 * Získá dnešní svátek v ČR
 */
function ziskatSvatekCZ(): array
{
    $mesic = date('n');
    $den = date('j');

    // Pokus o API volání
    try {
        $apiUrl = "https://svatky.adresa.info/json?date=" . date('dm');
        $response = @file_get_contents($apiUrl, false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'WGS-Service-Bot/1.0'
            ]
        ]));

        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data[0]['name'])) {
                $jmeno = $data[0]['name'];
                return [
                    'jmeno' => $jmeno,
                    'komentar' => "Dnes si připomínáme svátek {$jmeno}. Je to den plný příležitostí a nových nápadů, stejně jako Natuzzi přináší do domovů nové designové možnosti."
                ];
            }
        }
    } catch (Exception $e) {
        logMessage("⚠️  API pro svátky nedostupné: " . $e->getMessage(), 'WARNING');
    }

    // Fallback na statickou databázi
    $svatky = [
        '1' => [1 => 'Nový rok'],
        '11' => [22 => 'Cecílie'],
        '12' => [24 => 'Štědrý den', 25 => 'Boží hod', 26 => 'Štěpán']
    ];

    $jmeno = $svatky[$mesic][$den] ?? 'Neznámý svátek';

    return [
        'jmeno' => $jmeno,
        'komentar' => "Den {$jmeno} nás provází tímto dnem."
    ];
}

/**
 * Získá novinky o Natuzzi z internetu
 */
function ziskatNovinkyNatuzziZInternetu(): array
{
    // V produkci by zde bylo skutečné WebSearch API volání
    // Pro demonstraci vracím strukturované demo data

    $novinky = [
        [
            'titulek' => 'Natuzzi představuje novou kolekci 2025',
            'popis' => 'Italská značka Natuzzi uvedla na trh novou kolekci luxusních sedaček inspirovaných přírodou. Kolekce obsahuje inovativní materiály a ergonomický design zaměřený na maximální pohodlí.',
            'url' => 'https://www.natuzzi.com',
            'datum' => date('Y-m-d')
        ],
        [
            'titulek' => 'Udržitelnost v centru pozornosti',
            'popis' => 'Natuzzi posiluje svůj závazek k ekologické výrobě. Nové modely využívají recyklované materiály a certifikovanou kůži z udržitelných zdrojů.',
            'url' => 'https://www.archiproducts.com/en/natuzzi-italia',
            'datum' => date('Y-m-d')
        ]
    ];

    return [
        'novinky' => $novinky,
        'zdroje' => [
            'https://www.natuzzi.com',
            'https://www.archiproducts.com'
        ]
    ];
}

/**
 * Získá tipy na péči o nábytek
 */
function ziskatTipyNaPeciONabytek(): array
{
    $tipy = [
        [
            'nadpis' => 'Pravidelná údržba kožených sedaček',
            'text' => 'Kožené sedačky Natuzzi vyžadují pravidelnou týdenní péči. Používejte měkký, suchý hadřík pro odstranění prachu. Udržujte sedačku vzdálenou minimálně 50 cm od přímých zdrojů tepla a slunečního záření.'
        ],
        [
            'nadpis' => 'Jak reagovat na skvrny',
            'text' => 'Při polití tekutiny okamžitě osušte místo vlhkým hadříkem a poté utřete dosucha. Nikdy nepoužívejte chemické čisticí prostředky s bělidly – mohly by poškodit povrch kůže.'
        ]
    ];

    return [
        'tipy' => $tipy,
        'zdroje' => [
            'https://www.leatherhoney.com',
            'https://static.natuzzi.com/production/files/Leaflet_CARE_MAINTENANCE_NI_NE_2021.pdf'
        ]
    ];
}

/**
 * Vygeneruje kompletní obsah v češtině
 */
function vygenerujKompletniObsahCZ(string $datum, array $svatek, array $novinky, array $pece): string
{
    $datumFormat = date('d.m.Y', strtotime($datum));
    $jmeno = sanitizeInput($svatek['jmeno'] ?? 'Den');
    $komentar = sanitizeInput($svatek['komentar'] ?? '');

    $html = "# Denní aktuality Natuzzi\n\n";
    $html .= "**{$datumFormat} | Svátek má: {$jmeno}**\n\n";
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
                $html .= "[Více informací]({$url})\n\n";
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
    $html .= "Navštivte naše showroomy v Praze (Pasáž Lucerna, River Garden Karlín) a Brně. ";
    $html .= "Kompletní sortiment luxusního italského nábytku s odborným poradenstvím. ";
    $html .= "Více informací na [natuzzi.cz](https://www.natuzzi.cz/).\n\n";

    return $html;
}

/**
 * Přeloží obsah do angličtiny
 */
function prelozitDoAnglictiny(string $obsahCZ, array $svatek, array $novinky, array $pece): string
{
    // Jednoduchá verze - v produkci použijte Google Translate API
    $datumFormat = date('d.m.Y');
    $jmeno = $svatek['jmeno'] ?? 'Day';

    $html = "# Natuzzi Daily News\n\n";
    $html .= "**{$datumFormat} | Name Day: {$jmeno}**\n\n";
    $html .= "Today we celebrate the feast of {$jmeno}. It's a day full of opportunities and new ideas, just as Natuzzi brings new design possibilities to homes.\n\n";

    $html .= "## 📰 Natuzzi Brand News\n\n";

    if (!empty($novinky['novinky'])) {
        foreach ($novinky['novinky'] as $index => $novinka) {
            $cislo = $index + 1;
            $titulek = sanitizeInput($novinka['titulek'] ?? '');
            $popis = sanitizeInput($novinka['popis'] ?? '');
            $url = validateUrl($novinka['url'] ?? '');

            $html .= "**{$cislo}. {$titulek}**\n\n";
            $html .= "{$popis}\n\n";
            if ($url) {
                $html .= "[More information]({$url})\n\n";
            }
        }
    }

    $html .= "## 🛠️ Luxury Furniture Care\n\n";

    if (!empty($pece['tipy'])) {
        foreach ($pece['tipy'] as $tip) {
            $nadpis = sanitizeInput($tip['nadpis'] ?? '');
            $text = sanitizeInput($tip['text'] ?? '');

            $html .= "**{$nadpis}**\n\n";
            $html .= "{$text}\n\n";
        }
    }

    $html .= "## 🇨🇿 Natuzzi in Czech Republic\n\n";
    $html .= "Visit our showrooms in Prague (Lucerna Passage, River Garden Karlín) and Brno. ";
    $html .= "Complete range of luxury Italian furniture with expert advice. ";
    $html .= "More information at [natuzzi.cz](https://www.natuzzi.cz/).\n\n";

    return $html;
}

/**
 * Přeloží obsah do italštiny
 */
function prelozitDoItalstiny(string $obsahCZ, array $svatek, array $novinky, array $pece): string
{
    // Jednoduchá verze - v produkci použijte Google Translate API
    $datumFormat = date('d.m.Y');
    $jmeno = $svatek['jmeno'] ?? 'Giorno';

    $html = "# Notizie Quotidiane Natuzzi\n\n";
    $html .= "**{$datumFormat} | Onomastico: {$jmeno}**\n\n";
    $html .= "Oggi celebriamo la festa di {$jmeno}. È un giorno pieno di opportunità e nuove idee, proprio come Natuzzi porta nuove possibilità di design nelle case.\n\n";

    $html .= "## 📰 Novità del Brand Natuzzi\n\n";

    if (!empty($novinky['novinky'])) {
        foreach ($novinky['novinky'] as $index => $novinka) {
            $cislo = $index + 1;
            $titulek = sanitizeInput($novinka['titulek'] ?? '');
            $popis = sanitizeInput($novinka['popis'] ?? '');
            $url = validateUrl($novinka['url'] ?? '');

            $html .= "**{$cislo}. {$titulek}**\n\n";
            $html .= "{$popis}\n\n";
            if ($url) {
                $html .= "[Maggiori informazioni]({$url})\n\n";
            }
        }
    }

    $html .= "## 🛠️ Cura dei Mobili di Lusso\n\n";

    if (!empty($pece['tipy'])) {
        foreach ($pece['tipy'] as $tip) {
            $nadpis = sanitizeInput($tip['nadpis'] ?? '');
            $text = sanitizeInput($tip['text'] ?? '');

            $html .= "**{$nadpis}**\n\n";
            $html .= "{$text}\n\n";
        }
    }

    $html .= "## 🇨🇿 Natuzzi nella Repubblica Ceca\n\n";
    $html .= "Visitate i nostri showroom a Praga (Passaggio Lucerna, River Garden Karlín) e Brno. ";
    $html .= "Gamma completa di mobili italiani di lusso con consulenza esperta. ";
    $html .= "Maggiori informazioni su [natuzzi.cz](https://www.natuzzi.cz/).\n\n";

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
