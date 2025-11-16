<?php
/**
 * KOMPLETNÍ SYSTÉMOVÁ DIAGNOSTIKA
 * Zjistí všechny CSP konflikty a priority
 */

echo "=== SYSTÉMOVÁ DIAGNOSTIKA CSP A MAPY ===\n\n";

// 1. CSP ZDROJE
echo "1. CSP DEFINICE V PROJEKTU:\n\n";

echo "   A) .htaccess:54 (Apache level - NEJVYŠŠÍ PRIORITA)\n";
echo "      Header always set Content-Security-Policy\n";
$htaccess = file_get_contents(__DIR__ . '/.htaccess');
preg_match('/Header always set Content-Security-Policy "(.*?)"/', $htaccess, $matches);
if ($matches) {
    $csp = $matches[1];
    echo "      script-src: ";
    preg_match('/script-src ([^;]+)/', $csp, $script);
    echo ($script ? $script[1] : 'NOT FOUND') . "\n";
    echo "      connect-src: ";
    preg_match('/connect-src ([^;]+)/', $csp, $connect);
    echo ($connect ? $connect[1] : 'NOT FOUND') . "\n";

    echo "\n      unpkg.com v script-src? ";
    echo (strpos($csp, 'unpkg.com') !== false ? 'YES ✅' : 'NO ❌') . "\n";
    echo "      api.geoapify.com v connect-src? ";
    echo (strpos($csp, 'api.geoapify.com') !== false || strpos($csp, 'connect-src \'self\' https:') !== false ? 'YES ✅' : 'NO ❌') . "\n";
}

echo "\n   B) includes/security_headers.php:40 (PHP level - STŘEDNÍ PRIORITA)\n";
$secHeaders = file_get_contents(__DIR__ . '/includes/security_headers.php');
preg_match('/script-src ([^"]+)/', $secHeaders, $script2);
echo "      script-src: " . ($script2 ? trim($script2[1]) : 'NOT FOUND') . "\n";
echo "      unpkg.com? " . (strpos($secHeaders, 'unpkg.com') !== false ? 'YES ✅' : 'NO ❌') . "\n";

echo "\n   C) config/config.php:268 (funkce setSecurityHeaders - NEVOLÁ SE!)\n";
$config = file_get_contents(__DIR__ . '/config/config.php');
preg_match('/function setSecurityHeaders.*?script-src ([^;]+)/s', $config, $script3);
echo "      script-src: " . ($script3 ? trim($script3[1]) : 'NOT FOUND') . "\n";
echo "      Volá se? ";
$initPhp = file_get_contents(__DIR__ . '/init.php');
echo (strpos($initPhp, 'setSecurityHeaders()') !== false ? 'YES ✅' : 'NO ❌ NIKDY!') . "\n";

echo "\n   D) admin.php:20 (vlastní CSP pro admin)\n";
$admin = file_get_contents(__DIR__ . '/admin.php');
preg_match('/admin\.php.*script-src ([^;]+)/s', $admin, $script4);
echo "      script-src: " . ($script4 ? trim($script4[1]) : 'Separate file') . "\n";
echo "      (Používá se jen pro admin.php stránku)\n";

// 2. KTERÁ CSP SE SKUTEČNĚ POUŽÍVÁ?
echo "\n\n2. PRIORITA CSP HEADERS:\n";
echo "   Pokud je mod_headers.c enabled:\n";
echo "      1. Apache .htaccess (Header always set) ← PŘEPÍŠE vše ostatní\n";
echo "      2. PHP headers ignorovány\n";
echo "   Pokud mod_headers.c NENÍ enabled:\n";
echo "      1. includes/security_headers.php (z init.php)\n";
echo "      2. Další PHP headers se ignorují (first wins)\n";

// 3. INIT.PHP FLOW
echo "\n\n3. INIT.PHP FLOW:\n";
echo "   init.php:31 → require_once security_headers.php\n";
echo "   security_headers.php:40 → header('Content-Security-Policy: ...')\n";
echo "   Volá se VŽDY při načtení jakékoliv PHP stránky\n";

// 4. MAPA IMPLEMENTACE
echo "\n\n4. MAPA IMPLEMENTACE:\n";
$mapFiles = ['novareklamace.php', 'mimozarucniceny.php'];
foreach ($mapFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        echo "\n   $file:\n";
        echo "      Leaflet script: ";
        echo (preg_match('/unpkg\.com\/leaflet/', $content) ? 'unpkg.com ✅' : 'NOT FOUND ❌') . "\n";
        echo "      Leaflet init: ";
        echo (preg_match('/L\.map\(|initMap/', $content) ? 'YES ✅' : 'NO ❌') . "\n";
        echo "      Geocode proxy: ";
        echo (preg_match('/geocode_proxy\.php/', $content) || preg_match('/api\/geocode/', $content) ? 'YES ✅' : 'NO ❌') . "\n";
    }
}

// 5. GEOAPIFY API KEY
echo "\n\n5. GEOAPIFY API KEY STATUS:\n";
require_once __DIR__ . '/includes/env_loader.php';
$apiKey = getEnvValue('GEOAPIFY_API_KEY', 'NOT_FOUND');
echo "   Value: " . substr($apiKey, 0, 20) . "...\n";
$placeholders = ['your_geoapify_api_key', 'placeholder_geoapify_key', 'change-this-in-production', 'NOT_FOUND'];
echo "   Is placeholder: " . (in_array($apiKey, $placeholders) ? 'YES ❌' : 'NO ✅') . "\n";

// 6. GEOCODE_PROXY.PHP STATUS
echo "\n\n6. GEOCODE_PROXY.PHP STATUS:\n";
$proxy = file_get_contents(__DIR__ . '/api/geocode_proxy.php');
echo "   Stream context defined: ";
echo (preg_match('/\$context = stream_context_create/', $proxy) ? 'YES ✅' : 'NO ❌') . "\n";
echo "   Tile uses context: ";
echo (preg_match('/case \'tile\'.*?file_get_contents\(\$url, false, \$context\)/s', $proxy) ? 'YES ✅' : 'NO ❌') . "\n";
echo "   API key check: ";
echo (preg_match('/if \(!\$apiKey\)/', $proxy) ? 'YES ✅' : 'NO ❌') . "\n";

// 7. KONFLIKTY
echo "\n\n7. MOŽNÉ KONFLIKTY:\n";
$conflicts = [];

// CSP konflikt
if (strpos($htaccess, 'Header always set')) {
    if (!strpos($htaccess, 'unpkg.com')) {
        $conflicts[] = "❌ .htaccess CSP NEOBSAHUJE unpkg.com (a má prioritu!)";
    } else {
        echo "   ✅ .htaccess CSP obsahuje unpkg.com\n";
    }
}

// Security headers duplicita
if (strpos($secHeaders, 'header(') && strpos($htaccess, 'Header always set')) {
    echo "   ⚠️  CSP definováno 2x (Apache přepíše PHP)\n";
}

// setSecurityHeaders() se nevolá
if (strpos($config, 'function setSecurityHeaders') && !strpos($initPhp, 'setSecurityHeaders()')) {
    echo "   ⚠️  setSecurityHeaders() funkce existuje ale NIKDY SE NEVOLÁ\n";
}

if (empty($conflicts)) {
    echo "   Žádné kritické konflikty nenalezeny\n";
} else {
    foreach ($conflicts as $c) {
        echo "   $c\n";
    }
}

echo "\n\n=== ZÁVĚR ===\n";
echo "Zkontroluj skutečný CSP header z live serveru:\n";
echo "curl -I https://www.wgs-service.cz/novareklamace.php | grep -i content-security\n";
