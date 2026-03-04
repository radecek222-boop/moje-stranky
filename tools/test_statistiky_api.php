<?php
/**
 * Test statistiky API - přímé volání bez cache
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// Simulovat GET parametry jako by to volal frontend
$_GET['action'] = 'zakazky';
$_GET['stranka'] = '1';

// Vymazat output buffering
while (ob_get_level()) {
    ob_end_clean();
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Statistiky API</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        pre { background: #000; padding: 20px; border: 1px solid #0f0; overflow-x: auto; }
        h1 { color: #0f0; }
    </style>
</head>
<body>
<h1>RAW JSON odpověď z statistiky API:</h1>
<pre>";

// Zachytit output z API
ob_start();
include __DIR__ . '/api/statistiky_api.php';
$apiOutput = ob_get_clean();

// Zobrazit pretty-printed JSON
$jsonData = json_decode($apiOutput, true);
if ($jsonData) {
    echo json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo "CHYBA: Neplatný JSON\n\n";
    echo htmlspecialchars($apiOutput);
}

echo "</pre>

<h2>Hledání GREY M v datech:</h2>
<pre>";

if ($jsonData && isset($jsonData['data']['zakazky'])) {
    $nalezeno = false;
    foreach ($jsonData['data']['zakazky'] as $zakazka) {
        if (stripos($zakazka['cislo_reklamace'], 'GREY') !== false) {
            echo "✓ NALEZENO:\n";
            echo json_encode($zakazka, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $nalezeno = true;
            break;
        }
    }
    if (!$nalezeno) {
        echo "⚠ GREY M nenalezen v aktuálních filtrech";
    }
} else {
    echo "⚠ Žádná data zakázek";
}

echo "</pre>
</body>
</html>";
?>
