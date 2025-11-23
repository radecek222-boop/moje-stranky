<?php
/**
 * Test User Scores API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Simulace admin session
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = generateCSRFToken();

echo "<h1>Test User Scores API</h1>";
echo "<pre>";

$url = "http://" . $_SERVER['HTTP_HOST'] . "/api/analytics_user_scores.php?action=get_scores&csrf_token=" . urlencode($_SESSION['csrf_token']);

echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "HTTP Status: $httpCode\n\n";
echo "Response Body:\n";
echo $body . "\n";

if ($httpCode === 400) {
    echo "\n❌ HTTP 400 - pravděpodobně chybí parametr nebo CSRF error\n";
}

echo "</pre>";
