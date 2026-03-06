<?php
// Pouze pro admina
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen');
}

header('Content-Type: text/plain; charset=utf-8');

// === TEST 1: DB připojení ===
$zacatek = microtime(true);
$pdo = getDbConnection();
$cas1 = round((microtime(true) - $zacatek) * 1000, 2);

$zacatek2 = microtime(true);
getDbConnection();
$cas2 = round((microtime(true) - $zacatek2) * 1000, 3);

$zacatekDotaz = microtime(true);
$pdo->query("SELECT 1");
$casDotaz = round((microtime(true) - $zacatekDotaz) * 1000, 2);

$persistentni = $pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'ANO' : 'NE';

echo "=== TEST 1: DB PŘIPOJENÍ ===\n";
echo "1. volání getDbConnection():  {$cas1} ms\n";
echo "2. volání getDbConnection():  {$cas2} ms  (mělo být ~0)\n";
echo "Dotaz SELECT 1:               {$casDotaz} ms\n";
echo "Persistentní spojení:         {$persistentni}\n";
echo "DB host:                      " . DB_HOST . "\n\n";

// === TEST 2: Session lock – paralelní requesty ===
echo "=== TEST 2: SESSION LOCK (paralelní requesty) ===\n";

// Simulace: jak dlouho trvá session_write_close vs ponechání session otevřené
$sessionStav = session_status() === PHP_SESSION_ACTIVE ? 'AKTIVNÍ' : 'ZAVŘENÁ';
echo "Session stav:                 {$sessionStav}\n";

// Otestovat čas načtení load.php (1 request)
$ch = curl_init();
$sessionName = session_name();
$sessionId   = session_id();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://' . $_SERVER['HTTP_HOST'] . '/app/controllers/load.php?status=all&_t=' . time(),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_COOKIE         => "$sessionName=$sessionId",
    CURLOPT_SSL_VERIFYPEER => false,
]);

$casLoad = microtime(true);
$odpoved = curl_exec($ch);
$casLoadMs = round((microtime(true) - $casLoad) * 1000);
$httpKod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($odpoved, true);
$pocetZaznamu = $data['count'] ?? '?';

echo "load.php (1 request):         {$casLoadMs} ms  [HTTP {$httpKod}, {$pocetZaznamu} záznamů]\n\n";

// Paralelní test: spustit 3 requesty najednou (jako to dělá seznam.php)
$urls = [
    'load'    => 'https://' . $_SERVER['HTTP_HOST'] . '/app/controllers/load.php?status=all&_t=' . time(),
    'notes'   => 'https://' . $_SERVER['HTTP_HOST'] . '/api/notes_api.php?action=get_unread_counts&_t=' . time(),
    'nabidka' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/nabidka_api.php?action=emaily_s_nabidkou&_t=' . time(),
];

$mh = curl_multi_init();
$handles = [];
foreach ($urls as $klic => $url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_COOKIE         => "$sessionName=$sessionId",
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$klic] = $ch;
}

$casParalelni = microtime(true);
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);
$casParalelniMs = round((microtime(true) - $casParalelni) * 1000);

echo "=== TEST 3: 3 PARALELNÍ REQUESTY (jako seznam.php) ===\n";
foreach ($handles as $klic => $ch) {
    $info = curl_getinfo($ch);
    $ms   = round($info['total_time'] * 1000);
    $http = $info['http_code'];
    echo sprintf("%-10s %4d ms  [HTTP %d]\n", $klic . ':', $ms, $http);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

echo "\nCelkový čas paralelně:        {$casParalelniMs} ms\n";
echo "\nPokud 'Celkový čas' ≈ max(jednotlivé časy) → paralelní zpracování funguje\n";
echo "Pokud 'Celkový čas' ≈ součet časů            → session lock stále blokuje\n";
