<?php
/**
 * Test SMTP připojení
 *
 * Otestuje, jestli server může dosáhnout na SMTP server
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Test SMTP připojení</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     max-width: 1200px; margin: 0 auto; }
        h1 { color: #2D5016; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px;
              overflow-x: auto; }
        .test { margin: 20px 0; padding: 15px; border: 1px solid #ddd;
                border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Test SMTP připojení</h1>";

// Test 1: DNS resolving
echo "<div class='test'>";
echo "<h2>TEST 1: DNS Resolving</h2>";
echo "<p>Zkouším zjistit IP adresu SMTP serveru...</p>";

$smtpHost = 'smtp.ceskyhosting.cz';
$ip = gethostbyname($smtpHost);

if ($ip !== $smtpHost) {
    echo "<p class='success'>✅ DNS funguje: $smtpHost → $ip</p>";
} else {
    echo "<p class='error'>❌ DNS selhalo: Nelze zjistit IP adresu pro $smtpHost</p>";
}
echo "</div>";

// Test 2: fsockopen test (port 587)
echo "<div class='test'>";
echo "<h2>TEST 2: Připojení na port 587 (SMTP s TLS)</h2>";
echo "<p>Zkouším se připojit na $smtpHost:587...</p>";

$errno = 0;
$errstr = '';
$timeout = 10;

$socket = @fsockopen($smtpHost, 587, $errno, $errstr, $timeout);

if ($socket) {
    echo "<p class='success'>✅ Připojení na port 587 ÚSPĚŠNÉ!</p>";

    // Přečíst uvítací zprávu
    $response = fgets($socket, 1024);
    echo "<p class='info'>📨 Uvítací zpráva serveru:</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    fclose($socket);
} else {
    echo "<p class='error'>❌ Připojení SELHALO!</p>";
    echo "<p class='error'>Error $errno: $errstr</p>";

    echo "<p class='info'>🔍 Možné příčiny:</p>";
    echo "<ul>";
    echo "<li>Firewall blokuje odchozí připojení na port 587</li>";
    echo "<li>SMTP server není dostupný z tohoto hostingu</li>";
    echo "<li>Hosting poskytovatel blokuje SMTP připojení</li>";
    echo "</ul>";
}
echo "</div>";

// Test 3: Alternativní porty
echo "<div class='test'>";
echo "<h2>TEST 3: Alternativní porty</h2>";
echo "<p>Zkouším další běžné SMTP porty...</p>";

$ports = [25, 465, 587, 2525];
$results = [];

foreach ($ports as $port) {
    $socket = @fsockopen($smtpHost, $port, $errno, $errstr, 5);
    if ($socket) {
        echo "<p class='success'>✅ Port $port: OTEVŘENÝ</p>";
        fclose($socket);
        $results[$port] = true;
    } else {
        echo "<p class='error'>❌ Port $port: ZAVŘENÝ (Error $errno: $errstr)</p>";
        $results[$port] = false;
    }
}
echo "</div>";

// Test 4: Test jiných SMTP serverů (pro srovnání)
echo "<div class='test'>";
echo "<h2>TEST 4: Test jiných SMTP serverů (pro srovnání)</h2>";
echo "<p>Zkouším se připojit na veřejné SMTP servery...</p>";

$testServers = [
    'smtp.gmail.com:587' => 'Gmail SMTP',
    'smtp.seznam.cz:587' => 'Seznam SMTP',
    'smtp.office365.com:587' => 'Office365 SMTP'
];

foreach ($testServers as $server => $name) {
    list($host, $port) = explode(':', $server);
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($socket) {
        echo "<p class='success'>✅ $name: DOSTUPNÝ</p>";
        fclose($socket);
    } else {
        echo "<p class='error'>❌ $name: NEDOSTUPNÝ</p>";
    }
}

echo "<p class='info'>💡 Pokud žádný SMTP server nefunguje, hosting pravděpodobně blokuje všechna odchozí SMTP připojení.</p>";
echo "</div>";

// Doporučení
echo "<div class='test'>";
echo "<h2>📋 DOPORUČENÍ</h2>";

if (isset($results[587]) && $results[587]) {
    echo "<p class='success'>✅ Port 587 funguje! SMTP by měl fungovat.</p>";
    echo "<p>Pokud stále nejde odeslat email, zkontroluj:</p>";
    echo "<ul>";
    echo "<li>SMTP přihlašovací údaje (username, heslo)</li>";
    echo "<li>Otestuj odeslání emailu přes: <a href='/diagnoza_smtp.php'>diagnoza_smtp.php</a></li>";
    echo "</ul>";
} elseif (isset($results[465]) && $results[465]) {
    echo "<p class='info'>⚠️ Port 465 (SSL) funguje, ale 587 (TLS) ne.</p>";
    echo "<p>Zkus změnit konfiguraci:</p>";
    echo "<ul>";
    echo "<li>Port: <strong>465</strong></li>";
    echo "<li>Šifrování: <strong>ssl</strong></li>";
    echo "</ul>";
} elseif (isset($results[25]) && $results[25]) {
    echo "<p class='info'>⚠️ Port 25 funguje, ale je zastaralý a může mít SPF problémy.</p>";
} else {
    echo "<p class='error'>❌ ŽÁDNÝ SMTP PORT NEFUNGUJE!</p>";
    echo "<p><strong>Možná řešení:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Kontaktuj hosting support:</strong> Požádej o povolení SMTP na portu 587</li>";
    echo "<li><strong>Použij jiný SMTP server:</strong> Gmail, SendGrid, Mailgun</li>";
    echo "<li><strong>Použij hosting SMTP:</strong> Zeptej se na SMTP server vašeho hostingu</li>";
    echo "</ol>";
}
echo "</div>";

echo "<hr>";
echo "<p><a href='/admin.php'>← Zpět na Admin</a></p>";

echo "</div></body></html>";
?>
