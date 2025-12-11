<?php
/**
 * Test Google Translate API
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

echo "<pre style='background:#1a1a1a;color:#fff;padding:20px;font-family:monospace;'>";
echo "=== TEST GOOGLE TRANSLATE API ===\n\n";

$testText = "Dobrý den, jak se máte?";
$targetLang = 'en';

$url = 'https://translate.googleapis.com/translate_a/single';
$params = [
    'client' => 'gtx',
    'sl' => 'cs',
    'tl' => $targetLang,
    'dt' => 't',
    'q' => $testText
];

$fullUrl = $url . '?' . http_build_query($params);

echo "Test text: {$testText}\n";
echo "Cilovy jazyk: {$targetLang}\n";
echo "URL: {$fullUrl}\n\n";

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
    echo htmlspecialchars(substr($response, 0, 1000)) . "\n\n";

    $data = json_decode($response, true);

    if (!$data) {
        echo "CHYBA: Nelze parsovat JSON!\n";
        echo "JSON error: " . json_last_error_msg() . "\n";
    } else {
        echo "=== PARSED DATA ===\n";
        print_r($data);

        echo "\n=== EXTRAHOVANY PREKLAD ===\n";
        if (isset($data[0])) {
            $preklad = '';
            foreach ($data[0] as $segment) {
                if (isset($segment[0])) {
                    $preklad .= $segment[0];
                }
            }
            echo "Preklad: {$preklad}\n";
            echo "Puvodne: {$testText}\n";
            echo "Stejne: " . ($preklad === $testText ? 'ANO (PROBLEM!)' : 'NE (OK)') . "\n";
        } else {
            echo "CHYBA: Chybi data[0]!\n";
        }
    }
}

echo "</pre>";
?>
