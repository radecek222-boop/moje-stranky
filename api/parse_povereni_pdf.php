<?php
/**
 * API endpoint pro zpracování PDF pověření k reklamaci
 *
 * Tento endpoint přijímá text extrahovaný z PDF (z frontendu pomocí PDF.js)
 * a automaticky detekuje typ protokolu (NATUZZI, PHASE, atd.) podle konfigurace v databázi.
 *
 * NOVÁ FUNKČNOST:
 * - Načítá konfigurace parserů z databáze (wgs_pdf_parser_configs)
 * - Automaticky detekuje typ PDF podle detekčního patternu
 * - Používá score systém - vybere konfiguraci, která najde nejvíc dat
 * - Fallback na výchozí parser pokud žádná konfigurace nepasuje
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení - pouze pro přihlášené uživatele
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

try {
    $pdo = getDbConnection();

    // Získat text z PDF (již extrahovaný na frontendu pomocí PDF.js)
    $pdfText = $_POST['pdf_text'] ?? '';

    if (empty($pdfText)) {
        sendJsonError('Chybí text z PDF');
    }

    // Načíst všechny aktivní konfigurace z databáze
    $configs = nactiAktivniKonfigurace($pdo);

    if (empty($configs)) {
        // Fallback na výchozí parser pokud nejsou žádné konfigurace
        error_log("PDF Parser: Žádné konfigurace v databázi, používám fallback parser");
        $extrahovanaData = fallbackParser($pdfText);
        sendJsonSuccess('PDF zpracováno (výchozí parser)', $extrahovanaData);
    }

    // Detekovat typ PDF a najít nejlepší konfiguraci
    $nejlepsiConfig = detekujTypPDF($pdfText, $configs);

    if (!$nejlepsiConfig) {
        // Žádná konfigurace nedetekovala PDF, použít score systém
        $nejlepsiConfig = najdiNejlepsiKonfiguraci($pdfText, $configs);
    }

    if ($nejlepsiConfig) {
        error_log("PDF Parser: Použita konfigurace '{$nejlepsiConfig['nazev']}' (ID: {$nejlepsiConfig['config_id']})");
        $extrahovanaData = parsujPodleKonfigurace($pdfText, $nejlepsiConfig);

        sendJsonSuccess('PDF zpracováno (' . $nejlepsiConfig['nazev'] . ')', [
            'data' => $extrahovanaData,
            'config_name' => $nejlepsiConfig['nazev'],
            'config_id' => $nejlepsiConfig['config_id']
        ]);
    } else {
        // Fallback parser
        error_log("PDF Parser: Žádná konfigurace nevyhovuje, používám fallback");
        $extrahovanaData = fallbackParser($pdfText);
        sendJsonSuccess('PDF zpracováno (fallback)', $extrahovanaData);
    }

} catch (Exception $e) {
    error_log("Chyba při parsování PDF: " . $e->getMessage());
    sendJsonError('Chyba při zpracování PDF');
}

/**
 * Načte všechny aktivní konfigurace z databáze seřazené podle priority
 */
function nactiAktivniKonfigurace($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM wgs_pdf_parser_configs
        WHERE aktivni = 1
        ORDER BY priorita DESC, config_id ASC
    ");

    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dekódovat JSON pole
    foreach ($configs as &$config) {
        $config['regex_patterns'] = json_decode($config['regex_patterns'], true);
        $config['pole_mapping'] = json_decode($config['pole_mapping'], true);
    }

    return $configs;
}

/**
 * Detekuje typ PDF podle detekčního patternu
 * Vrátí první konfiguraci, jejíž detekční pattern matchuje
 */
function detekujTypPDF($text, $configs) {
    foreach ($configs as $config) {
        $detekcePattern = $config['detekce_pattern'];

        if (preg_match($detekcePattern, $text)) {
            return $config;
        }
    }

    return null;
}

/**
 * Najde konfiguraci, která extrahuje nejvíc dat (score systém)
 */
function najdiNejlepsiKonfiguraci($text, $configs) {
    $nejlepsiConfig = null;
    $nejlepsiScore = 0;

    foreach ($configs as $config) {
        $score = 0;

        // Zkusit parsovat každým regex patternem a spočítat kolik jich matchuje
        foreach ($config['regex_patterns'] as $klic => $pattern) {
            if (preg_match($pattern, $text)) {
                $score++;
            }
        }

        if ($score > $nejlepsiScore) {
            $nejlepsiScore = $score;
            $nejlepsiConfig = $config;
        }
    }

    return $nejlepsiConfig;
}

/**
 * Parsuje PDF podle zadané konfigurace
 */
function parsujPodleKonfigurace($text, $config) {
    $data = [];
    $regexPatterns = $config['regex_patterns'];
    $poleMapping = $config['pole_mapping'];

    // Pro každý regex pattern zkusit extrahovat data
    foreach ($regexPatterns as $klic => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $hodnota = isset($matches[1]) ? trim($matches[1]) : '';

            // Mapovat na správný klíč pole ve formuláři
            if (isset($poleMapping[$klic])) {
                $cilovePoloe = $poleMapping[$klic];
                $data[$cilovePoloe] = htmlspecialchars($hodnota, ENT_QUOTES, 'UTF-8');
            }
        }
    }

    return $data;
}

/**
 * Fallback parser - použije se když žádná konfigurace nevyhovuje
 * Používá obecné regex vzory
 */
function fallbackParser($text) {
    $data = [
        'cislo_objednavky_reklamace' => '',
        'datum_prodeje' => '',
        'datum_reklamace' => '',
        'jmeno' => '',
        'email' => '',
        'telefon' => '',
        'ulice' => '',
        'mesto' => '',
        'psc' => '',
        'model' => '',
        'provedeni' => '',
        'barva' => '',
        'popis_problemu' => '',
        'doplnujici_info' => ''
    ];

    // Email - univerzální pattern
    if (preg_match('/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches)) {
        $data['email'] = trim($matches[1]);
    }

    // Telefon - české/slovenské formáty
    if (preg_match('/(?:telefon|tel|mobil|telefón)[:\s]*([\+\d\s]+)/ui', $text, $matches)) {
        $data['telefon'] = preg_replace('/\s+/', ' ', trim($matches[1]));
    }

    // PSČ - 5 číslic s/bez mezery
    if (preg_match('/(?:PSČ|psc)[:\s]*(\d{3}\s?\d{2})/ui', $text, $matches)) {
        $data['psc'] = trim($matches[1]);
    }

    // Datum (formát DD.MM.RRRR)
    if (preg_match('/(?:datum|dátum)[:\s]*(\d{1,2}\.\d{1,2}\.\d{4})/ui', $text, $matches)) {
        $data['datum_reklamace'] = trim($matches[1]);
    }

    // Jméno (dvě slova s velkými písmeny)
    if (preg_match('/(?:jméno|meno|zákazník)[:\s]*([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui', $text, $matches)) {
        $data['jmeno'] = trim($matches[1]);
    }

    // Model
    if (preg_match('/(?:model)[:\s]*([A-Z0-9][A-Za-z0-9\s\-]+)/ui', $text, $matches)) {
        $data['model'] = trim($matches[1]);
    }

    // Sanitizace všech dat
    foreach ($data as $key => $value) {
        $data[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    return $data;
}
?>
