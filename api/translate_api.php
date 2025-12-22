<?php
/**
 * Translate API Proxy
 * Server-side proxy pro překlad textu
 * Používá MyMemory API (zdarma, bez API klíče)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// CORS pro AJAX požadavky
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * HTTP request pomocí cURL
 */
function fetchUrl($url, $timeout = 10) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WGS Service/1.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        error_log("Translate API error: {$error}, HTTP: {$httpCode}");
        return false;
    }

    return $response;
}

try {
    // Získání vstupních dat
    $input = null;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        // JSON request
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
    } else {
        // Form data nebo GET
        $input = $_REQUEST;
    }

    // Získání parametrů
    $text = $input['text'] ?? '';
    $sourceLang = $input['source'] ?? $input['sourceLang'] ?? 'cs';
    $targetLang = $input['target'] ?? $input['targetLang'] ?? 'en';
    $engine = $input['engine'] ?? 'mymemory';

    // Validace
    if (empty($text)) {
        throw new Exception('Chybí text pro překlad');
    }

    // Limit délky textu (MyMemory má limit 500 znaků pro anonymní požadavky)
    $text = trim($text);
    if (strlen($text) > 500) {
        $text = substr($text, 0, 500);
    }

    // Sanitizace jazykových kódů
    $sourceLang = preg_replace('/[^a-z]/', '', strtolower($sourceLang));
    $targetLang = preg_replace('/[^a-z]/', '', strtolower($targetLang));

    if (empty($sourceLang)) $sourceLang = 'cs';
    if (empty($targetLang)) $targetLang = 'en';

    // ============================================
    // PRIMARY: MyMemory API (zdarma, bez klíče)
    // ============================================
    $langPair = "{$sourceLang}|{$targetLang}";
    $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
        'q' => $text,
        'langpair' => $langPair,
        'de' => 'reklamace@wgs-service.cz' // Kontaktní email pro vyšší limit
    ]);

    $response = fetchUrl($url);

    if ($response !== false) {
        $data = json_decode($response, true);

        if (isset($data['responseStatus']) && $data['responseStatus'] == 200) {
            $translated = $data['responseData']['translatedText'] ?? '';

            // MyMemory někdy vrací HTML entity
            $translated = html_entity_decode($translated, ENT_QUOTES, 'UTF-8');

            echo json_encode([
                'status' => 'success',
                'translated' => $translated,
                'source' => $text,
                'sourceLang' => $sourceLang,
                'targetLang' => $targetLang,
                'provider' => 'mymemory'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Pokud MyMemory vrátí chybu
        $errorMsg = $data['responseDetails'] ?? 'Neznámá chyba';
        error_log("MyMemory API error: {$errorMsg}");
    }

    // ============================================
    // FALLBACK: LibreTranslate (self-hosted nebo veřejný)
    // ============================================
    $libreUrl = 'https://libretranslate.com/translate';

    $ch = curl_init($libreUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'q' => $text,
        'source' => $sourceLang,
        'target' => $targetLang,
        'format' => 'text'
    ]));

    $libreResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($libreResponse !== false && $httpCode === 200) {
        $libreData = json_decode($libreResponse, true);

        if (isset($libreData['translatedText'])) {
            echo json_encode([
                'status' => 'success',
                'translated' => $libreData['translatedText'],
                'source' => $text,
                'sourceLang' => $sourceLang,
                'targetLang' => $targetLang,
                'provider' => 'libretranslate'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Pokud oba API selhaly
    throw new Exception('Překlad není momentálně dostupný');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
