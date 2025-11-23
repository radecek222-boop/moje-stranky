<?php
/**
 * Test Analytics API
 * Diagnostika proč se data nenačítají
 */

require_once __DIR__ . '/init.php';

// Simulovat admin session
$_SESSION['is_admin'] = true;

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test Analytics API</title>
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 50px auto; padding: 20px; }
        h1 { color: #333333; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<h1>🔍 Test Analytics API</h1>";

// Test 1: Kontrola tabulky
echo "<h2>Test 1: Kontrola existence tabulky</h2>";
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");

    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Tabulka wgs_pageviews existuje</p>";

        // Spočítat záznamy
        $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM wgs_pageviews");
        $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>📊 Počet záznamů: <strong>$count</strong></p>";
    } else {
        echo "<p class='error'>❌ Tabulka wgs_pageviews neexistuje</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Test API volání
echo "<h2>Test 2: Simulace API volání</h2>";

$_GET['period'] = 'week';

try {
    ob_start();
    include __DIR__ . '/api/analytics_api.php';
    $apiOutput = ob_get_clean();

    echo "<p class='success'>✅ API odpovědělo</p>";
    echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";

    // Parsovat JSON
    $data = json_decode($apiOutput, true);

    if ($data) {
        echo "<h3>Parsovaná data:</h3>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    } else {
        echo "<p class='error'>❌ Nelze parsovat JSON</p>";
    }

} catch (Exception $e) {
    $output = ob_get_clean();
    echo "<p class='error'>❌ Chyba při volání API: " . htmlspecialchars($e->getMessage()) . "</p>";
    if ($output) {
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
}

// Test 3: JavaScript fetch simulace
echo "<h2>Test 3: JavaScript Fetch Test</h2>";
echo "<button onclick='testFetch()'>Otestovat Fetch</button>";
echo "<div id='fetch-result'></div>";

echo "
<script>
async function testFetch() {
    const resultDiv = document.getElementById('fetch-result');
    resultDiv.innerHTML = '<p>Načítání...</p>';

    try {
        const response = await fetch('/api/analytics_api.php?period=week');

        resultDiv.innerHTML = '<p class=\"success\">✅ Response status: ' + response.status + '</p>';

        const text = await response.text();
        resultDiv.innerHTML += '<h4>Raw response:</h4><pre>' + text + '</pre>';

        try {
            const data = JSON.parse(text);
            resultDiv.innerHTML += '<h4>Parsed JSON:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        } catch (e) {
            resultDiv.innerHTML += '<p class=\"error\">❌ JSON parse error: ' + e.message + '</p>';
        }

    } catch (error) {
        resultDiv.innerHTML = '<p class=\"error\">❌ Fetch error: ' + error.message + '</p>';
    }
}
</script>
";

echo "</body></html>";
?>
