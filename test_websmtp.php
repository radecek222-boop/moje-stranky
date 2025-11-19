<?php
/**
 * Test WebSMTP připojení
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test WebSMTP</title>
<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}
.success{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}
pre{background:#f4f4f4;padding:15px;border-radius:5px;}</style></head><body>";

echo "<h1>🔍 Test WebSMTP (websmtp.cesky-hosting.cz)</h1>";

// Test DNS
$host = 'websmtp.cesky-hosting.cz';
$ip = gethostbyname($host);
echo "<h2>TEST 1: DNS</h2>";
echo ($ip !== $host) ? "<p class='success'>✅ DNS: $host → $ip</p>" : "<p class='error'>❌ DNS selhal</p>";

// Test portů
$ports = [25, 587, 465];
echo "<h2>TEST 2: Testy portů</h2>";
$workingPort = null;

foreach ($ports as $port) {
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);

    if ($socket) {
        echo "<p class='success'>✅ Port $port: FUNGUJE!</p>";
        $response = fgets($socket, 1024);
        echo "<pre>Server odpověď: " . htmlspecialchars($response) . "</pre>";
        fclose($socket);
        if (!$workingPort) $workingPort = $port;
    } else {
        echo "<p class='error'>❌ Port $port: Nefunguje (Error $errno: $errstr)</p>";
    }
}

if ($workingPort) {
    echo "<hr><h2>✅ ŘEŠENÍ NALEZENO!</h2>";
    echo "<p class='success'>WebSMTP funguje na portu <strong>$workingPort</strong>!</p>";
    echo "<p>Nastav SMTP konfiguraci:</p>";
    echo "<pre>Host: websmtp.cesky-hosting.cz
Port: $workingPort
Username: reklamace@wgs-service.cz (nebo prázdné)
Password: (heslo nebo prázdné)
Šifrování: " . ($workingPort == 25 ? "žádné" : "tls") . "</pre>";

    echo "<p><a href='/oprav_smtp_na_websmtp.php'>→ AUTOMATICKY OPRAVIT KONFIGURACI</a></p>";
} else {
    echo "<hr><p class='error'>❌ WebSMTP také nefunguje</p>";
}

echo "<hr><a href='/admin.php'>← Zpět</a></body></html>";
?>
