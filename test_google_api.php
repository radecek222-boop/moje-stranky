<?php
/**
 * Test MyMemory Translate API
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<pre style='background:#1a1a1a;color:#fff;padding:20px;font-family:monospace;'>";
echo "=== TEST MYMEMORY TRANSLATE API ===\n\n";

$testText = "Dobrý den, jak se máte?";
$targetLang = 'en';

$url = 'https://api.mymemory.translated.net/get';
$params = [
    'q' => $testText,
    'langpair' => 'cs|' . $targetLang,
    'de' => 'info@wgs-service.cz'
];

$fullUrl = $url . '?' . http_build_query($params);

echo "Test text: {$testText}\n";
echo "Cilovy jazyk: {$targetLang}\n";
echo "URL: {$fullUrl}\n\n";

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

echo "Volam API...\n";
$startTime = microtime(true);
$response = @file_get_contents($fullUrl, false, $context);
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000);

echo "Cas: {$duration}ms\n\n";

if ($response === false) {
    echo "CHYBA: Pozadavek selhal!\n";
    echo "HTTP response headers:\n";
    print_r($http_response_header ?? 'N/A');
} else {
    echo "=== RAW RESPONSE ===\n";
    echo htmlspecialchars(substr($response, 0, 2000)) . "\n\n";

    $data = json_decode($response, true);

    if (!$data) {
        echo "CHYBA: Nelze parsovat JSON!\n";
        echo "JSON error: " . json_last_error_msg() . "\n";
    } else {
        echo "=== PARSED DATA ===\n";
        echo "responseStatus: " . ($data['responseStatus'] ?? 'N/A') . "\n";
        echo "translatedText: " . ($data['responseData']['translatedText'] ?? 'N/A') . "\n";
        echo "match: " . ($data['responseData']['match'] ?? 'N/A') . "\n\n";

        if (isset($data['responseData']['translatedText'])) {
            $preklad = html_entity_decode($data['responseData']['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            echo "=== VYSLEDEK ===\n";
            echo "Puvodni: {$testText}\n";
            echo "Preklad: {$preklad}\n";
            echo "Stejne: " . ($preklad === $testText ? 'ANO (PROBLEM!)' : 'NE (OK - preklad funguje!)') . "\n";
        }
    }
}

echo "\n=== TEST IT ===\n";
$paramsIt = [
    'q' => $testText,
    'langpair' => 'cs|it',
    'de' => 'info@wgs-service.cz'
];
$urlIt = $url . '?' . http_build_query($paramsIt);
$responseIt = @file_get_contents($urlIt, false, $context);
if ($responseIt) {
    $dataIt = json_decode($responseIt, true);
    if (isset($dataIt['responseData']['translatedText'])) {
        $prekladIt = html_entity_decode($dataIt['responseData']['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        echo "Preklad do IT: {$prekladIt}\n";
    }
}

echo "</pre>";
?>
